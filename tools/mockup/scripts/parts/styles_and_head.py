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
    /* =========================================================================
       DIFFERENT FASHION — HUMAN-CRAFTED DESIGN SYSTEM & DESIGN TOKENS
       Clean, restrained, high-density POS aesthetics inspired by Linear & Stripe
       ========================================================================= */
    :root {
      /* Surfaces & Neutrals (Light Mode) */
      --background: #F8FAFC;
      --surface: #FFFFFF;
      --surface-raised: #FFFFFF;
      --surface-sunken: #F1F5F9;
      --foreground: #0F172A;
      --muted-foreground: #475569;
      --subtle-foreground: #94A3B8;
      --border: #E2E8F0;
      --border-subtle: #F1F5F9;
      --input: #CBD5E1;
      
      /* Semantic Status — High Contrast, Low Saturation */
      --success: #059669;
      --success-soft: rgba(5, 150, 105, 0.08);
      --success-text: #065F46;

      --warning: #D97706;
      --warning-soft: rgba(217, 119, 6, 0.08);
      --warning-text: #92400E;

      --danger: #DC2626;
      --danger-soft: rgba(220, 38, 38, 0.08);
      --danger-text: #991B1B;

      --info: #2563EB;
      --info-soft: rgba(37, 99, 235, 0.08);
      --info-text: #1E40AF;

      --accent: #B45309;
      --accent-foreground: #FFFFFF;

      --money-positive: #059669;
      --money-negative: #DC2626;

      /* Disciplined Geometric Radii */
      --radius-xs: 4px;
      --radius-sm: 6px;
      --radius: 8px;
      --radius-lg: 10px;
      --radius-xl: 12px;

      /* Refined Brand Scheme: Heritage Forest Teal (Default) */
      --primary: #0D6E66;
      --primary-hover: #09544E;
      --primary-foreground: #FFFFFF;
      --primary-soft: rgba(13, 110, 102, 0.08);
      --ring: #0D6E66;

      /* Table density & Ergonomics */
      --row-height: 40px;
      --table-font: 13px;
      --card-pad: 16px;
    }

    [data-theme="dark"] {
      /* Surfaces & Neutrals (Dark Mode — Deep Obsidian Precision) */
      --background: #090D14;
      --surface: #0F1623;
      --surface-raised: #162032;
      --surface-sunken: #0A0F1A;
      --foreground: #F8FAFC;
      --muted-foreground: #94A3B8;
      --subtle-foreground: #64748B;
      --border: #1E293B;
      --border-subtle: #141D2D;
      --input: #26354D;
      
      --success: #10B981;
      --success-soft: rgba(16, 185, 129, 0.14);
      --success-text: #6EE7B7;

      --warning: #F59E0B;
      --warning-soft: rgba(245, 158, 11, 0.14);
      --warning-text: #FCD34D;

      --danger: #EF4444;
      --danger-soft: rgba(239, 68, 68, 0.14);
      --danger-text: #FCA5A5;

      --info: #3B82F6;
      --info-soft: rgba(59, 130, 246, 0.14);
      --info-text: #93C5FD;

      --accent: #F59E0B;
      --accent-foreground: #451A03;

      --money-positive: #10B981;
      --money-negative: #EF4444;
    }

    /* 6 Purpose-Built Refined Color Schemes */
    [data-scheme="teal"] {
      --primary: #0D6E66;
      --primary-hover: #09544E;
      --primary-soft: rgba(13, 110, 102, 0.08);
      --ring: #0D6E66;
    }
    [data-theme="dark"][data-scheme="teal"] {
      --primary: #14B8A6;
      --primary-hover: #2DD4BF;
      --primary-soft: rgba(20, 184, 166, 0.14);
      --ring: #14B8A6;
    }

    [data-scheme="indigo"] {
      --primary: #3730A3;
      --primary-hover: #312E81;
      --primary-soft: rgba(55, 48, 163, 0.08);
      --ring: #4F46E5;
    }
    [data-theme="dark"][data-scheme="indigo"] {
      --primary: #818CF8;
      --primary-hover: #A5B4FC;
      --primary-soft: rgba(129, 140, 248, 0.14);
      --ring: #818CF8;
    }

    [data-scheme="violet"] {
      --primary: #5B21B6;
      --primary-hover: #4C1D95;
      --primary-soft: rgba(91, 33, 182, 0.08);
      --ring: #7C3AED;
    }
    [data-theme="dark"][data-scheme="violet"] {
      --primary: #A78BFA;
      --primary-hover: #C4B5FD;
      --primary-soft: rgba(167, 139, 250, 0.14);
      --ring: #A78BFA;
    }

    [data-scheme="rose"] {
      --primary: #9F1239;
      --primary-hover: #881337;
      --primary-soft: rgba(159, 18, 57, 0.08);
      --ring: #E11D48;
    }
    [data-theme="dark"][data-scheme="rose"] {
      --primary: #FB7185;
      --primary-hover: #FDA4AF;
      --primary-soft: rgba(251, 113, 133, 0.14);
      --ring: #FB7185;
    }

    [data-scheme="ocean"] {
      --primary: #075985;
      --primary-hover: #0C4A6E;
      --primary-soft: rgba(7, 89, 133, 0.08);
      --ring: #0284C7;
    }
    [data-theme="dark"][data-scheme="ocean"] {
      --primary: #38BDF8;
      --primary-hover: #7DD3FC;
      --primary-soft: rgba(56, 189, 248, 0.14);
      --ring: #38BDF8;
    }

    [data-scheme="graphite"] {
      --primary: #1E293B;
      --primary-hover: #0F172A;
      --primary-soft: rgba(30, 41, 59, 0.08);
      --ring: #475569;
    }
    [data-theme="dark"][data-scheme="graphite"] {
      --primary: #CBD5E1;
      --primary-hover: #E2E8F0;
      --primary-soft: rgba(203, 213, 225, 0.14);
      --ring: #CBD5E1;
    }

    /* Density Switch */
    [data-density="compact"] {
      --row-height: 32px;
      --table-font: 12px;
      --card-pad: 10px;
    }
    [data-density="comfortable"] {
      --row-height: 40px;
      --table-font: 13px;
      --card-pad: 16px;
    }

    /* Global Typography & Rules */
    body {
      font-family: 'Inter', 'Hind Siliguri', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
      background-color: var(--background);
      color: var(--foreground);
      line-height: 1.5;
      user-select: none;
      overflow: hidden;
      -webkit-font-smoothing: antialiased;
      -moz-osx-font-smoothing: grayscale;
    }

    h1, h2, h3, h4 {
      letter-spacing: -0.015em;
    }

    .font-mono { font-family: 'JetBrains Mono', monospace; }
    .font-bangla { font-family: 'Hind Siliguri', sans-serif; }
    
    .num, .money {
      font-family: 'Inter', 'Hind Siliguri', sans-serif !important;
      font-variant-numeric: tabular-nums !important;
      letter-spacing: -0.01em;
    }

    /* Custom Hairline Scrollbars */
    ::-webkit-scrollbar { width: 5px; height: 5px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: var(--border); border-radius: 4px; }
    ::-webkit-scrollbar-thumb:hover { background: var(--muted-foreground); }

    /* Focus Ring */
    :focus-visible { outline: 2px solid var(--ring); outline-offset: 1px; }

    /* Tactile Physical Keycap Badge */
    kbd {
      font-family: 'JetBrains Mono', monospace;
      font-size: 10px;
      padding: 1px 5px;
      border-radius: 4px;
      background: var(--surface-sunken);
      border: 1px solid var(--border);
      border-bottom-width: 2px;
      color: var(--muted-foreground);
      font-weight: 600;
      white-space: nowrap;
      box-shadow: 0 1px 0 rgba(0, 0, 0, 0.04);
    }

    /* Tactile Button Depth & Focus */
    .btn-tactile {
      transition: all 0.12s ease-in-out;
    }
    .btn-tactile:active {
      transform: translateY(1px);
    }

    /* Keyframe Animations */
    @keyframes flash-primary {
      0% { background-color: var(--primary-soft); }
      100% { background-color: transparent; }
    }
    .animate-flash { animation: flash-primary 0.6s ease-out; }

    @keyframes shake {
      0%, 100% { transform: translateX(0); }
      20%, 60% { transform: translateX(-6px); }
      40%, 80% { transform: translateX(6px); }
    }
    .animate-shake { animation: shake 0.35s ease-in-out; }

    .card-print-root { display: none; }
    .cp-mark { display: none; }
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

      /* Always show unmasked numbers during physical print */
      .card-number-masked { display: none !important; }
      .card-number-unmasked { display: inline-block !important; }

      /* Card print job: only #card-print-root is rendered, one CR80 (or bleed) page per card side */
      body.print-cards > *:not(#card-print-root),
      body.print-bleed > *:not(#card-print-root) { display: none !important; }
      body.print-cards, body.print-bleed {
        display: block !important;
        width: auto !important;
        height: auto !important;
        overflow: visible !important;
        background: #fff !important;
      }
      body.print-cards #card-print-root, body.print-bleed #card-print-root { display: block !important; }
      #card-print-root, #card-print-root * { visibility: visible !important; }
      #card-print-root .cp-page {
        position: relative;
        width: 85.6mm;
        height: 54mm;
        overflow: hidden;
        margin: 0;
        padding: 0;
        break-after: page;
        page-break-after: always;
      }
      #card-print-root .cp-page:last-child { break-after: auto; page-break-after: auto; }
      body.print-bleed #card-print-root .cp-page { width: 91.6mm; height: 60mm; }
      #card-print-root .cp-page [class*="rounded"] { border-radius: 0 !important; }
      #card-print-root .cp-page [class*="aspect-"] { aspect-ratio: auto !important; height: 100% !important; border: none !important; box-shadow: none !important; }
      #card-print-root .cp-fill, #card-print-root .cp-fill > div, #card-print-root .cp-fill > div > div { width: 100%; height: 100%; }
      #card-print-root .cp-mark { position: absolute; background: #fff; mix-blend-mode: difference; }

      .cr80-page { border-radius: 0 !important; box-shadow: none !important; }
      
      /* A4 10-Up Sheet Page Profile */
      @page a4sheet {
        size: A4 portrait;
        margin: 8mm;
      }
      .a4-sheet-page {
        page: a4sheet;
        width: 194mm !important;
        page-break-after: always;
        break-after: page;
        margin: 0 auto !important;
      }
    }
  </style>
</head>
<body class="h-screen w-screen flex flex-col">
  <div id="root" class="h-full w-full flex flex-col"></div>
"""
