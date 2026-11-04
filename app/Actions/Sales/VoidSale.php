<?php

declare(strict_types=1);

namespace App\Actions\Sales;

use App\Enums\AllocatableType;
use App\Enums\SaleStatus;
use App\Enums\StockMovementType;
use App\Events\SaleVoided;
use App\Exceptions\Domain\InvalidSaleDocument;
use App\Exceptions\Domain\VoidWindowClosed;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Support\Audit\Audit;
use App\Support\Database\Transaction;
use App\Support\Money\Money;
use App\Support\Sales\BusinessDay;
use App\Support\Sales\CustomerAccounts;
use App\Support\Sales\SaleGate;
use App\Support\Sales\SoldStock;
use App\Support\Stock\StockLedger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Voids a completed sale on its own business day (tenant timezone); afterwards only a return is
 * possible. Needs sales.void. Sales with returns and exchange replacement sales cannot be voided.
 *
 * Stock goes back onto the batches it came from (StockLedger::reverse, type sale_void). Payments
 * that settle only this sale are voided (the money goes back over the counter); a payment that also
 * settles other documents loses just this allocation, which becomes an advance. The customer's
 * balance drops by total − voided money. Locking order: branch_stocks → stock_batches → customers → sale.
 */
final readonly class VoidSale
{
    public function __construct(
        private StockLedger $ledger,
        private TenantContext $context,
        private SaleGate $gate,
        private CustomerAccounts $accounts,
    ) {}

    public function handle(User $actor, Sale $sale, string $reason): Sale
    {
        return Transaction::run(function () use ($actor, $sale, $reason): Sale {
            $sale = Sale::query()->findOrFail($sale->id);
            $this->gate->authorize($actor, 'void', $sale);
            $this->assertVoidable($sale);

            $branch = $this->context->branch();
            /** @var list<SaleItem> $items */
            $items = SaleItem::query()->where('sale_id', $sale->id)->orderBy('id')->with('batches')->get()->all();
            $products = Product::query()->withTrashed()->whereIn('id', array_map(static fn (SaleItem $item): int => $item->product_id, $items))->get()->keyBy('id');

            $this->ledger->lock($branch, array_values(array_filter($products->all(), static fn (Product $product): bool => $product->track_stock)));

            foreach ($items as $item) {
                $product = $products->get($item->product_id);
                if (! $product instanceof Product) {
                    continue;
                }
                $this->ledger->reverse($product, $branch, SoldStock::all($item), StockMovementType::SaleVoid, [
                    'type' => 'sale_items', 'id' => $item->id, 'user_id' => $actor->id, 'note' => 'void',
                ]);
            }

            $customer = Customer::query()->withTrashed()->findOrFail($sale->customer_id);
            $locked = $this->accounts->lock($customer);

            $sale = Sale::query()->whereKey($sale->id)->lockForUpdate()->firstOrFail();
            $this->assertVoidable($sale);

            $returned = Money::zero();
            $allocations = PaymentAllocation::query()
                ->where('allocatable_type', AllocatableType::Sale)
                ->where('allocatable_id', $sale->id)
                ->orderBy('id')
                ->get();
            foreach ($allocations as $allocation) {
                $payment = Payment::query()->withoutBranchScope()->findOrFail($allocation->payment_id);
                if ($payment->voided_at !== null) {
                    continue;
                }
                $elsewhere = PaymentAllocation::query()
                    ->where('payment_id', $payment->id)
                    ->whereKeyNot($allocation->id)
                    ->exists();

                if ($elsewhere || $payment->branch_id !== $sale->branch_id) {
                    $allocation->delete();

                    continue;
                }

                $payment->voided_at = Carbon::now();
                $payment->save();
                if (! CustomerAccounts::isCredit(PaymentMethod::query()->findOrFail($payment->payment_method_id))) {
                    $returned = $returned->plus($payment->amount);
                }
            }

            $sale->status = SaleStatus::Voided;
            $sale->voided_by = $actor->id;
            $sale->voided_at = Carbon::now();
            $sale->void_reason = $reason;
            $sale->save();

            if ($locked instanceof Customer) {
                $locked->balance = $locked->balance->minus($sale->total)->plus($returned);
                $locked->save();
            }

            Audit::log('sale.voided', $sale, [
                'number' => $sale->number,
                'reason' => $reason,
                'total' => $sale->total->paisa,
                'money_returned' => $returned->paisa,
            ], $actor);

            DB::afterCommit(static fn () => SaleVoided::dispatch($sale->id));

            return $sale->load('items');
        });
    }

    private function assertVoidable(Sale $sale): void
    {
        $number = (string) $sale->number;
        if ($sale->status !== SaleStatus::Completed) {
            throw InvalidSaleDocument::notCompleted($number);
        }
        if (! BusinessDay::isSameDay($sale->sold_at, Carbon::now(), BusinessDay::timezone($this->context->tenant()))) {
            throw VoidWindowClosed::for($number);
        }
        if (! $sale->returned_total->isZero() || $sale->returns()->exists()) {
            throw InvalidSaleDocument::notVoidable($number, 'it has returns');
        }
        if ($sale->exchange_return_id !== null) {
            throw InvalidSaleDocument::notVoidable($number, 'it is the replacement sale of an exchange');
        }
    }
}
