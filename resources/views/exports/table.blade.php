<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<style>
    body { font-family: '{{ \App\Support\Pdf\PdfRenderer::FONT }}'; font-size: 9pt; color: #111827; }
    .head { width: 100%; border-bottom: 1.5px solid #111827; padding-bottom: 6px; margin-bottom: 8px; }
    .head td { vertical-align: top; }
    .logo { width: 56px; }
    .logo img { max-height: 44px; max-width: 52px; }
    .biz { font-size: 13pt; font-weight: bold; }
    .meta { font-size: 8pt; color: #4b5563; }
    .title { font-size: 12pt; font-weight: bold; text-align: right; }
    table.data { width: 100%; border-collapse: collapse; }
    table.data th { background: #f3f4f6; border-bottom: 1px solid #9ca3af; padding: 4px 5px; text-align: left; font-weight: bold; font-size: 8.5pt; }
    table.data td { border-bottom: 0.5px solid #e5e7eb; padding: 3px 5px; }
    table.data .num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    table.data tr.total td { border-top: 1px solid #111827; border-bottom: none; font-weight: bold; background: #f9fafb; }
    .empty { padding: 24px 0; text-align: center; color: #6b7280; }
</style>
</head>
<body>
<table class="head">
    <tr>
        @if ($meta->tenantLogoPath !== null)
            <td class="logo"><img src="{{ $meta->tenantLogoPath }}" alt=""></td>
        @endif
        <td>
            <div class="biz">{{ $meta->tenantName }}</div>
            <div class="meta">
                @if ($meta->branchName !== null){{ $meta->branchName }} · @endif
                {{ __('app.export.generated', ['at' => $generated]) }}
            </div>
        </td>
        <td>
            <div class="title">{{ $meta->title }}</div>
            @if ($meta->period !== null)
                <div class="meta" style="text-align: right">{{ __('app.export.period', ['period' => $meta->period]) }}</div>
            @endif
            <div class="meta" style="text-align: right">{{ __('app.export.rows', ['count' => count($rows)]) }}</div>
        </td>
    </tr>
</table>

@if (count($rows) === 0)
    <div class="empty">{{ __('app.export.empty') }}</div>
@else
<table class="data" autosize="1">
    <thead>
        <tr>
            @foreach ($columns as $column)
                <th @class(['num' => $column->type->isNumeric()])>{{ $column->label }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @foreach ($rows as $row)
            <tr>
                @foreach ($row as $cell)
                    <td @class(['num' => $cell['numeric']])>{{ $cell['text'] }}</td>
                @endforeach
            </tr>
        @endforeach
        @if ($totals !== null)
            <tr class="total">
                @foreach ($totals as $cell)
                    <td @class(['num' => $cell['numeric']])>{{ $cell['text'] }}</td>
                @endforeach
            </tr>
        @endif
    </tbody>
</table>
@endif
</body>
</html>
