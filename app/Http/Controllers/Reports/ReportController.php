<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ReportRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\User;
use App\Reports\CustomersByLastPurchase;
use App\Reports\ReportContract;
use App\Reports\ReportCsv;
use App\Reports\ReportRegistry;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
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
            'options' => [
                'categories' => in_array('category', $definition->filters(), true) ? Category::query()->orderBy('name')->get(['id', 'name'])->toArray() : [],
                'brands' => in_array('brand', $definition->filters(), true) ? Brand::query()->orderBy('name')->get(['id', 'name'])->toArray() : [],
                'buckets' => in_array('bucket', $definition->filters(), true) ? CustomersByLastPurchase::BUCKETS : [],
            ],
        ]);
    }

    public function export(ReportRequest $request, string $report): StreamedResponse
    {
        Gate::authorize('reports.export');
        $definition = $this->find($report);
        $parameters = $request->parameters($definition);

        $result = $definition->run($parameters);
        if (! $parameters->canProfit) {
            $result = $result->withoutProfit();
        }

        $name = $definition->name().($definition->usesPeriod() ? "-{$parameters->fromDate}_{$parameters->toDate}" : '-'.$parameters->today()->format('Y-m-d'));

        return ReportCsv::stream($result, $name.'.csv');
    }

    private function find(string $name): ReportContract
    {
        return $this->registry->find($name) ?? abort(404);
    }
}
