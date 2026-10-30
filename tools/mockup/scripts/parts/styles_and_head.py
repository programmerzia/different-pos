# scripts/parts/styles_and_head.py

HEAD_HTML = """<!DOCTYPE html>
<html lang="en" data-theme="light" data-scheme="teal" data-density="comfortable">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Retail POS — DIFFERENT FASHION</title>

  <!-- Google Fonts: Inter, Hind Siliguri, JetBrains Mono -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">

  <!-- Tailwind CSS CDN -->
  <script src="https://cdn.tailwindcss.com"></script>

  <!-- React 18 & ReactDOM -->
  <script src="https://unpkg.com/react@18.2.0/umd/react.production.min.js"></script>
  <script src="https://unpkg.com/react-dom@18.2.0/umd/react-dom.production.min.js"></script>
  <script src="https://unpkg.com/@babel/standalone/babel.min.js"></script>

  <style>
    /* CSS Variables & Tokens */
    :root {
      --background: #F8FAFC;
      --surface: #FFFFFF;
      --surface-raised: #FFFFFF;
      --surface-sunken: #F1F5F9;
      --foreground: #0F172A;
      --muted-foreground: #475569;
      --subtle-foreground: #64748B;
      --border: #E2E8F0;
      --input: #CBD5E1;
      
      --success: #15803D;
      --success-soft: #DCFCE7;
      --warning: #B45309;
      --warning-soft: #FEF3C7;
      --danger: #B91C1C;
      --danger-soft: #FEE2E2;
      --info: #1D4ED8;
      --info-soft: #DBEAFE;
      --accent: #D97706;
      --accent-foreground: #FFFFFF;

      --money-positive: #15803D;
      --money-negative: #B91C1C;

      --radius-sm: 6px;
      --radius: 10px;
      --radius-lg: 14px;

      /* Scheme variables (Teal default) */
      --primary: #0F766E;
      --primary-hover: #115E59;
      --primary-foreground: #FFFFFF;
      --primary-soft: #CCFBF1;
      --ring: #14B8A6;

      /* Table row height & density */
      --row-height: 44px;
      --table-font: 14px;
      --card-pad: 20px;
    }

    [data-theme="dark"] {
      --background: #0B1120;
      --surface: #111827;
      --surface-raised: #1F2937;
      --surface-sunken: #0F172A;
      --foreground: #F1F5F9;
      --muted-foreground: #94A3B8;
      --subtle-foreground: #64748B;
      --border: #1E293B;
      --input: #334155;
      
      --success: #4ADE80;
      --success-soft: #052E16;
      --warning: #FBBF24;
      --warning-soft: #422006;
      --danger: #F87171;
      --danger-soft: #450A0A;
      --info: #60A5FA;
      --info-soft: #172554;
      --accent: #FBBF24;
      --accent-foreground: #451A03;

      --money-positive: #4ADE80;
      --money-negative: #F87171;
    }

    /* 6 Color Schemes */
    [data-scheme="teal"] {
      --primary: #0F766E;
      --primary-hover: #115E59;
      --primary-soft: #CCFBF1;
      --ring: #14B8A6;
    }
    [data-theme="dark"][data-scheme="teal"] {
      --primary: #14B8A6;
      --primary-hover: #2DD4BF;
      --primary-soft: #042F2E;
      --ring: #2DD4BF;
    }

    [data-scheme="indigo"] {
      --primary: #4338CA;
      --primary-hover: #3730A3;
      --primary-soft: #E0E7FF;
      --ring: #6366F1;
    }
    [data-theme="dark"][data-scheme="indigo"] {
      --primary: #818CF8;
      --primary-hover: #A5B4FC;
      --primary-soft: #1E1B4B;
      --ring: #A5B4FC;
    }

    [data-scheme="violet"] {
      --primary: #6D28D9;
      --primary-hover: #5B21B6;
      --primary-soft: #EDE9FE;
      --ring: #8B5CF6;
    }
    [data-theme="dark"][data-scheme="violet"] {
      --primary: #A78BFA;
      --primary-hover: #C4B5FD;
      --primary-soft: #2E1065;
      --ring: #C4B5FD;
    }

    [data-scheme="rose"] {
      --primary: #BE123C;
      --primary-hover: #9F1239;
      --primary-soft: #FFE4E6;
      --ring: #F43F5E;
    }
    [data-theme="dark"][data-scheme="rose"] {
      --primary: #FB7185;
      --primary-hover: #FDA4AF;
      --primary-soft: #4C0519;
      --ring: #FDA4AF;
    }

    [data-scheme="ocean"] {
      --primary: #0369A1;
      --primary-hover: #075985;
      --primary-soft: #E0F2FE;
      --ring: #0284C7;
    }
    [data-theme="dark"][data-scheme="ocean"] {
      --primary: #38BDF8;
      --primary-hover: #7DD3FC;
      --primary-soft: #082F49;
      --ring: #7DD3FC;
    }

    [data-scheme="graphite"] {
      --primary: #334155;
      --primary-hover: #1E293B;
      --primary-soft: #E2E8F0;
      --ring: #64748B;
    }
    [data-theme="dark"][data-scheme="graphite"] {
      --primary: #CBD5E1;
      --primary-hover: #E2E8F0;
      --primary-soft: #1E293B;
      --ring: #E2E8F0;
    }

    /* Density Switch */
    [data-density="compact"] {
      --row-height: 32px;
      --table-font: 12px;
      --card-pad: 12px;
    }
    [data-density="comfortable"] {
      --row-height: 44px;
      --table-font: 14px;
      --card-pad: 20px;
    }

    /* Global Typography & Rules */
    body {
      font-family: 'Inter', 'Hind Siliguri', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
      background-color: var(--background);
      color: var(--foreground);
      line-height: 1.6;
      user-select: none;
      overflow: hidden;
    }

    .font-mono { font-family: 'JetBrains Mono', monospace; }
    .font-bangla { font-family: 'Hind Siliguri', sans-serif; }
    .num, .money {
      font-family: 'Inter', 'Hind Siliguri', sans-serif !important;
      font-variant-numeric: tabular-nums !important;
      letter-spacing: -0.01em;
    }

    /* Custom Scrollbars */
    ::-webkit-scrollbar { width: 6px; height: 6px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: var(--border); border-radius: 4px; }
    ::-webkit-scrollbar-thumb:hover { background: var(--muted-foreground); }

    /* Focus Ring */
    :focus-visible { outline: 2px solid var(--ring); outline-offset: 2px; }

    /* Keyframe Animations */
    @keyframes flash-primary {
      0% { background-color: var(--primary-soft); }
      100% { background-color: transparent; }
    }
    .animate-flash { animation: flash-primary 0.6s ease-out; }

    @keyframes shake {
      0%, 100% { transform: translateX(0); }
      20%, 60% { transform: translateX(-8px); }
      40%, 80% { transform: translateX(8px); }
    }
    .animate-shake { animation: shake 0.4s ease-in-out; }

    kbd {
      font-family: 'JetBrains Mono', monospace;
      font-size: 11px;
      padding: 1px 5px;
      border-radius: 4px;
      background: var(--surface-sunken);
      border: 1px solid var(--border);
      color: var(--muted-foreground);
      font-weight: 500;
      white-space: nowrap;
    }

    /* Print Isolation Styles: Hide app shell and print ONLY designated .printable-area with 100% color fidelity */
    @media print {
      *, *::before, *::after {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
        color-adjust: exact !important;
      }
      body {
        background: transparent !important;
      }
      body * { visibility: hidden !important; }
      .printable-area, .printable-area *, #printable-card-area, #printable-card-area *, #printable-barcode-batch-area, #printable-barcode-batch-area * {
        visibility: visible !important;
      }
      .printable-area, #printable-card-area, #printable-barcode-batch-area {
        position: absolute !important;
        left: 0 !important;
        top: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
        margin: 0 !important;
        padding: 16px !important;
        z-index: 999999 !important;
        box-shadow: none !important;
        border: none !important;
        background: transparent !important;
      }
      .printable-receipt {
        background: white !important;
        color: black !important;
      }
      .no-print, button, .modal-backdrop, header, aside, .header-bar { display: none !important; }
    }
  </style>
</head>
<body class="h-screen w-screen flex flex-col">
  <div id="root" class="h-full w-full flex flex-col"></div>
"""
