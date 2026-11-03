<?php

declare(strict_types=1);

namespace App\Actions\Sales;

use App\Actions\Sales\Data\ReturnDraft;
use App\Actions\Sales\Data\ReturnLineDraft;
use App\Actions\Settings\EnsureSystemPaymentMethods;
use App\Enums\AllocatableType;
use App\Enums\PaymentDirection;
use App\Enums\PaymentMethodType;
use App\Enums\PaymentStatus;
use App\Enums\RefundMode;
use App\Enums\SaleStatus;
use App\Enums\StockMovementType;
use App\Exceptions\Domain\InvalidSaleDocument;
use App\Exceptions\Domain\InvalidTender;
use App\Exceptions\Domain\ReturnQtyExceeded;
use App\Exceptions\Domain\ReturnWindowClosed;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleItemBatch;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\User;
use App\Support\Audit\Audit;
use App\Support\Database\Transaction;
use App\Support\Money\Money;
use App\Support\Numbering\DocumentNumberer;
use App\Support\Quantity\Quantity;
use App\Support\Sales\BusinessDay;
use App\Support\Sales\CustomerAccounts;
use App\Support\Sales\OrderDiscountShares;
use App\Support\Sales\ReturnPlan;
use App\Support\Sales\SaleGate;
use App\Support\Sales\SoldStock;
use App\Support\Stock\StockLedger;
use App\Support\Tenancy\TenantContext;
use Brick\Math\BigInteger;
use Brick\Math\RoundingMode;
use Illuminate\Support\Carbon;

/**
 * Takes goods back from a completed sale of the current branch (number type `return`). Needs
 * returns.create. Quantity per line ≤ sold − already returned; within the tenant setting
 * `return_days` (null = no limit). Restocked lines go back onto the batches they were sold from;
 * other lines write no stock.
 *
 * Settlement of the return value V: refund and credit first settle the original sale's open due
 * (a customer_credit payment `{return}/C` allocated to that sale). Then refund pays the rest out
 * (`{return}/R`, direction out, allocated to the return; cash unless another method is given) and
 * credit leaves the rest on the account as an advance. Exchange (CreateExchange only) applies V to
 * the replacement sale. customers.balance −= credited; refunded + credited = total.
 * Locking order: document_sequences → branch_stocks → stock_batches → customers → the sale.
 */
final readonly class CreateSaleReturn
{
    public function __construct(
        private DocumentNumberer $numberer,
        private StockLedger $ledger,
        private TenantContext $context,
        private SaleGate $gate,
        private CustomerAccounts $accounts,
        private EnsureSystemPaymentMethods $systemMethods,
    ) {}

    public function handle(User $actor, ReturnDraft $draft): SaleReturn
    {
        $this->gate->authorize($actor, 'create', SaleReturn::class);
        if ($draft->refundMode === RefundMode::Exchange) {
            throw InvalidSaleDocument::exchangeOnly();
        }

        return Transaction::run(function () use ($actor, $draft): SaleReturn {
            $this->systemMethods->handle(); // customer_credit for store-credit refunds, before any lock is taken
            $plan = $this->plan($draft->saleId, $draft->lines);
            if ($draft->refundMode === RefundMode::Credit && $plan->customer->is_walk_in) {
                throw InvalidTender::creditNeedsCustomer();
            }
            $method = $this->refundMethod($draft->refundMode, $draft->refundMethodId);

            $number = $this->numberer->next('return', $this->context->branchId());
            $this->ledger->lock($this->context->branch(), $plan->restockedProducts());
            $return = $this->insertAndRestock($actor, $plan, $number, $draft->refundMode, $draft->reason, $draft->note);

            $customer = $this->accounts->lock($plan->customer);
            $sale = $this->confirm($plan, $draft->lines, $return);
            $this->settle($actor, $return, $sale, $customer, $draft->refundMode, $method);
            $customer?->save();

            Audit::log('sale.returned', $return, [
                'number' => $return->number,
                'sale' => $sale->number,
                'total' => $return->total->paisa,
                'refund_mode' => $return->refund_mode->value,
                'refunded' => $return->refunded->paisa,
                'credited' => $return->credited->paisa,
            ], $actor);

            return $return->load('items');
        });
    }

    /**
     * Reads and values the return. With $lock the sale, its lines and their batch links are read
     * FOR UPDATE (the confirm step).
     *
     * @param  list<ReturnLineDraft>  $lines
     */
    public function plan(int $saleId, array $lines, bool $lock = false, ?int $excludeReturnId = null): ReturnPlan
    {
        if ($lines === []) {
            throw InvalidSaleDocument::noLines();
        }

        $query = Sale::query()->whereKey($saleId);
        $sale = ($lock ? $query->lockForUpdate() : $query)->firstOrFail();
        $number = (string) $sale->number;
        if ($sale->status !== SaleStatus::Completed) {
            throw InvalidSaleDocument::notCompleted($number);
        }

        $window = $this->context->tenant()->setting('return_days');
        if (is_int($window) && BusinessDay::daysBetween($sale->sold_at, Carbon::now(), BusinessDay::timezone($this->context->tenant())) > $window) {
            throw ReturnWindowClosed::for($number, $window);
        }

        $itemsQuery = SaleItem::query()->where('sale_id', $sale->id)->orderBy('id');
        $items = ($lock ? $itemsQuery->lockForUpdate() : $itemsQuery)->get()->keyBy('id');
        $linksQuery = SaleItemBatch::query()->whereIn('sale_item_id', $items->keys()->all())->orderBy('id');
        $links = ($lock ? $linksQuery->lockForUpdate() : $linksQuery)->get()->groupBy('sale_item_id');
        foreach ($items as $item) {
            $item->setRelation('batches', $links->get($item->id) ?? collect());
        }

        $shares = OrderDiscountShares::of($sale, array_values($items->all()));
        $previous = SaleReturnItem::query()
            ->whereIn('sale_item_id', $items->keys()->all())
            ->when($excludeReturnId !== null, static fn ($query) => $query->where('sale_return_id', '!=', $excludeReturnId))
            ->groupBy('sale_item_id')
            ->selectRaw('sale_item_id, SUM(line_total) AS value, SUM(tax_amount) AS tax')
            ->get()
            ->keyBy('sale_item_id');

        $customer = Customer::query()->withTrashed()->findOrFail($sale->customer_id);
        $products = Product::query()->withTrashed()->whereIn('id', $items->pluck('product_id')->all())->get()->keyBy('id');

        $planned = [];
        $seen = [];
        foreach ($lines as $index => $line) {
            $item = $items->get($line->saleItemId) ?? throw InvalidSaleDocument::itemNotInSale($line->saleItemId, $number);
            if (! $line->qty->isPositive()) {
                throw InvalidSaleDocument::nonPositiveQuantity($index + 1);
            }
            if (isset($seen[$item->id])) {
                throw InvalidSaleDocument::duplicateLine($item->id);
            }
            $seen[$item->id] = true;

            $returnable = $item->qty->minus($item->qty_returned);
            if ($line->qty->compareTo($returnable) > 0) {
                throw ReturnQtyExceeded::forItem($item->id, $line->qty, $returnable);
            }

            $payable = $item->line_total->minus($shares[$item->id]);
            if ($line->qty->equals($returnable)) {
                $done = $previous->get($item->id);
                $value = $payable->minus(Money::ofPaisa((int) ($done?->getAttribute('value') ?? 0)));
                $tax = $item->tax_amount->minus(Money::ofPaisa((int) ($done?->getAttribute('tax') ?? 0)));
            } else {
                $value = self::prorate($payable, $line->qty, $item->qty);
                $tax = self::prorate($item->tax_amount, $line->qty, $item->qty);
            }

            $planned[] = [
                'item' => $item,
                'product' => $products->get($item->product_id) ?? throw InvalidSaleDocument::itemNotInSale($item->id, $number),
                'qty' => $line->qty,
                'restock' => $line->restock,
                'value' => $value->isNegative() ? Money::zero() : $value,
                'tax' => $tax->isNegative() ? Money::zero() : $tax,
                'portions' => $line->restock ? SoldStock::forReturn($item, $line->qty) : [],
            ];
        }

        return new ReturnPlan($sale, $customer, $planned);
    }

    /** Writes the return and its lines and puts restocked goods back. Inside Transaction::run(), stock rows locked. */
    public function insertAndRestock(User $actor, ReturnPlan $plan, string $number, RefundMode $mode, string $reason, ?string $note): SaleReturn
    {
        $total = $plan->total();
        $tax = $plan->tax();
        $branch = $this->context->branch();

        $return = SaleReturn::query()->create([
            'number' => $number,
            'sale_id' => $plan->sale->id,
            'customer_id' => $plan->customer->id,
            'returned_at' => Carbon::now(),
            'subtotal' => $total->minus($tax),
            'tax_total' => $tax,
            'total' => $total,
            'refund_mode' => $mode,
            'refunded' => Money::zero(),
            'credited' => Money::zero(),
            'reason' => mb_substr($reason, 0, 32),
            'note' => $note,
            'created_by' => $actor->id,
        ]);

        foreach ($plan->lines as $line) {
            $cogs = Money::zero();
            foreach ($line['portions'] as $portion) {
                $cogs = $cogs->plus($portion['allocation']['unit_cost']->times($portion['allocation']['qty']));
            }

            $returnItem = SaleReturnItem::query()->create([
                'sale_return_id' => $return->id,
                'sale_item_id' => $line['item']->id,
                'product_id' => $line['product']->id,
                'qty' => $line['qty'],
                'unit_price' => $line['item']->unit_price,
                'tax_amount' => $line['tax'],
                'line_total' => $line['value'],
                'restock' => $line['restock'],
                'cogs_reversed' => $cogs,
            ]);

            if ($line['restock'] && $line['portions'] !== []) {
                $this->ledger->reverse(
                    $line['product'],
                    $branch,
                    array_map(static fn (array $portion): array => $portion['allocation'], $line['portions']),
                    StockMovementType::SaleReturn,
                    ['type' => 'sale_return_items', 'id' => $returnItem->id, 'user_id' => $actor->id],
                );
            }
        }

        return $return;
    }

    /**
     * Re-reads the sale with row locks (after the customer lock), checks nothing changed since the
     * plan, and books the returned quantities.
     *
     * @param  list<ReturnLineDraft>  $lines
     */
    public function confirm(ReturnPlan $plan, array $lines, SaleReturn $return): Sale
    {
        $fresh = $this->plan($plan->sale->id, $lines, true, $return->id);
        if ($fresh->signature() !== $plan->signature()) {
            throw InvalidSaleDocument::changed((string) $plan->sale->number);
        }

        foreach ($fresh->lines as $line) {
            $line['item']->qty_returned = $line['item']->qty_returned->plus($line['qty']);
            $line['item']->save();
            foreach ($line['portions'] as $portion) {
                if ($portion['link'] instanceof SaleItemBatch) {
                    $portion['link']->qty_returned = $portion['link']->qty_returned->plus($portion['allocation']['qty']);
                    $portion['link']->save();
                }
            }
        }

        $sale = $fresh->sale;
        $sale->returned_total = $sale->returned_total->plus($fresh->total());
        $sale->save();

        return $sale;
    }

    /**
     * Settles the return value. $exchangeApplied is the part that paid an exchange's replacement
     * sale (refund mode then means "refund the surplus").
     */
    public function settle(
        User $actor,
        SaleReturn $return,
        Sale $sale,
        ?Customer $customer,
        RefundMode $mode,
        ?PaymentMethod $refundMethod,
        ?Money $exchangeApplied = null,
        RefundMode $surplusMode = RefundMode::Refund,
    ): void {
        $value = $return->total;
        $party = $customer ?? Customer::query()->withTrashed()->findOrFail($return->customer_id);
        $now = Carbon::now();
        $credited = Money::zero();
        $rest = $value;

        if ($mode === RefundMode::Exchange) {
            $credited = $exchangeApplied ?? Money::zero();
            $rest = $value->minus($credited);
            $mode = $surplusMode;
        } else {
            $offset = $sale->due->compareTo($value) < 0 ? $sale->due : $value;
            if ($offset->paisa > 0) {
                $this->accounts->recordPayment(
                    $actor,
                    $return->number.'/C',
                    PaymentDirection::In,
                    $party,
                    $this->accounts->systemMethod(PaymentMethodType::CustomerCredit),
                    $offset,
                    $now,
                    [[AllocatableType::Sale, $sale->id, $offset]],
                    note: 'return '.$return->number,
                );
                $sale->paid = $sale->paid->plus($offset);
                $sale->due = $sale->due->minus($offset);
                $sale->payment_status = $sale->due->isZero() ? PaymentStatus::Paid : PaymentStatus::Partial;
                $sale->save();
                $credited = $offset;
                $rest = $value->minus($offset);
            }
        }

        $refunded = Money::zero();
        if ($rest->paisa > 0) {
            if ($mode === RefundMode::Credit) {
                if (! $customer instanceof Customer) {
                    throw InvalidTender::creditNeedsCustomer();
                }
                $credited = $credited->plus($rest);
            } else {
                $this->accounts->recordPayment(
                    $actor,
                    $return->number.'/R',
                    PaymentDirection::Out,
                    $party,
                    $refundMethod ?? $this->accounts->systemMethod(PaymentMethodType::Cash),
                    $rest,
                    $now,
                    [[AllocatableType::SaleReturn, $return->id, $rest]],
                    note: 'refund '.$return->number,
                );
                $refunded = $rest;
            }
        }

        $return->refunded = $refunded;
        $return->credited = $credited;
        $return->save();

        if ($customer instanceof Customer) {
            $customer->balance = $customer->balance->minus($credited);
        }
    }

    /** The refund method for refund mode (given, else cash); never a credit method. */
    public function refundMethod(RefundMode $mode, ?int $methodId): ?PaymentMethod
    {
        if ($mode === RefundMode::Credit) {
            return null;
        }
        $method = $methodId === null
            ? $this->accounts->systemMethod(PaymentMethodType::Cash)
            : PaymentMethod::query()->findOrFail($methodId);
        if (! $method->is_active) {
            throw InvalidTender::inactive($method->name);
        }
        if (CustomerAccounts::isCredit($method)) {
            throw InvalidTender::notMoney($method->name);
        }

        return $method;
    }

    private static function prorate(Money $amount, Quantity $part, Quantity $whole): Money
    {
        return Money::ofPaisa(BigInteger::of($amount->paisa)->multipliedBy($part->milli)->dividedBy($whole->milli, RoundingMode::HalfUp)->toInt());
    }
}
