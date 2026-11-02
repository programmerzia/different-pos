<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Sales\Concerns\PresentsSales;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\SaleReturn;
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
