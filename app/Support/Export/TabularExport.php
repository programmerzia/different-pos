<?php

declare(strict_types=1);

namespace App\Support\Export;

use App\Reports\ReportCsv;
use App\Reports\ReportResult;
use App\Support\Pdf\PdfRenderer;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * One download path for every tabular export: a `ReportResult` (columns + rows + totals) becomes a
 * streamed CSV, a streamed .xlsx or a synchronous PDF. The PDF is capped at PdfRenderer::MAX_TABLE_ROWS
 * and refused with a validation error above it, so nothing is ever cut off silently.
 */
final readonly class TabularExport
{
    public function __construct(private PdfRenderer $pdf) {}

    public function respond(ReportResult $result, ExportFormat $format, string $basename, ExportMeta $meta): Response|StreamedResponse
    {
        $filename = $basename.'.'.$format->extension();

        return match ($format) {
            ExportFormat::Csv => ReportCsv::stream($result, $filename),
            ExportFormat::Xlsx => XlsxWriter::stream($result, $filename),
            ExportFormat::Pdf => $this->pdf($result, $meta, $filename),
        };
    }

    private function pdf(ReportResult $result, ExportMeta $meta, string $filename): Response
    {
        $count = count($result->rows);
        if ($count > PdfRenderer::MAX_TABLE_ROWS) {
            throw ValidationException::withMessages([
                'format' => __('app.export.pdf_too_large', ['count' => number_format($count), 'max' => number_format(PdfRenderer::MAX_TABLE_ROWS)]),
            ]);
        }

        return response($this->pdf->table($result, $meta), 200, [
            'Content-Type' => ExportFormat::Pdf->contentType(),
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
