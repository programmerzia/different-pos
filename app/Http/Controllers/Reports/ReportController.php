<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ReportRequest;
use App\Http\Requests\Sales\StoreSaleReturnRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\User;
use App\Models\Vendor;
use App\Reports\CustomersByArea;
use App\Reports\CustomersByLastPurchase;
use App\Reports\ReportContract;
use App\Reports\ReportRegistry;
use App\Support\Export\ExportMeta;
use App\Support\Export\TabularExport;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ReportController extends Controller
{
    public function __construct(private readonly ReportRegistry $registry) {}

    public function index(Request $request): Response
    {
        Gate::authorize('reports.view');

        $groups = [];
        foreach ($this->registry->all() as $report) {
            $groups[$report->group()][] = ['name' => $report->name()];
        }

        $user = $request->user();

        return Inertia::render('reports/index', [
            'groups' => $groups,
            'canExport' => $user instanceof User && $user->can('reports.export'),
        ]);
    }

    public function show(ReportRequest $request, string $report): Response
    {
        Gate::authorize('reports.view');
        $definition = $this->find($report);
        $parameters = $request->parameters($definition);

        $result = $definition->run($parameters);
        if (! $parameters->canProfit) {
            $result = $result->withoutProfit();
        }

        $user = $request->user();
        $canAll = $user instanceof User && $user->can('reports.all_branches');

        return Inertia::render('reports/show', [
            'report' => [
                'name' => $definition->name(),
                'usesPeriod' => $definition->usesPeriod(),
                'filters' => $definition->filters(),
            ],
            'params' => [
                'from' => $parameters->fromDate,
                'to' => $parameters->toDate,
                'branch' => $parameters->branchIds === null ? 'all' : (count($parameters->branchIds) === 1 && $parameters->branchIds[0] !== app(TenantContext::class)->branchId() ? (string) $parameters->branchIds[0] : 'current'),
                'filters' => $parameters->filters,
            ],
            'result' => $result->toArray(),
            'canAllBranches' => $canAll,
            'canExport' => $user instanceof User && $user->can('reports.export'),
            'options' => $this->options($definition),
        ]);
    }

    /**
     * POST /app/reports/{report}/export?format=csv|xlsx|pdf (+ the report's query string).
     * CSV and XLSX stream; the PDF renders synchronously up to PdfRenderer::MAX_TABLE_ROWS and is
     * refused with a validation error above that. Filename `<report>-<from>-<to>.<ext>`.
     */
    public function export(ReportRequest $request, string $report, TabularExport $export, TenantContext $context): HttpResponse|StreamedResponse
    {
        Gate::authorize('reports.export');
        $definition = $this->find($report);
        $parameters = $request->parameters($definition);

        $result = $definition->run($parameters);
        if (! $parameters->canProfit) {
            $result = $result->withoutProfit();
        }

        $period = $definition->usesPeriod()
            ? ExportMeta::periodLabel($parameters->fromDate, $parameters->toDate)
            : $parameters->today()->format('Y-m-d');
        $name = $definition->name().($definition->usesPeriod() ? "-{$parameters->fromDate}-{$parameters->toDate}" : '-'.$parameters->today()->format('Y-m-d'));
        $title = ucfirst(str_replace('-', ' ', $definition->name()));

        return $export->respond($result, $request->exportFormat(), $name, ExportMeta::for($context, $title, $period));
    }

    /**
     * Choices for the report's own filters (only the ones it declares, to keep the page light).
     *
     * @return array<string, array<mixed>>
     */
    private function options(ReportContract $definition): array
    {
        $has = static fn (string $filter): bool => in_array($filter, $definition->filters(), true);

        return [
            'categories' => $has('category') ? Category::query()->orderBy('name')->get(['id', 'name'])->toArray() : [],
            'brands' => $has('brand') ? Brand::query()->orderBy('name')->get(['id', 'name'])->toArray() : [],
            'buckets' => $has('bucket') ? CustomersByLastPurchase::BUCKETS : [],
            'cashiers' => $has('cashier') ? User::query()->inCurrentTenant()->orderBy('name')->get(['id', 'name'])->toArray() : [],
            'vendors' => $has('vendor') ? Vendor::query()->orderBy('name')->get(['id', 'name'])->toArray() : [],
            'reasons' => $has('reason') ? StoreSaleReturnRequest::REASONS : [],
            'levels' => $has('level') ? CustomersByArea::LEVELS : [],
        ];
    }

    private function find(string $name): ReportContract
    {
        return $this->registry->find($name) ?? abort(404);
    }
}
