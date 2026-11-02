{{-- Print layout for receipts. $paper: 80 | 58 | a4. Monochrome only. --}}
<!DOCTYPE html>
<html lang="{{ $r['locale'] }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>@yield('title')</title>
<style>
    @if ($paper === 'a4')
    @page { size: A4; margin: 12mm; }
    @else
    @page { size: {{ $paper }}mm auto; margin: 0; }
    @endif
    * { box-sizing: border-box; }
    html, body { margin: 0; padding: 0; color: black; background: white; }
    body { font-family: 'Hind Siliguri', 'Noto Sans Bengali', 'Segoe UI', Arial, sans-serif; line-height: 1.35; }
    .sheet { margin: 0 auto; }
    .paper-80 .sheet { width: 72mm; padding: 3mm 0; font-size: 12px; }
    .paper-58 .sheet { width: 48mm; padding: 2mm 0; font-size: 10px; }
    .paper-a4 .sheet { width: 186mm; font-size: 12px; }
    .center { text-align: center; }
    .right { text-align: right; }
    .num { font-variant-numeric: tabular-nums; white-space: nowrap; }
    .strong { font-weight: 700; }
    .muted { opacity: .8; }
    .small { font-size: .85em; }
    .shop-name { font-size: 1.35em; font-weight: 700; }
    .title { font-weight: 700; text-transform: uppercase; letter-spacing: .05em; margin: 2mm 0; }
    hr { border: 0; border-top: 1px dashed black; margin: 2mm 0; }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 1px 0; vertical-align: top; }
    th { text-align: left; font-weight: 700; }
    .paper-a4 th, .paper-a4 td { padding: 4px 6px; border-bottom: 1px solid black; }
    .paper-a4 .totals td { border: 0; padding: 2px 6px; }
    .row { display: flex; justify-content: space-between; gap: 2mm; }
    .void-stamp { border: 2px solid black; font-weight: 700; font-size: 1.4em; text-align: center; padding: 1mm; margin: 2mm 0; letter-spacing: .2em; }
    .codes { text-align: center; margin-top: 2mm; }
    .codes svg { max-width: 100%; height: auto; }
    .qr svg { width: 26mm; height: 26mm; }
    .paper-58 .qr svg { width: 22mm; height: 22mm; }
    .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 6mm; }
    .box { border: 1px solid black; padding: 3mm; }
    .toolbar { position: sticky; top: 0; display: flex; gap: 8px; flex-wrap: wrap; justify-content: center; padding: 8px; border-bottom: 1px solid black; background: white; font: 14px system-ui, sans-serif; }
    .toolbar a, .toolbar button { min-height: 44px; padding: 0 14px; display: inline-flex; align-items: center; border: 1px solid black; border-radius: 6px; color: black; background: white; text-decoration: none; font: inherit; cursor: pointer; }
    .toolbar [aria-current="page"] { font-weight: 700; text-decoration: underline; }
    .toolbar a:focus-visible, .toolbar button:focus-visible { outline: 3px solid black; outline-offset: 2px; }
    @media print { .toolbar { display: none; } }
</style>
</head>
<body class="paper-{{ $paper }}">
@if (! empty($toolbar))
    <nav class="toolbar" aria-label="{{ __('receipts.paper') }}">
        <button type="button" id="print-now" accesskey="p">{{ __('receipts.print') }}</button>
        @foreach ($toolbar as $link)
            <a href="{{ $link['url'] }}" @if ($link['active']) aria-current="page" @endif>{{ $link['label'] }}</a>
        @endforeach
    </nav>
@endif
<main class="sheet">
@yield('content')
</main>
@if (! empty($toolbar))
<script>
    document.getElementById('print-now').addEventListener('click', function () { window.print(); });
    document.addEventListener('keydown', function (e) { if ((e.ctrlKey || e.metaKey) && e.key === 'p') { return; } if (e.key === 'Escape' && window.opener) { window.close(); } });
    @if (! empty($autoprint)) window.addEventListener('load', function () { window.print(); }); @endif
</script>
@endif
</body>
</html>
