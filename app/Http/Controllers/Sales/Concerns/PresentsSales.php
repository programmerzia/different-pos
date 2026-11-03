<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sales\Concerns;

use App\Actions\Sales\Data\ReturnLineDraft;
use App\Enums\PaymentMethodType;
use App\Enums\SaleStatus;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Models\User;
use App\Support\Customers\CustomerNumber;
use App\Support\Dates\Period;
use App\Support\Quantity\Quantity;
use App\Support\Receipts\ReceiptRenderer;
use App\Support\Sales\BusinessDay;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** Small shared helpers for the sales, returns and dues pages. */
trait PresentsSales
{
    protected function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    protected function timezone(): string
    {
        return BusinessDay::timezone(app(TenantContext::class)->tenant());
    }

    /** Tenant-local "Y-m-d H:i" (the client only swaps digits). */
    protected function localTime(?CarbonInterface $at): ?string
    {
        return $at?->copy()->setTimezone($this->timezone())->format('Y-m-d H:i');
    }

    /** @return array{id: int, uuid: string, name: string, shop_name: string|null, customer_no: string|null, phone: string|null, walk_in: bool}|null */
    protected function customerSummary(?Customer $customer): ?array
    {
        if (! $customer instanceof Customer) {
            return null;
        }

        return [
            'id' => $customer->id,
            'uuid' => $customer->uuid,
            'name' => $customer->name,
            'shop_name' => $customer->shop_name,
            'customer_no' => $customer->customer_no !== null ? CustomerNumber::format((string) $customer->customer_no) : null,
            'phone' => $customer->phone,
            'walk_in' => $customer->is_walk_in,
        ];
    }

    /**
     * Applies filter[from] / filter[to] (tenant-local YYYY-MM-DD) to a timestamp column. A named
     * filter[period] (Period::keys(): today, week = Saturday to today, month, ...) wins over from/to.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    protected function dateRange(Builder $query, string $column, ?string $from, ?string $to, ?string $period = null): Builder
    {
        $named = Period::range($period, $this->timezone());
        if ($named !== null) {
            [$from, $to] = [$named['from'], $named['to']];
        }

        return BusinessDay::whereLocalDates($query, $column, $from, $to, $this->timezone());
    }

    /** True when VoidSale would accept the sale (same business day, completed, no returns, not an exchange replacement). */
    protected function voidable(Sale $sale): bool
    {
        return $sale->status === SaleStatus::Completed
            && $sale->returned_total->isZero()
            && $sale->exchange_return_id === null
            && BusinessDay::isSameDay($sale->sold_at, Carbon::now(), $this->timezone());
    }

    /** @return list<array{id: int, name: string, type: string, requires_reference: bool}> */
    protected function moneyMethods(): array
    {
        return array_values(PaymentMethod::query()
            ->where('is_active', true)
            ->whereNotIn('type', [PaymentMethodType::CustomerCredit, PaymentMethodType::ExchangeCredit])
            ->orderBy('sort_order')->orderBy('id')
            ->get()
            ->map(static fn (PaymentMethod $m): array => [
                'id' => $m->id, 'name' => $m->name, 'type' => $m->type->value, 'requires_reference' => $m->requires_reference,
            ])->all());
    }

    /** @return list<ReturnLineDraft> */
    protected function returnLines(Request $request): array
    {
        $lines = [];
        foreach ((array) $request->input('lines', []) as $line) {
            if (is_array($line)) {
                $lines[] = new ReturnLineDraft((int) $line['sale_item_id'], Quantity::ofMilli((int) $line['qty']), (bool) $line['restock']);
            }
        }

        return $lines;
    }

    protected function receiptLocale(Request $request): string
    {
        $lang = $request->query('lang');

        return is_string($lang) && in_array($lang, ['en', 'bn'], true) ? $lang : (app()->getLocale() === 'bn' ? 'bn' : 'en');
    }

    /**
     * Paper switcher links for the print toolbar.
     *
     * @return list<array{label: string, url: string, active: bool}>
     */
    protected function paperLinks(string $route, int $id, string $paper, string $locale): array
    {
        $links = [];
        foreach (ReceiptRenderer::PAPERS as $option) {
            $links[] = [
                'label' => $option === 'a4' ? 'A4' : $option.' mm',
                'url' => route($route, [$id, 'paper' => $option, 'lang' => $locale]),
                'active' => $option === $paper,
            ];
        }
        $other = $locale === 'bn' ? 'en' : 'bn';
        $links[] = ['label' => $other === 'bn' ? 'বাংলা' : 'English', 'url' => route($route, [$id, 'paper' => $paper, 'lang' => $other]), 'active' => false];

        return $links;
    }
}
