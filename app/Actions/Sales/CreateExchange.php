<?php

declare(strict_types=1);

namespace App\Actions\Sales;

use App\Actions\Sales\Data\ExchangeDraft;
use App\Actions\Sales\Data\ExchangeResult;
use App\Actions\Sales\Data\SaleDraft;
use App\Enums\RefundMode;
use App\Enums\SaleOrigin;
use App\Exceptions\Domain\InvalidSaleDocument;
use App\Exceptions\Domain\InvalidTender;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\User;
use App\Support\Audit\Audit;
use App\Support\Database\Transaction;
use App\Support\Numbering\DocumentNumberer;
use App\Support\Sales\CustomerAccounts;
use App\Support\Sales\SaleGate;
use App\Support\Stock\StockLedger;
use App\Support\Tenancy\TenantContext;

/**
 * An exchange (ADR 0005) in one transaction: CreateSaleReturn with refund_mode exchange plus a
 * replacement CompleteSale for the same customer. The server-valued return pays the replacement as an
 * Exchange credit tender (up to its total); the replacement's tenders pay any difference; a surplus is
 * refunded or credited. Links sale_returns.exchange_sale_id ↔ sales.exchange_return_id.
 * Needs returns.create + pos.sell. Online only, back-office numbers for both halves for now (the
 * device number for the replacement comes with the device API).
 * Locking order: document_sequences (return, sale) → branch_stocks → stock_batches → customers → the
 * original sale.
 */
final readonly class CreateExchange
{
    public function __construct(
        private CreateSaleReturn $returns,
        private CompleteSale $sales,
        private DocumentNumberer $numberer,
        private StockLedger $ledger,
        private TenantContext $context,
        private SaleGate $gate,
        private CustomerAccounts $accounts,
    ) {}

    public function handle(User $actor, ExchangeDraft $draft): ExchangeResult
    {
        $this->gate->authorize($actor, 'create', SaleReturn::class);
        $this->gate->authorize($actor, 'create', Sale::class);
        if ($draft->surplusMode === RefundMode::Exchange) {
            throw InvalidSaleDocument::exchangeOnly();
        }

        return Transaction::run(function () use ($actor, $draft): ExchangeResult {
            $plan = $this->returns->plan($draft->saleId, $draft->returnLines);
            $customer = $plan->customer;

            $wanted = $draft->replacement->customerId;
            if ($wanted !== null && $wanted !== $customer->id) {
                throw InvalidSaleDocument::exchangeCustomer();
            }
            if ($draft->surplusMode === RefundMode::Credit && $customer->is_walk_in) {
                throw InvalidTender::creditNeedsCustomer();
            }

            $replacement = self::forCustomer($draft->replacement, $customer->is_walk_in ? null : $customer->id);
            $value = $plan->total();
            $prepared = $this->sales->prepare($actor, $replacement, SaleOrigin::Web, $value);
            $refundMethod = $this->returns->refundMethod($draft->surplusMode, $draft->refundMethodId);

            $branch = $this->context->branch();
            $returnNumber = $this->numberer->next('return', $branch->id);
            $saleNumber = $this->numberer->next('sale', $branch->id);

            $products = [];
            foreach ([...$plan->restockedProducts(), ...$prepared->trackedProducts()] as $product) {
                $products[$product->id] = $product;
            }
            $this->ledger->lock($branch, array_values($products));

            $return = $this->returns->insertAndRestock($actor, $plan, $returnNumber, RefundMode::Exchange, $draft->reason, $draft->note);
            $sale = $this->sales->insertAndIssue($actor, $prepared, $saleNumber, $return->id);

            $locked = $this->accounts->lock($customer);
            $original = $this->returns->confirm($plan, $draft->returnLines, $return);
            $this->returns->settle($actor, $return, $original, $locked, RefundMode::Exchange, $refundMethod, $prepared->exchangeCredit, $draft->surplusMode);
            $this->sales->settle($actor, $prepared, $sale, $locked);
            $locked?->save();

            $return->exchange_sale_id = $sale->id;
            $return->save();

            $difference = $sale->total->minus($return->total);
            Audit::log('sale.exchanged', $return, [
                'return' => $return->number,
                'original_sale' => $original->number,
                'replacement_sale' => $sale->number,
                'return_total' => $return->total->paisa,
                'sale_total' => $sale->total->paisa,
                'difference' => $difference->paisa,
                'overrides' => $prepared->overrides,
            ], $actor);

            return new ExchangeResult($return->load('items'), $sale->load('items'), $difference);
        });
    }

    private static function forCustomer(SaleDraft $draft, ?int $customerId): SaleDraft
    {
        return new SaleDraft(
            lines: $draft->lines,
            tenders: $draft->tenders,
            customerId: $customerId,
            registerId: $draft->registerId,
            orderDiscount: $draft->orderDiscount,
            note: $draft->note,
            expectedTotal: $draft->expectedTotal,
            clientUuid: $draft->clientUuid,
            approver: $draft->approver,
            courierName: $draft->courierName,
            courierTracking: $draft->courierTracking,
        );
    }
}
