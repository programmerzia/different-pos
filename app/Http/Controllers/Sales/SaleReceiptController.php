<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sales;

use App\Enums\SaleStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Sales\Concerns\PresentsSales;
use App\Models\Courier;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Support\Receipts\ChalanCourier;
use App\Support\Receipts\ReceiptBuilder;
use App\Support\Receipts\ReceiptRenderer;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use RuntimeException;

/**
 * Print views: sale receipt (?paper=80|58|a4), return receipt and money receipt. HTML for the
 * browser's print dialog; ?format=pdf answers 501 until mPDF is installed.
 */
final class SaleReceiptController extends Controller
{
    use AuthorizesRequests, PresentsSales;

    public function __construct(private readonly ReceiptBuilder $builder, private readonly ReceiptRenderer $renderer) {}

    public function sale(Request $request, int $sale): Response
    {
        $model = Sale::query()->findOrFail($sale);
        $this->authorize('view', $model);
        $paper = ReceiptRenderer::paper($request->string('paper')->value() ?: null);
        $locale = $this->useLocale($request);

        return $this->respond($request, $paper === 'a4' ? 'a4' : 'thermal', $this->builder->sale($model, $locale), $paper, 'sales.receipt', $model->id);
    }

    /** Delivery chalan: A4 only, quantities plus the COD amount (the due) when there is one. */
    public function chalan(Request $request, int $sale): Response
    {
        $model = Sale::query()->findOrFail($sale);
        $this->authorize('view', $model);
        $locale = $this->useLocale($request);
        $requested = $request->query('courier');
        $requestedId = is_string($requested) && ctype_digit($requested) && strlen($requested) < 19 ? (int) $requested : null;
        $courier = ChalanCourier::resolve($model, $requestedId);
        $cod = $request->query('cod') !== '0';
        $chalan = $this->builder->chalan($model, $locale, $courier, $cod);
        $other = $locale === 'bn' ? 'en' : 'bn';
        // Print-only choice: carried through the language switch and the other couriers' links.
        $picked = $requestedId !== null && $courier !== null && Courier::query()->whereKey($requestedId)->where('is_active', true)->exists() ? $requestedId : null;

        $toolbar = [[
            'label' => $other === 'bn' ? 'বাংলা' : 'English',
            'url' => route('sales.chalan', self::query([$model->id, 'lang' => $other, 'courier' => $picked, 'cod' => $cod ? null : 0, 'print' => $request->boolean('print') ? 1 : null])),
            'active' => false,
        ]];
        $choices = Courier::query()->where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get();
        if ($choices->count() > 1) {
            foreach ($choices as $choice) {
                $toolbar[] = [
                    'label' => __('receipts.chalan.courier').': '.$choice->name,
                    'url' => route('sales.chalan', self::query([$model->id, 'lang' => $locale, 'courier' => $choice->id, 'cod' => $cod ? null : 0, 'print' => $request->boolean('print') ? 1 : null])),
                    'active' => $courier !== null && mb_strtolower($courier['name']) === mb_strtolower($choice->name),
                ];
            }
        }

        // COD on/off applies only to a sale that owes something; a paid or voided one never offers it.
        if ($model->status !== SaleStatus::Voided && $model->due->paisa > 0) {
            $toggle = static fn (bool $on): string => route('sales.chalan', self::query([$model->id, 'lang' => $locale, 'courier' => $picked, 'cod' => $on ? null : 0, 'print' => $request->boolean('print') ? 1 : null]));
            $toolbar[] = ['label' => __('receipts.chalan.cod_toggle').': '.__('receipts.chalan.'.($cod ? 'cod_on' : 'cod_off')), 'url' => $toggle(! $cod), 'active' => false];
        }

        $html = $this->renderer->html('chalan', $chalan, 'a4', [
            'toolbar' => $toolbar,
            'autoprint' => $request->boolean('print'),
        ]);

        return response($html, 200, ['Content-Type' => 'text/html; charset=utf-8', 'Cache-Control' => 'no-store', 'X-Robots-Tag' => 'noindex']);
    }

    public function saleReturn(Request $request, int $return): Response
    {
        $model = SaleReturn::query()->findOrFail($return);
        $this->authorize('view', $model);
        $paper = ReceiptRenderer::paper($request->string('paper')->value() ?: null);
        $locale = $this->useLocale($request);

        return $this->respond($request, 'return', $this->builder->saleReturn($model, $locale), $paper, 'returns.receipt', $model->id);
    }

    public function payment(Request $request, int $payment): Response
    {
        $model = Payment::query()->findOrFail($payment);
        $this->authorize('view', $model);
        $paper = ReceiptRenderer::paper($request->string('paper')->value() ?: null);
        $locale = $this->useLocale($request);

        return $this->respond($request, 'payment', $this->builder->payment($model, $locale), $paper, 'payments.receipt', $model->id);
    }

    /**
     * Drops the null entries only (array_filter would also drop cod=0).
     *
     * @param  array<int|string, mixed>  $params
     * @return array<int|string, mixed>
     */
    private static function query(array $params): array
    {
        return array_filter($params, static fn (mixed $value): bool => $value !== null);
    }

    private function useLocale(Request $request): string
    {
        $locale = $this->receiptLocale($request);
        app()->setLocale($locale);

        return $locale;
    }

    /** @param  array<string, mixed>  $receipt */
    private function respond(Request $request, string $view, array $receipt, string $paper, string $route, int $id): Response
    {
        if ($request->query('format') === 'pdf') {
            try {
                return response($this->renderer->pdf($view, $receipt, $paper), 200, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'inline; filename="'.$id.'.pdf"',
                ]);
            } catch (RuntimeException) {
                return response(__('receipts.pdf_unavailable'), 501, ['Content-Type' => 'text/plain; charset=utf-8']);
            }
        }

        $html = $this->renderer->html($view, $receipt, $paper, [
            'toolbar' => $this->paperLinks($route, $id, $paper, (string) $receipt['locale']),
            'autoprint' => $request->boolean('print'),
        ]);

        return response($html, 200, ['Content-Type' => 'text/html; charset=utf-8', 'Cache-Control' => 'no-store', 'X-Robots-Tag' => 'noindex']);
    }
}
