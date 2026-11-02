<?php

declare(strict_types=1);

namespace App\Actions\Sales;

use App\Enums\SaleStatus;
use App\Exceptions\Domain\InvalidSaleDocument;
use App\Models\Sale;
use App\Models\User;
use App\Support\Audit\Audit;
use App\Support\Database\Transaction;
use App\Support\Sales\SaleGate;
use Illuminate\Support\Str;

/**
 * Makes sure a completed sale has a public receipt token (/r/{token}) and returns it. New sales get
 * one at completion; imported sales get one the first time someone shares them. Needs sales.view.
 */
final readonly class ShareSaleReceipt
{
    public function __construct(private SaleGate $gate) {}

    public function handle(User $actor, Sale $sale): string
    {
        $this->gate->authorize($actor, 'view', $sale);

        return Transaction::run(function () use ($actor, $sale): string {
            $sale = Sale::query()->whereKey($sale->id)->lockForUpdate()->firstOrFail();
            if ($sale->status !== SaleStatus::Completed) {
                throw InvalidSaleDocument::notCompleted((string) $sale->number);
            }
            if ($sale->public_token !== null) {
                return $sale->public_token;
            }

            $token = Str::random(32);
            $sale->public_token = $token;
            $sale->save();
            Audit::log('sale.receipt_shared', $sale, ['number' => $sale->number], $actor);

            return $token;
        });
    }
}
