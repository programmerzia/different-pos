# scripts/parts/icons_and_helpers.py

ICONS_AND_HELPERS_JS = """
  <script type="text/babel">
    const { useState, useEffect, useRef, useMemo } = React;

    // Currency & Formatter Helpers (South Asian Digit Grouping: 1,23,450.00)
    function formatMoney(amount, showSymbol = true) {
      if (amount === undefined || amount === null || isNaN(amount)) amount = 0;
      const isNegative = amount < 0;
      const absAmount = Math.abs(amount).toFixed(2);
      const [intPart, decPart] = absAmount.split('.');
      
      let lastThree = intPart.substring(intPart.length - 3);
      let otherNumbers = intPart.substring(0, intPart.length - 3);
      if (otherNumbers !== '') {
        lastThree = ',' + lastThree;
      }
      const formattedInt = otherNumbers.replace(/\\B(?=(\\d{2})+(?!\\d))/g, ",") + lastThree;
      const formatted = `${formattedInt}.${decPart}`;
      
      return `${isNegative ? '−' : ''}${showSymbol ? '৳ ' : ''}${formatted}`;
    }

    // Luhn Check Digit Verification for 8-digit Customer Numbers (#1000 00XX)
    function luhnCheck(numStr) {
      const clean = (numStr || '').replace(/\\D/g, '');
      if (clean.length !== 8) return false;
      const payload = clean.slice(0, 7);
      const cd = parseInt(clean[7], 10);
      let sum = 0;
      for (let i = payload.length - 1; i >= 0; i--) {
        let d = parseInt(payload[i], 10);
        if ((payload.length - 1 - i) % 2 === 0) {
          d *= 2;
          if (d > 9) d -= 9;
        }
        sum += d;
      }
      const expected = ((10 - (sum % 10)) % 10 + 1) % 10;
      return cd === expected;
    }

    // Lucide SVG Icons Component
    // IMPORTANT: All child SVG elements are wrapped inside <g> tags for Babel compatibility
    function Icon({ name, size = 18, className = "" }) {
      const icons = {
        shoppingCart: <g><circle cx="8" cy="21" r="1" /><circle cx="19" cy="21" r="1" /><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12" /></g>,
        shoppingBag: <g><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z" /><line x1="3" y1="6" x2="21" y2="6" /><path d="M16 10a4 4 0 0 1-8 0" /></g>,
        search: <g><circle cx="11" cy="11" r="8" /><path d="m21 21-4.3-4.3" /></g>,
        barcode: <g><path d="M3 5v14 M8 5v14 M12 5v14 M17 5v14 M21 5v14" /></g>,
        check: <g><polyline points="20 6 9 17 4 12" /></g>,
        checkCircle: <g><circle cx="12" cy="12" r="10" /><path d="m9 12 2 2 4-4" /></g>,
        x: <g><line x1="18" y1="6" x2="6" y2="18" /><line x1="6" y1="6" x2="18" y2="18" /></g>,
        user: <g><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2" /><circle cx="12" cy="7" r="4" /></g>,
        users: <g><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /><path d="M22 21v-2a4 4 0 0 0-3-3.87" /><path d="M16 3.13a4 4 0 0 1 0 7.75" /></g>,
        bell: <g><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9" /><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0" /></g>,
        clock: <g><circle cx="12" cy="12" r="10" /><polyline points="12 6 12 12 16 14" /></g>,
        wifi: <g><path d="M12 20h.01" /><path d="M2 8.82a15 15 0 0 1 20 0" /><path d="M5 12.86a10 10 0 0 1 14 0" /><path d="M8.5 16.43a5 5 0 0 1 7 0" /></g>,
        wifiOff: <g><line x1="2" y1="2" x2="22" y2="22" /><path d="M8.5 16.43a5 5 0 0 1 4.5-1.43" /><path d="M2 8.82a15 15 0 0 1 4.18-2.5" /><path d="M10.66 5c4.01-.36 8.14.9 11.34 3.82" /><path d="M15.5 12.86a10 10 0 0 1 3.5 2.14" /></g>,
        refresh: <g><path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8" /><path d="M3 3v5h5" /><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16" /><path d="M16 16h5v5" /></g>,
        palette: <g><circle cx="13.5" cy="6.5" r=".5" fill="currentColor"/><circle cx="17.5" cy="10.5" r=".5" fill="currentColor"/><circle cx="8.5" cy="7.5" r=".5" fill="currentColor"/><circle cx="6.5" cy="12.5" r=".5" fill="currentColor"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.93 0 1.5-.67 1.5-1.5 0-.39-.15-.74-.39-1-.23-.27-.38-.62-.38-1 0-.83.67-1.5 1.5-1.5H16c3.31 0 6-2.69 6-6 0-4.97-4.48-9-10-9z"/></g>,
        chevronDown: <g><polyline points="6 9 12 15 18 9" /></g>,
        chevronRight: <g><polyline points="9 18 15 12 9 6" /></g>,
        chevronUp: <g><polyline points="18 15 12 9 6 15" /></g>,
        plus: <g><line x1="12" y1="5" x2="12" y2="19" /><line x1="5" y1="12" x2="19" y2="12" /></g>,
        plusCircle: <g><circle cx="12" cy="12" r="10" /><line x1="12" y1="8" x2="12" y2="16" /><line x1="8" y1="12" x2="16" y2="12" /></g>,
        minus: <g><line x1="5" y1="12" x2="19" y2="12" /></g>,
        trash: <g><polyline points="3 6 5 6 21 6" /><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" /></g>,
        tag: <g><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z" /><line x1="7" y1="7" x2="7.01" y2="7" /></g>,
        pause: <g><rect x="6" y="4" width="4" height="16" /><rect x="14" y="4" width="4" height="16" /></g>,
        creditCard: <g><rect width="20" height="14" x="2" y="5" rx="2" /><line x1="2" y1="10" x2="22" y2="10" /></g>,
        banknote: <g><rect width="20" height="12" x="2" y="6" rx="2" /><circle cx="12" cy="12" r="2" /><path d="M6 12h.01M18 12h.01" /></g>,
        smartphone: <g><rect width="14" height="20" x="5" y="2" rx="2" ry="2" /><line x1="12" y1="18" x2="12.01" y2="18" /></g>,
        alertCircle: <g><circle cx="12" cy="12" r="10" /><line x1="12" y1="8" x2="12" y2="12" /><line x1="12" y1="16" x2="12.01" y2="16" /></g>,
        alertTriangle: <g><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z" /><line x1="12" y1="9" x2="12" y2="13" /><line x1="12" y1="17" x2="12.01" y2="17" /></g>,
        info: <g><circle cx="12" cy="12" r="10" /><line x1="12" y1="16" x2="12" y2="12" /><line x1="12" y1="8" x2="12.01" y2="8" /></g>,
        helpCircle: <g><circle cx="12" cy="12" r="10" /><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3" /><line x1="12" y1="17" x2="12.01" y2="17" /></g>,
        printer: <g><polyline points="6 9 6 2 18 2 18 9" /><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" /><rect width="12" height="8" x="6" y="14" /></g>,
        receipt: <g><path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1Z" /><line x1="8" y1="7" x2="16" y2="7" /><line x1="8" y1="11" x2="16" y2="11" /><line x1="8" y1="15" x2="12" y2="15" /></g>,
        lock: <g><rect width="18" height="11" x="3" y="11" rx="2" ry="2" /><path d="M7 11V7a5 5 0 0 1 10 0v4" /></g>,
        unlock: <g><rect width="18" height="11" x="3" y="11" rx="2" ry="2" /><path d="M7 11V7a5 5 0 0 1 9.9-1" /></g>,
        command: <g><path d="M18 3a3 3 0 0 0-3 3v12a3 3 0 0 0 3 3 3 3 0 0 0 3-3 3 3 0 0 0-3-3H6a3 3 0 0 0-3 3 3 3 0 0 0 3 3 3 3 0 0 0 3-3V6a3 3 0 0 0-3-3 3 3 0 0 0-3 3 3 3 0 0 0 3 3h12a3 3 0 0 0 3-3 3 3 0 0 0-3-3z" /></g>,
        package: <g><line x1="16.5" y1="9.4" x2="7.5" y2="4.21" /><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" /><polyline points="3.27 6.96 12 12.01 20.73 6.96" /><line x1="12" y1="22.08" x2="12" y2="12" /></g>,
        barChart: <g><line x1="12" y1="20" x2="12" y2="10" /><line x1="18" y1="20" x2="18" y2="4" /><line x1="6" y1="20" x2="6" y2="16" /></g>,
        layers: <g><polygon points="12 2 2 7 12 12 22 7 12 2" /><polyline points="2 17 12 22 22 17" /><polyline points="2 12 12 17 22 12" /></g>,
        settings: <g><circle cx="12" cy="12" r="3" /><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z" /></g>,
        sliders: <g><line x1="4" y1="21" x2="4" y2="14" /><line x1="4" y1="10" x2="4" y2="3" /><line x1="12" y1="21" x2="12" y2="12" /><line x1="12" y1="8" x2="12" y2="3" /><line x1="20" y1="21" x2="20" y2="16" /><line x1="20" y1="12" x2="20" y2="3" /><line x1="1" y1="14" x2="7" y2="14" /><line x1="9" y1="8" x2="15" y2="8" /><line x1="17" y1="16" x2="23" y2="16" /></g>,
        copy: <g><rect width="14" height="14" x="8" y="8" rx="2" ry="2" /><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2" /></g>,
        externalLink: <g><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6" /><polyline points="15 3 21 3 21 9" /><line x1="10" y1="14" x2="21" y2="3" /></g>,
        fileText: <g><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" /><polyline points="14 2 14 8 20 8" /><line x1="16" y1="13" x2="8" y2="13" /><line x1="16" y1="17" x2="8" y2="17" /><polyline points="10 9 9 9 8 9" /></g>,
        idCard: <g><rect width="20" height="14" x="2" y="5" rx="2" /><circle cx="9" cy="12" r="2" /><path d="M15 11h2M15 14h2" /></g>,
        key: <g><path d="m21 2-2 2m-1.5 1.5L16 7l-2-2-1.5 1.5 2 2L13 10l-1.5-1.5-2 2 1.5 1.5L9 14l-1.5-1.5L6 14a6 6 0 1 0 8.5 8.5l9-9Z" /></g>,
        keyboard: <g><rect width="20" height="14" x="2" y="5" rx="2" /><line x1="6" y1="9" x2="6.01" y2="9" /><line x1="10" y1="9" x2="10.01" y2="9" /><line x1="14" y1="9" x2="14.01" y2="9" /><line x1="18" y1="9" x2="18.01" y2="9" /><line x1="6" y1="13" x2="6.01" y2="13" /><line x1="18" y1="13" x2="18.01" y2="13" /><line x1="10" y1="13" x2="14" y2="13" /></g>,
        laptop: <g><rect width="18" height="12" x="3" y="4" rx="2" /><line x1="2" y1="20" x2="22" y2="20" /></g>,
        database: <g><ellipse cx="12" cy="5" rx="9" ry="3" /><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3" /><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5" /></g>,
        shield: <g><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" /></g>,
        shieldCheck: <g><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" /><polyline points="9 12 11 14 15 10" /></g>,
        send: <g><line x1="22" y1="2" x2="11" y2="13" /><polygon points="22 2 15 22 11 13 2 9 22 2" /></g>,
        truck: <g><rect x="1" y="3" width="15" height="13" /><polygon points="16 8 20 8 23 11 23 16 16 16 16 8" /><circle cx="5.5" cy="18.5" r="2.5" /><circle cx="18.5" cy="18.5" r="2.5" /></g>,
        dollarSign: <g><line x1="12" y1="1" x2="12" y2="23" /><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" /></g>,
        arrowRight: <g><line x1="5" y1="12" x2="19" y2="12" /><polyline points="12 5 19 12 12 19" /></g>,
        arrowLeft: <g><line x1="19" y1="12" x2="5" y2="12" /><polyline points="12 19 5 12 12 5" /></g>,
        arrowLeftRight: <g><polyline points="8 4 4 8 8 12" /><polyline points="16 20 20 16 16 12" /><line x1="4" y1="8" x2="20" y2="8" /><line x1="4" y1="16" x2="20" y2="16" /></g>,
        arrowUpRight: <g><line x1="7" y1="17" x2="17" y2="7" /><polyline points="7 7 17 7 17 17" /></g>,
        arrowDownLeft: <g><line x1="17" y1="7" x2="7" y2="17" /><polyline points="17 17 7 17 7 7" /></g>,
        arrowUpDown: <g><path d="m7 15 5 5 5-5"/><path d="m7 9 5-5 5 5"/></g>,
        eye: <g><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z" /><circle cx="12" cy="12" r="3" /></g>,
        eyeOff: <g><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24" /><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68" /><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61" /><line x1="2" y1="2" x2="22" y2="22" /></g>,
        edit: <g><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" /><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" /></g>,
        filter: <g><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3" /></g>,
        calendar: <g><rect x="3" y="4" width="18" height="18" rx="2" ry="2" /><line x1="16" y1="2" x2="16" y2="6" /><line x1="8" y1="2" x2="8" y2="6" /><line x1="3" y1="10" x2="21" y2="10" /></g>,
        phone: <g><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z" /></g>,
        mapPin: <g><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" /><circle cx="12" cy="10" r="3" /></g>,
        percent: <g><line x1="19" y1="5" x2="5" y2="19" /><circle cx="6.5" cy="6.5" r="2.5" /><circle cx="17.5" cy="17.5" r="2.5" /></g>,
        download: <g><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" /><polyline points="7 10 12 15 17 10" /><line x1="12" y1="15" x2="12" y2="3" /></g>,
        columns: <g><path d="M12 3h7a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-7m0-18H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h7m0-18v18" /></g>,
        grid: <g><rect width="7" height="7" x="3" y="3" rx="1.5" /><rect width="7" height="7" x="14" y="3" rx="1.5" /><rect width="7" height="7" x="14" y="14" rx="1.5" /><rect width="7" height="7" x="3" y="14" rx="1.5" /></g>,
        sparkles: <g><path d="m12 3-1.9 5.8a2 2 0 0 1-1.3 1.3L3 12l5.8 1.9a2 2 0 0 1 1.3 1.3L12 21l1.9-5.8a2 2 0 0 1 1.3-1.3L21 12l-5.8-1.9a2 2 0 0 1-1.3-1.3L12 3z" /></g>,
        menu: <g><line x1="4" y1="12" x2="20" y2="12" /><line x1="4" y1="6" x2="20" y2="6" /><line x1="4" y1="18" x2="20" y2="18" /></g>,
        logOut: <g><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" /><polyline points="16 17 21 12 16 7" /><line x1="21" y1="12" x2="9" y2="12" /></g>,
        messageSquare: <g><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" /></g>,
        bank: <g><line x1="3" y1="21" x2="21" y2="21" /><line x1="6" y1="18" x2="6" y2="11" /><line x1="10" y1="18" x2="10" y2="11" /><line x1="14" y1="18" x2="14" y2="11" /><line x1="18" y1="18" x2="18" y2="11" /><polygon points="12 2 20 7 4 7" /></g>,
        building: <g><rect width="16" height="20" x="4" y="2" rx="2" ry="2" /><path d="M9 22v-4h6v4" /><path d="M8 6h.01M16 6h.01M8 10h.01M16 10h.01M8 14h.01M16 14h.01" /></g>,
        history: <g><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8" /><path d="M3 3v5h5" /><polyline points="12 7 12 12 15 15" /></g>,
        trendingUp: <g><polyline points="22 7 13.5 15.5 8.5 10.5 2 17" /><polyline points="16 7 22 7 22 13" /></g>,
        trendingDown: <g><polyline points="22 17 13.5 8.5 8.5 13.5 2 7" /><polyline points="16 17 22 17 22 11" /></g>,
        panjabi: <g><path d="M8 2h8l-1 5a3 3 0 0 1-6 0L8 2z" /><path d="M6 7 3 11l3 2 1-3" /><path d="M18 7l3 4-3 2-1-3" /><path d="M7 11v11h10V11" /><path d="M12 7v6" /></g>,
        saree: <g><path d="M4 4c4 2 8 2 12 0l4 3-2 13H6L4 4z" /><path d="M9 4v16" /><path d="M14 5v15" /></g>,
        shirt: <g><path d="M20.38 3.46 16 2a4 4 0 0 1-8 0L3.62 3.46a2 2 0 0 0-1.34 2.23l.58 3.47a1 1 0 0 0 .99.84H6v10a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V10h2.15a1 1 0 0 0 .99-.84l.58-3.47a2 2 0 0 0-1.34-2.23z" /></g>,
      };

      return (
        <svg
          xmlns="http://www.w3.org/2000/svg"
          width={size}
          height={size}
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          strokeWidth="1.8"
          strokeLinecap="round"
          strokeLinejoin="round"
          className={className}
        >
          {icons[name] || <circle cx="12" cy="12" r="1.5" fill="currentColor" />}
        </svg>
      );
    }

    // Unified Modal Component for ALL overlays, dialogs, drawers, and popovers
    function Modal({
      isOpen,
      onClose,
      title,
      subtitle,
      icon,
      badge,
      size = 'md',
      children,
      footer,
      showClose = true,
      preventBackdropClose = false,
      onDiscardConfirm = false,
      className = '',
      printable = false,
      id = ''
    }) {
      const modalRef = useRef(null);
      const previousActiveElement = useRef(null);

      useEffect(() => {
        if (isOpen) {
          previousActiveElement.current = document.activeElement;
          const timer = setTimeout(() => {
            if (modalRef.current) {
              const focusables = modalRef.current.querySelectorAll(
                'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
              );
              if (focusables.length > 0) {
                const primaryFocus = Array.from(focusables).find(el => el.getAttribute('autofocus') !== null) ||
                                     Array.from(focusables).find(el => el.tagName === 'INPUT' || el.tagName === 'SELECT') ||
                                     focusables[0];
                primaryFocus?.focus();
              }
            }
          }, 40);
          return () => clearTimeout(timer);
        } else if (previousActiveElement.current) {
          const scanInput = document.getElementById('pos-barcode-input');
          if (scanInput && scanInput.offsetParent !== null) {
            scanInput.focus();
          } else {
            try { previousActiveElement.current.focus(); } catch (e) {}
          }
        }
      }, [isOpen]);

      if (!isOpen) return null;

      const sizeMap = {
        sm: 'max-w-sm',
        md: 'max-w-md',
        lg: 'max-w-lg',
        xl: 'max-w-xl',
        '2xl': 'max-w-2xl',
        '3xl': 'max-w-3xl',
        '4xl': 'max-w-4xl',
        drawer: 'max-w-md ml-auto h-full rounded-none',
        full: 'max-w-[95vw] h-[92vh]'
      };

      const handleBackdrop = (e) => {
        if (e.target === e.currentTarget) {
          if (preventBackdropClose && onDiscardConfirm) {
            if (window.confirm("Discard current changes / input?")) {
              onClose();
            }
            return;
          }
          if (!preventBackdropClose) {
            onClose();
          }
        }
      };

      const handleKey = (e) => {
        if (e.key === 'Escape') {
          e.stopPropagation();
          if (preventBackdropClose && onDiscardConfirm) {
            if (window.confirm("Discard current changes / input?")) {
              onClose();
            }
            return;
          }
          onClose();
          return;
        }

        // Focus Trap Cycle
        if (e.key === 'Tab' && modalRef.current) {
          const focusables = Array.from(modalRef.current.querySelectorAll(
            'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
          )).filter(el => el.offsetParent !== null);
          if (focusables.length === 0) return;
          const first = focusables[0];
          const last = focusables[focusables.length - 1];

          if (e.shiftKey && document.activeElement === first) {
            e.preventDefault();
            last.focus();
          } else if (!e.shiftKey && document.activeElement === last) {
            e.preventDefault();
            first.focus();
          }
        }
      };

      return (
        <div
          className={`fixed inset-0 bg-slate-950/60 backdrop-blur-sm flex ${size === 'drawer' ? 'items-stretch justify-end p-0' : 'items-center justify-center p-4'} z-50 animate-in fade-in duration-150`}
          onClick={handleBackdrop}
          onKeyDown={handleKey}
          id={id ? `modal-${id}` : undefined}
        >
          <div
            ref={modalRef}
            role="dialog"
            aria-modal="true"
            className={`w-full ${sizeMap[size] || sizeMap.md} bg-[var(--surface-raised)] border border-[var(--border)] ${size === 'drawer' ? 'rounded-none shadow-2xl h-full' : 'rounded-xl shadow-xl shadow-slate-950/20'} overflow-hidden flex flex-col text-xs animate-in ${size === 'drawer' ? 'slide-in-from-right duration-200' : 'zoom-in-95 duration-150'} ${className} ${printable ? 'printable-area' : ''}`}
          >
            {/* Modal Header */}
            {(title || showClose) && (
              <div className="no-print px-4 py-3 border-b border-[var(--border)] bg-[var(--surface)] flex items-center justify-between shrink-0 select-none">
                <div className="flex items-center gap-2.5">
                  {icon && (
                    <div className="w-6 h-6 rounded-md bg-[var(--primary-soft)] text-[var(--primary)] flex items-center justify-center shrink-0">
                      <Icon name={icon} size={13} />
                    </div>
                  )}
                  <div>
                    <div className="font-semibold text-xs text-[var(--foreground)] flex items-center gap-2">
                      <span>{title}</span>
                      {badge && (
                        <span className="px-1.5 py-0.2 rounded text-[10px] font-mono font-medium bg-[var(--surface-sunken)] border border-[var(--border)] text-[var(--muted-foreground)]">
                          {badge}
                        </span>
                      )}
                    </div>
                    {subtitle && <div className="text-[11px] text-[var(--muted-foreground)] mt-0.5">{subtitle}</div>}
                  </div>
                </div>
                {showClose && (
                  <div className="flex items-center gap-1.5">
                    <kbd className="hidden sm:inline-block text-[9px]">Esc</kbd>
                    <button
                      type="button"
                      onClick={() => {
                        if (preventBackdropClose && onDiscardConfirm) {
                          if (window.confirm("Discard current changes / input?")) {
                            onClose();
                          }
                          return;
                        }
                        onClose();
                      }}
                      className="w-6 h-6 rounded-md flex items-center justify-center text-[var(--muted-foreground)] hover:text-[var(--foreground)] hover:bg-[var(--surface-sunken)] transition-colors cursor-pointer"
                      title="Close (Esc)"
                    >
                      <Icon name="x" size={14} />
                    </button>
                  </div>
                )}
              </div>
            )}

            {/* Modal Body */}
            <div className="flex-1 overflow-y-auto p-4 space-y-3">
              {children}
            </div>

            {/* Modal Footer (Optional) */}
            {footer && (
              <div className="no-print p-3 border-t border-[var(--border)] bg-[var(--surface)] flex items-center justify-end gap-2 shrink-0">
                {footer}
              </div>
            )}
          </div>
        </div>
      );
    }

    // Helper to format customer representation in lists
    // Guarantees: Name + formatted no "1000 0249", never raw Customer ID
    function formatSaleCustomer(sale) {
      if (!sale) return { name: 'Walk-in Customer', no: '' };
      if (sale.customer === 'Walk-in Customer' || sale.customer_no === 'Walk-in') {
        return { name: 'Walk-in Customer', no: '' };
      }
      
      let rawNo = (sale.customer_no || '').replace(/[^0-9]/g, '');
      if (!rawNo && sale.customer && sale.customer.includes('#')) {
        rawNo = sale.customer.replace(/[^0-9]/g, '');
      }

      if (rawNo.length === 8) {
        const formatted = `${rawNo.slice(0, 4)} ${rawNo.slice(4)}`;
        // Try to match with MOCK_CUSTOMERS if available
        if (typeof MOCK_CUSTOMERS !== 'undefined') {
          const cust = MOCK_CUSTOMERS.find(c => c.customer_no === formatted || c.customer_no.split(' ').join('') === rawNo);
          if (cust) {
            return { name: cust.name_en, no: formatted };
          }
        }
        const cleanName = (sale.customer || '').replace(/^Customer[ ]*#?[0-9]+/, '').trim();
        return { name: cleanName || `Customer ${formatted}`, no: formatted };
      }

      if (typeof MOCK_CUSTOMERS !== 'undefined') {
        const custByName = MOCK_CUSTOMERS.find(c => c.name_en === sale.customer);
        if (custByName) {
          return { name: custByName.name_en, no: custByName.customer_no };
        }
      }

      return { name: sale.customer || 'Customer', no: sale.customer_no || '' };
    }

    // Date Preset Filter Helper
    function matchesDatePreset(dateStr, preset, customStart, customEnd) {
      if (!preset || preset === 'All') return true;
      if (!dateStr) return true;

      const cleanStr = dateStr === 'Today' ? '29 Oct 2026' : (dateStr === 'Yesterday' ? '28 Oct 2026' : dateStr);
      const parsed = new Date(cleanStr);
      if (isNaN(parsed.getTime())) return true;

      // Mock dataset baseline anchor date: 29 Oct 2026
      const refDate = new Date('2026-10-29T23:59:59');

      if (preset === 'Today') {
        return cleanStr.includes('29 Oct 2026');
      }
      if (preset === 'Yesterday') {
        return cleanStr.includes('28 Oct 2026');
      }
      if (preset === 'This week') {
        const diffDays = (refDate.getTime() - parsed.getTime()) / (1000 * 60 * 60 * 24);
        return diffDays >= 0 && diffDays <= 7;
      }
      if (preset === 'This month') {
        return cleanStr.includes('Oct 2026');
      }
      if (preset === 'Custom range' && (customStart || customEnd)) {
        if (customStart && parsed < new Date(customStart)) return false;
        if (customEnd && parsed > new Date(customEnd + 'T23:59:59')) return false;
        return true;
      }
      return true;
    }

    // Standard CSV / Excel Exporter
    function exportToCSV(filename, columns, rows) {
      const visibleCols = columns.filter(c => c.id !== '_select' && c.id !== 'actions');
      const headers = visibleCols.map(c => `"${(c.header || c.label || c.id || '').replace(/"/g, '""')}"`).join(',');
      const lines = rows.map(row => {
        return visibleCols.map(c => {
          let val = c.exportValue ? c.exportValue(row) : (c.accessor ? c.accessor(row) : row[c.id]);
          if (typeof val === 'number') return val;
          return `"${(val !== undefined && val !== null ? String(val) : '').replace(/"/g, '""')}"`;
        }).join(',');
      });
      const csvContent = "\uFEFF" + [headers, ...lines].join(String.fromCharCode(10));
      const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
      const url = URL.createObjectURL(blob);
      const link = document.createElement("a");
      link.setAttribute("href", url);
      link.setAttribute("download", filename);
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      URL.revokeObjectURL(url);
    }

    // =========================================================================
    // UNIFIED REUSABLE <MembershipCard> COMPONENT (CR80 ISO 7810 ID-1 SPEC)
    // Features:
    // 1. Luxury Gold/Platinum/Corporate retail privilege card design with anti-counterfeit Guilloche lines
    // 2. High-security card number masking by default (•••• •••• •••• 0017) with instant hover-to-reveal & eye toggle
    // 3. Crisp scannable Code 128 barcode + QR code on the back
    // 4. Smooth 3D flip card interaction
    // 5. Optimized for PVC card printers (Zebra, Evolis) and A4 10-up batch print
    // =========================================================================
    function MembershipCard({
      customer,
      side = 'front', // 'front' | 'back'
      allowFlip = true,
      onFlip,
      forPrint = false,
      isRevealed = false,
      onToggleReveal,
      className = '',
      showActions = false,
      onPrintSingle,
      onReissue,
    }) {
      const [internalSide, setInternalSide] = useState(side);
      const [isHovered, setIsHovered] = useState(false);
      const [localRevealed, setLocalRevealed] = useState(false);

      useEffect(() => {
        setInternalSide(side);
      }, [side]);

      const activeSide = onFlip ? side : internalSide;
      const handleFlip = () => {
        if (!allowFlip) return;
        const nextSide = activeSide === 'front' ? 'back' : 'front';
        if (onFlip) onFlip(nextSide);
        else setInternalSide(nextSide);
      };

      const cust = customer || {
        id: 'c1',
        name_en: 'Rahim Uddin',
        name_bn: 'রহিম উদ্দিন',
        customer_no: '1000 0017',
        group: 'Retail',
        phone: '01711-223344',
        loyalty_points: 1450,
        balance: 0,
      };

      const isVip = (cust.group || '').toLowerCase().includes('vip');
      const isCorporate = (cust.group || '').toLowerCase().includes('corporate');

      const rawNo = (cust.customer_no || '1000 0017').replace(/\\D/g, '');
      const formattedNo = rawNo.length === 8 ? `${rawNo.slice(0, 4)} ${rawNo.slice(4)}` : (cust.customer_no || '1000 0017');
      const maskedNo = `••••  ••••  ••••  ${rawNo.slice(-4)}`;

      // Reveal number if forPrint, or prop isRevealed, or localRevealed, or hovered
      const showPlainNumber = forPrint || isRevealed || localRevealed || isHovered;

      // Tier-based luxury styling
      let theme = {
        tierName: 'GOLD PRIVILEGE',
        tierBn: 'গোল্ড প্রিভিলেজ',
        gradient: 'radial-gradient(135% 135% at 85% 15%, #064e3b 0%, #022c22 55%, #011c15 100%)',
        foilText: 'from-amber-200 via-yellow-100 to-amber-300',
        borderColor: 'border-amber-400/40',
        accentText: 'text-amber-300',
        badgeBg: 'bg-gradient-to-r from-amber-500/20 to-yellow-500/30 border border-amber-400/50 text-amber-200',
        glowColor: 'rgba(245, 158, 11, 0.25)',
      };

      if (isVip) {
        theme = {
          tierName: 'PLATINUM VIP',
          tierBn: 'প্লাটিনাম ভিআইপি',
          gradient: 'radial-gradient(135% 135% at 85% 15%, #27272a 0%, #18181b 55%, #09090b 100%)',
          foilText: 'from-amber-100 via-yellow-200 to-amber-400',
          borderColor: 'border-amber-400/50',
          accentText: 'text-amber-200',
          badgeBg: 'bg-gradient-to-r from-amber-400/25 to-yellow-600/30 border border-amber-300/60 text-amber-100',
          glowColor: 'rgba(217, 119, 6, 0.35)',
        };
      } else if (isCorporate) {
        theme = {
          tierName: 'CORPORATE ELITE',
          tierBn: 'কর্পোরেট এলিট',
          gradient: 'radial-gradient(135% 135% at 85% 15%, #1e3a8a 0%, #0f172a 55%, #020617 100%)',
          foilText: 'from-blue-200 via-sky-100 to-slate-200',
          borderColor: 'border-cyan-400/40',
          accentText: 'text-cyan-300',
          badgeBg: 'bg-gradient-to-r from-cyan-500/20 to-blue-500/30 border border-cyan-400/50 text-cyan-200',
          glowColor: 'rgba(56, 189, 248, 0.25)',
        };
      }

      return (
        <div
          className={`flex flex-col items-center ${className}`}
          onMouseEnter={() => setIsHovered(true)}
          onMouseLeave={() => setIsHovered(false)}
        >
          {/* Card Physical Representation with 3D Flip */}
          <div
            onClick={allowFlip ? handleFlip : undefined}
            style={{
              cursor: allowFlip ? 'pointer' : 'default',
              perspective: '1200px',
            }}
            className="w-full relative group"
            title={allowFlip ? "Click card to flip between Front and Back" : ""}
          >
            {activeSide === 'front' ? (
              /* ================= FRONT SIDE ================= */
              <div
                style={{
                  background: theme.gradient,
                  WebkitPrintColorAdjust: 'exact',
                  printColorAdjust: 'exact',
                  boxShadow: forPrint ? 'none' : `0 16px 32px -8px ${theme.glowColor}, 0 2px 6px rgba(0,0,0,0.5)`,
                }}
                className={`w-full aspect-[85.6/54] rounded-2xl p-4 sm:p-5 flex flex-col justify-between relative overflow-hidden border ${theme.borderColor} select-none transition-all duration-300`}
              >
                {/* Guilloche Anti-Counterfeit Background Pattern */}
                <svg className="absolute inset-0 w-full h-full opacity-10 pointer-events-none" xmlns="http://www.w3.org/2000/svg">
                  <defs>
                    <pattern id={`guilloche-${cust.id || 'card'}`} width="36" height="36" patternUnits="userSpaceOnUse">
                      <path d="M0 18 Q9 0 18 18 T36 18" fill="none" stroke="currentColor" strokeWidth="0.5" className="text-amber-200" />
                      <path d="M0 18 Q9 36 18 18 T36 18" fill="none" stroke="currentColor" strokeWidth="0.5" className="text-amber-200" />
                      <circle cx="18" cy="18" r="9" fill="none" stroke="currentColor" strokeWidth="0.3" className="text-amber-200" />
                    </pattern>
                  </defs>
                  <rect width="100%" height="100%" fill={`url(#guilloche-${cust.id || 'card'})`} />
                </svg>

                {/* Metallic Sheen Flare */}
                <div className="absolute -top-24 -right-24 w-60 h-60 bg-gradient-to-br from-white/15 via-white/5 to-transparent rounded-full blur-2xl pointer-events-none" />

                {/* Header: Brand & Tier Badge */}
                <div className="flex items-start justify-between relative z-10">
                  <div className="space-y-0.5">
                    <div className="flex items-center gap-1.5">
                      <span className="w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse"></span>
                      <span className="font-serif font-black tracking-[0.18em] text-xs sm:text-sm uppercase bg-gradient-to-r from-amber-100 via-amber-200 to-yellow-400 bg-clip-text text-transparent drop-shadow-sm">
                        DIFFERENT FASHION
                      </span>
                    </div>
                    <div className="text-[7.5px] sm:text-[8.5px] tracking-[0.16em] font-semibold text-amber-200/80 uppercase">
                      HAUTE COUTURE · PRIVILEGE CLUB
                    </div>
                  </div>

                  <div className={`px-2 py-0.5 rounded-full ${theme.badgeBg} shadow-xs flex items-center gap-1 backdrop-blur-xs`}>
                    <Icon name="sparkles" size={10} className="text-amber-300" />
                    <span className="font-mono font-black text-[8.5px] sm:text-[9.5px] tracking-wider uppercase">
                      {theme.tierName}
                    </span>
                  </div>
                </div>

                {/* Middle: EMV Microchip & Contactless Waves */}
                <div className="flex items-center justify-between relative z-10 my-auto py-0.5">
                  <div className="flex items-center gap-2.5">
                    {/* Realistic Gold EMV Microchip */}
                    <div className="w-8 h-6 sm:w-9 sm:h-7 rounded-md bg-gradient-to-br from-amber-200 via-amber-400 to-yellow-600 p-0.5 shadow-md border border-amber-200/70 relative">
                      <div className="w-full h-full border border-amber-700/40 rounded-xs flex flex-col justify-between p-0.5">
                        <div className="flex justify-between h-1.5">
                          <div className="w-2 border-r border-amber-800/40"></div>
                          <div className="w-2 border-l border-amber-800/40"></div>
                        </div>
                        <div className="w-full h-1 border-y border-amber-800/40 rounded-xs bg-amber-300/30"></div>
                        <div className="flex justify-between h-1.5">
                          <div className="w-2 border-r border-amber-800/40"></div>
                          <div className="w-2 border-l border-amber-800/40"></div>
                        </div>
                      </div>
                    </div>

                    {/* Contactless Radio Waves */}
                    <svg className="w-4 h-4 sm:w-5 sm:h-5 text-amber-200/80" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round">
                      <path d="M6 9a7 7 0 0 1 0 6" />
                      <path d="M10 6a11 11 0 0 1 0 12" />
                      <path d="M14 3a16 16 0 0 1 0 18" />
                    </svg>
                  </div>

                  {/* Security Hologram Disc */}
                  <div className="w-6 h-6 sm:w-7 sm:h-7 rounded-full bg-gradient-to-tr from-amber-300 via-emerald-300 to-purple-400 p-0.5 shadow-inner opacity-85 border border-white/40 flex items-center justify-center">
                    <span className="text-[6px] font-black text-black/80 font-mono tracking-tighter">DF</span>
                  </div>
                </div>

                {/* Card Number Row: Security Masked by default, unmasks on hover or eye click */}
                <div className="relative z-10 space-y-0.5">
                  <div className="flex items-center justify-between">
                    <span className="text-[7px] sm:text-[7.5px] uppercase tracking-widest font-bold text-amber-200/70">
                      Card Number
                    </span>
                    <div className="flex items-center gap-1.5 no-print">
                      {showPlainNumber ? (
                        <span className="text-[7.5px] sm:text-[8px] font-mono font-semibold px-1.5 py-0.2 rounded bg-emerald-500/20 border border-emerald-400/40 text-emerald-300 flex items-center gap-1 animate-in fade-in duration-150">
                          <Icon name="eye" size={9} />
                          <span>Revealed</span>
                        </span>
                      ) : (
                        <span className="text-[7.5px] sm:text-[8px] font-mono font-semibold px-1.5 py-0.2 rounded bg-white/10 border border-white/20 text-white/70 flex items-center gap-1">
                          <Icon name="lock" size={9} />
                          <span>Hover to reveal</span>
                        </span>
                      )}
                      <button
                        type="button"
                        onClick={(e) => {
                          e.stopPropagation();
                          if (onToggleReveal) onToggleReveal();
                          else setLocalRevealed(prev => !prev);
                        }}
                        className="p-1 rounded hover:bg-white/15 text-white/80 hover:text-white transition-colors cursor-pointer"
                        title={showPlainNumber ? "Click to mask" : "Click to pin unmasked"}
                      >
                        <Icon name={showPlainNumber ? "eyeOff" : "eye"} size={11} />
                      </button>
                    </div>
                  </div>

                  {/* Embossed Card Number Typography */}
                  <div
                    className="font-mono text-sm sm:text-base md:text-lg font-extrabold tracking-[0.2em] text-white drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)] flex items-center gap-2 group/num"
                  >
                    {/* Web view: switches between masked and full */}
                    <span className="no-print font-bold">
                      {showPlainNumber ? formattedNo : maskedNo}
                    </span>
                    {/* Print view: always prints full unmasked */}
                    <span className="hidden print:inline-block font-bold">
                      {formattedNo}
                    </span>
                  </div>

                  {/* Cardholder Information & Validity Footer */}
                  <div className="flex items-end justify-between pt-1 border-t border-white/10">
                    <div>
                      <div className="text-[7px] uppercase tracking-wider text-amber-200/70 font-semibold">Cardholder</div>
                      <div className="text-[11px] sm:text-xs font-black tracking-wide text-white uppercase drop-shadow-xs truncate max-w-[150px] sm:max-w-[190px]">
                        {cust.name_en}
                      </div>
                      <div className="text-[8.5px] sm:text-[9.5px] text-amber-200/80 font-bangla">
                        {cust.name_bn || 'সম্মানিত সদস্য'}
                      </div>
                    </div>

                    <div className="text-right">
                      <div className="text-[7px] uppercase tracking-wider text-amber-200/70 font-semibold">Valid Thru</div>
                      <div className="text-[10px] sm:text-[11px] font-mono font-bold text-white tracking-widest">
                        10/29
                      </div>
                      <div className="text-[7.5px] sm:text-[8px] font-mono text-amber-300/80">
                        PTS: {cust.loyalty_points || 0}
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            ) : (
              /* ================= BACK SIDE ================= */
              <div
                style={{
                  background: '#0f172a',
                  WebkitPrintColorAdjust: 'exact',
                  printColorAdjust: 'exact',
                  boxShadow: forPrint ? 'none' : '0 16px 32px -8px rgba(0,0,0,0.5), 0 2px 6px rgba(0,0,0,0.5)',
                }}
                className={`w-full aspect-[85.6/54] rounded-2xl flex flex-col justify-between relative overflow-hidden border border-slate-700 text-white select-none transition-all duration-300`}
              >
                {/* 3000 Oe HiCo Black Magnetic Stripe */}
                <div className="w-full h-7 sm:h-8 bg-[#111111] mt-2.5 sm:mt-3 border-y border-black shadow-inner flex items-center px-4 justify-between">
                  <span className="text-[6px] font-mono text-white/25 tracking-widest uppercase">
                    3-TRACK HICO 3000 OE MAGNETIC STRIPE
                  </span>
                </div>

                {/* Signature Strip & Security CVV Box */}
                <div className="px-4 py-0.5 space-y-1">
                  <div className="flex items-center gap-2">
                    <div className="flex-1 h-6 sm:h-7 bg-white text-black px-2.5 rounded flex items-center justify-between border border-gray-300 shadow-inner overflow-hidden relative">
                      <div className="absolute inset-0 opacity-10 flex items-center justify-around pointer-events-none text-[5.5px] font-mono font-bold tracking-tighter text-gray-600">
                        <span>DIFFERENT FASHION</span>
                        <span>DIFFERENT FASHION</span>
                      </div>
                      <span className="text-[6.5px] text-gray-500 font-sans uppercase font-bold relative z-10">
                        AUTHORIZED SIGNATURE
                      </span>
                      <span className="font-serif italic text-xs text-gray-900 font-semibold relative z-10">
                        {cust.name_en}
                      </span>
                    </div>

                    <div className="w-9 h-6 sm:h-7 bg-white text-black rounded flex flex-col items-center justify-center border border-gray-300 shadow-inner">
                      <span className="text-[5.5px] text-gray-400 font-mono font-bold">CID</span>
                      <span className="font-mono font-bold text-[10px] sm:text-xs tracking-wider">
                        {rawNo.slice(-3) || '849'}
                      </span>
                    </div>
                  </div>

                  {/* Scannable Code 128 Barcode & QR Code */}
                  <div className="flex items-center justify-between gap-2.5 bg-white p-1.5 sm:p-2 rounded-lg border border-gray-300 text-black shadow-xs">
                    <div className="flex-1 text-center">
                      <svg className="w-full h-6 sm:h-7 mx-auto" viewBox="0 0 180 28" preserveAspectRatio="none">
                        <rect x="0" y="0" width="180" height="28" fill="#ffffff" />
                        <g fill="#000000">
                          <rect x="5" y="0" width="2" height="24" /><rect x="9" y="0" width="1" height="24" /><rect x="13" y="0" width="3" height="24" />
                          <rect x="18" y="0" width="1" height="24" /><rect x="21" y="0" width="2" height="24" /><rect x="25" y="0" width="3" height="24" />
                          <rect x="31" y="0" width="1" height="24" /><rect x="34" y="0" width="1" height="24" /><rect x="38" y="0" width="2" height="24" />
                          <rect x="42" y="0" width="3" height="24" /><rect x="47" y="0" width="1" height="24" /><rect x="50" y="0" width="2" height="24" />
                          <rect x="55" y="0" width="2" height="24" /><rect x="59" y="0" width="3" height="24" /><rect x="64" y="0" width="1" height="24" />
                          <rect x="67" y="0" width="1" height="24" /><rect x="71" y="0" width="2" height="24" /><rect x="75" y="0" width="3" height="24" />
                          <rect x="80" y="0" width="1" height="24" /><rect x="83" y="0" width="1" height="24" /><rect x="87" y="0" width="2" height="24" />
                          <rect x="91" y="0" width="3" height="24" /><rect x="96" y="0" width="1" height="24" /><rect x="99" y="0" width="2" height="24" />
                          <rect x="104" y="0" width="2" height="24" /><rect x="108" y="0" width="3" height="24" /><rect x="113" y="0" width="1" height="24" />
                          <rect x="116" y="0" width="1" height="24" /><rect x="120" y="0" width="2" height="24" /><rect x="124" y="0" width="3" height="24" />
                          <rect x="129" y="0" width="2" height="24" /><rect x="133" y="0" width="1" height="24" /><rect x="137" y="0" width="2" height="24" />
                          <rect x="141" y="0" width="3" height="24" /><rect x="146" y="0" width="1" height="24" /><rect x="149" y="0" width="2" height="24" />
                          <rect x="154" y="0" width="1" height="24" /><rect x="157" y="0" width="3" height="24" /><rect x="162" y="0" width="2" height="24" />
                          <rect x="167" y="0" width="2" height="24" /><rect x="171" y="0" width="3" height="24" /><rect x="176" y="0" width="1" height="24" />
                        </g>
                      </svg>
                      <div className="font-mono text-[7.5px] sm:text-[8.5px] font-bold text-gray-800 tracking-wider">
                        * DF{formattedNo.replace(/\\s+/g, '')} *
                      </div>
                    </div>

                    <div className="w-10 h-10 sm:w-11 sm:h-11 bg-white p-0.5 rounded border border-gray-200 flex flex-col items-center justify-center shrink-0">
                      <svg className="w-8 h-8 sm:w-9 sm:h-9" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8">
                        <rect width="6" height="6" x="3" y="3" rx="1" fill="#000" />
                        <rect width="6" height="6" x="15" y="3" rx="1" fill="#000" />
                        <rect width="6" height="6" x="3" y="15" rx="1" fill="#000" />
                        <rect x="5" y="5" width="2" height="2" fill="#fff" />
                        <rect x="17" y="5" width="2" height="2" fill="#fff" />
                        <rect x="5" y="17" width="2" height="2" fill="#fff" />
                        <path d="M14 14h3v3h-3z M19 14h2v2h-2z M14 19h2v2h-2z M18 18h3v3h-3z M10 8h4v2h-4z M10 14h2v2h-2z" fill="#000" />
                      </svg>
                    </div>
                  </div>
                </div>

                {/* Legal Disclaimer & Location */}
                <div className="px-4 pb-2.5 pt-0.5 space-y-0.5">
                  <div className="text-[6px] sm:text-[6.5px] text-slate-400 leading-tight">
                    1. Non-transferable. Property of Different Fashion Ltd. 2. Scan barcode at checkout to earn/redeem 5% loyalty reward points. 3. Settle dues within 30 days. Report lost cards immediately.
                  </div>
                  <div className="flex items-center justify-between text-[6px] sm:text-[7px] text-amber-200/90 border-t border-slate-800 pt-0.5">
                    <span>Helpline: +880 1711-223344</span>
                    <span>Uttara Flagship · Sector 3</span>
                    <span className="font-mono">BIN: 002847192-0101</span>
                  </div>
                </div>
              </div>
            )}
          </div>

          {/* Under Card Quick Action Controls (When showActions is true and not printing) */}
          {showActions && !forPrint && (
            <div className="w-full mt-2.5 flex items-center justify-between gap-2 px-1 text-xs">
              <button
                type="button"
                onClick={handleFlip}
                className="h-7 px-2.5 rounded-lg bg-[var(--surface-sunken)] hover:bg-[var(--surface-raised)] border border-[var(--border)] text-[var(--foreground)] font-semibold flex items-center gap-1 cursor-pointer transition-colors shadow-2xs"
                title="Flip to opposite side"
              >
                <Icon name="refresh" size={12} />
                <span>Side: {activeSide.toUpperCase()}</span>
              </button>

              <div className="flex items-center gap-1.5">
                {onPrintSingle && (
                  <button
                    type="button"
                    onClick={() => onPrintSingle(cust)}
                    className="h-7 px-2.5 rounded-lg bg-[var(--primary)] text-[var(--primary-foreground)] font-bold flex items-center gap-1 shadow-xs hover:bg-[var(--primary-hover)] cursor-pointer"
                    title="Print single CR80 card"
                  >
                    <Icon name="printer" size={12} />
                    <span>Print Card</span>
                  </button>
                )}
              </div>
            </div>
          )}
        </div>
      );
    }

    // =========================================================================
    // UNIFIED REUSABLE <DataList> COMPONENT (PART 2 SPECIFICATION)
    // Applied to: Sales, Returns, Due Collection, Customers, Products, Stock,
    //             Purchases, Vendors, and Money Entries
    // =========================================================================
    function DataList({
      id = 'datalist',
      title,
      subtitle,
      data = [],
      keyField = 'id',
      columns = [],
      searchPlaceholder = 'Search records... (Press / to focus)',
      searchFields = [],
      dateField = 'date',
      datePresets = ['All', 'Today', 'Yesterday', 'This week', 'This month', 'Custom range'],
      initialDatePreset = 'All',
      filterChips = [],
      moreFilters = [],
      savedViews = [],
      bulkActions = [],
      headerActions = null,
      onRowClick = null,
      onRowContextMenu = null,
      initialSortField = '',
      initialSortDirection = 'none',
      pageSizeOptions = [25, 50, 100],
      initialPageSize = 25,
      emptyIllustration = 'search',
      emptyTitle = 'No records found',
      emptyMessage = 'Try adjusting your search query, date presets, or filters.',
      emptyCta = null,
      loading = false,
      customFilter = null,
      className = ''
    }) {
      const [searchQuery, setSearchQuery] = useState('');
      const [activeDatePreset, setActiveDatePreset] = useState(initialDatePreset);
      const [customStartDate, setCustomStartDate] = useState('');
      const [customEndDate, setCustomEndDate] = useState('');
      const [activeFilters, setActiveFilters] = useState({});
      const [activeSavedView, setActiveSavedView] = useState('default');
      const [sortField, setSortField] = useState(initialSortField);
      const [sortDirection, setSortDirection] = useState(initialSortDirection);
      const [pageSize, setPageSize] = useState(initialPageSize);
      const [currentPage, setCurrentPage] = useState(1);
      const [highlightedIndex, setHighlightedIndex] = useState(0);
      const [selectedIds, setSelectedIds] = useState([]);
      const [visibleColumns, setVisibleColumns] = useState(() =>
        columns.filter(c => c.defaultVisible !== false).map(c => c.id)
      );
      const [showColumnChooser, setShowColumnChooser] = useState(false);
      const [showMoreFiltersDrawer, setShowMoreFiltersDrawer] = useState(false);
      const [showExportMenu, setShowExportMenu] = useState(false);

      const searchInputRef = useRef(null);
      const tableContainerRef = useRef(null);
      const columnChooserRef = useRef(null);
      const exportMenuRef = useRef(null);

      // Keyboard Shortcut: '/' focuses search input globally for this table when visible
      useEffect(() => {
        const handleGlobalSlash = (e) => {
          const tag = document.activeElement?.tagName;
          if (['INPUT', 'TEXTAREA', 'SELECT'].includes(tag)) return;
          if (e.key === '/') {
            e.preventDefault();
            if (searchInputRef.current) {
              searchInputRef.current.focus();
              searchInputRef.current.select();
            }
          }
        };
        window.addEventListener('keydown', handleGlobalSlash);
        return () => window.removeEventListener('keydown', handleGlobalSlash);
      }, []);

      // Close dropdowns on outside click
      useEffect(() => {
        const handleClickOutside = (e) => {
          if (columnChooserRef.current && !columnChooserRef.current.contains(e.target)) {
            setShowColumnChooser(false);
          }
          if (exportMenuRef.current && !exportMenuRef.current.contains(e.target)) {
            setShowExportMenu(false);
          }
        };
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
      }, []);

      // Filter Data
      const filteredData = useMemo(() => {
        return (data || []).filter(item => {
          // 1. Search Query
          if (searchQuery.trim()) {
            const q = searchQuery.toLowerCase().trim();
            let matches = false;
            if (searchFields.length > 0) {
              matches = searchFields.some(field => {
                const val = typeof field === 'function' ? field(item) : item[field];
                return val !== undefined && val !== null && String(val).toLowerCase().includes(q);
              });
            } else {
              matches = Object.values(item).some(val =>
                val !== undefined && val !== null && typeof val !== 'object' && String(val).toLowerCase().includes(q)
              );
            }
            if (!matches) return false;
          }

          // 2. Date Preset
          if (dateField && activeDatePreset !== 'All') {
            const rowDate = item[dateField];
            if (!matchesDatePreset(rowDate, activeDatePreset, customStartDate, customEndDate)) {
              return false;
            }
          }

          // 3. Active Filters (from chips & drawer)
          for (const [key, filterVal] of Object.entries(activeFilters)) {
            if (filterVal !== undefined && filterVal !== null && filterVal !== 'all' && filterVal !== '') {
              const itemVal = item[key];
              if (typeof filterVal === 'boolean') {
                if (Boolean(itemVal) !== filterVal) return false;
              } else if (typeof filterVal === 'string') {
                if (String(itemVal || '').toLowerCase() !== filterVal.toLowerCase()) {
                  // Fallback: check if includes
                  if (!String(itemVal || '').toLowerCase().includes(filterVal.toLowerCase())) {
                    return false;
                  }
                }
              }
            }
          }

          // 4. Custom filter predicate
          if (customFilter) {
            if (!customFilter(item, activeFilters, activeDatePreset, { customStartDate, customEndDate })) {
              return false;
            }
          }

          return true;
        });
      }, [data, searchQuery, searchFields, dateField, activeDatePreset, customStartDate, customEndDate, activeFilters, customFilter]);

      // Sort Data
      const filteredAndSortedData = useMemo(() => {
        if (!sortField || sortDirection === 'none') return filteredData;
        const col = columns.find(c => c.id === sortField);
        return [...filteredData].sort((a, b) => {
          let valA = col && col.accessor ? col.accessor(a) : a[sortField];
          let valB = col && col.accessor ? col.accessor(b) : b[sortField];

          if (valA === undefined || valA === null) valA = '';
          if (valB === undefined || valB === null) valB = '';

          if (typeof valA === 'number' && typeof valB === 'number') {
            return sortDirection === 'asc' ? valA - valB : valB - valA;
          }
          const strA = String(valA).toLowerCase();
          const strB = String(valB).toLowerCase();
          return sortDirection === 'asc' ? strA.localeCompare(strB) : strB.localeCompare(strA);
        });
      }, [filteredData, sortField, sortDirection, columns]);

      // Pagination Calculation
      const totalCount = filteredAndSortedData.length;
      const totalPages = Math.max(1, Math.ceil(totalCount / pageSize));
      const startIndex = (currentPage - 1) * pageSize;
      const paginatedData = useMemo(() => {
        return filteredAndSortedData.slice(startIndex, startIndex + pageSize);
      }, [filteredAndSortedData, startIndex, pageSize]);

      // Visible Column Definitions
      const visibleColDefs = useMemo(() => {
        return columns.filter(c => visibleColumns.includes(c.id));
      }, [columns, visibleColumns]);

      // Active filters count for drawer badge
      const activeFilterCount = useMemo(() => {
        let count = 0;
        Object.entries(activeFilters).forEach(([k, v]) => {
          if (v && v !== 'all' && v !== '') count++;
        });
        if (activeDatePreset !== 'All') count++;
        if (searchQuery.trim()) count++;
        return count;
      }, [activeFilters, activeDatePreset, searchQuery]);

      // Keyboard Row Navigation: Up/Down or J/K, Enter opens, Space/X selects, Shift+F10 context menu
      const handleTableKeyDown = (e) => {
        if (['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target.tagName)) return;

        if (e.key === 'ArrowDown' || e.key === 'j' || e.key === 'J') {
          e.preventDefault();
          setHighlightedIndex(prev => Math.min(paginatedData.length - 1, prev + 1));
          return;
        }
        if (e.key === 'ArrowUp' || e.key === 'k' || e.key === 'K') {
          e.preventDefault();
          setHighlightedIndex(prev => Math.max(0, prev - 1));
          return;
        }
        if (e.key === 'Enter') {
          const activeRow = paginatedData[highlightedIndex];
          if (activeRow && onRowClick) {
            e.preventDefault();
            onRowClick(activeRow);
          }
          return;
        }
        if (e.key === ' ' || e.key === 'x' || e.key === 'X') {
          e.preventDefault();
          const activeRow = paginatedData[highlightedIndex];
          if (activeRow) {
            const idVal = activeRow[keyField];
            setSelectedIds(prev =>
              prev.includes(idVal) ? prev.filter(x => x !== idVal) : [...prev, idVal]
            );
          }
          return;
        }
        if (e.shiftKey && e.key === 'F10') {
          e.preventDefault();
          const activeRow = paginatedData[highlightedIndex];
          if (activeRow && onRowContextMenu) {
            const rowEl = document.getElementById(`${id}-row-${highlightedIndex}`);
            const rect = rowEl?.getBoundingClientRect();
            const simulatedEvent = {
              preventDefault: () => {},
              stopPropagation: () => {},
              clientX: rect ? Math.round(rect.left + 100) : 250,
              clientY: rect ? Math.round(rect.top + 20) : 250
            };
            onRowContextMenu(activeRow, simulatedEvent);
          }
          return;
        }
      };

      const handleHeaderSort = (col) => {
        if (!col.sortable) return;
        if (sortField !== col.id) {
          setSortField(col.id);
          setSortDirection('asc');
        } else if (sortDirection === 'asc') {
          setSortDirection('desc');
        } else if (sortDirection === 'desc') {
          setSortField('');
          setSortDirection('none');
        } else {
          setSortDirection('asc');
        }
      };

      const clearAllFilters = () => {
        setSearchQuery('');
        setActiveFilters({});
        setActiveDatePreset('All');
        setCustomStartDate('');
        setCustomEndDate('');
        setActiveSavedView('default');
        setCurrentPage(1);
        setHighlightedIndex(0);
      };

      return (
        <div className={`flex-1 flex flex-col overflow-hidden p-4 space-y-3 ${className}`}>
          {/* Header Bar */}
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 shrink-0">
            <div>
              <div className="flex items-center gap-2">
                <h1 className="text-lg font-bold text-[var(--foreground)]">{title}</h1>
                <span className="px-2 py-0.5 rounded-full text-[11px] font-mono font-bold bg-[var(--surface-sunken)] border border-[var(--border)] text-[var(--muted-foreground)]">
                  {totalCount}
                </span>
              </div>
              {subtitle && <div className="text-xs text-[var(--muted-foreground)] mt-0.5">{subtitle}</div>}
            </div>

            <div className="flex items-center gap-2">
              {headerActions}

              {/* Column Chooser */}
              <div className="relative" ref={columnChooserRef}>
                <button
                  type="button"
                  onClick={() => setShowColumnChooser(prev => !prev)}
                  className="h-8 px-2.5 rounded-lg bg-[var(--surface)] border border-[var(--border)] hover:border-[var(--primary)] text-xs font-medium flex items-center gap-1.5 text-[var(--muted-foreground)] hover:text-[var(--foreground)] transition-colors cursor-pointer"
                  title="Choose Columns"
                >
                  <Icon name="columns" size={13} />
                  <span className="hidden sm:inline">Columns</span>
                </button>
                {showColumnChooser && (
                  <div className="absolute right-0 top-9 w-52 bg-[var(--surface-raised)] border border-[var(--border)] rounded-xl shadow-2xl z-40 p-2 text-xs space-y-1 animate-in fade-in zoom-in-95 duration-100">
                    <div className="font-semibold text-[11px] text-[var(--muted-foreground)] px-1.5 py-0.5 border-b border-[var(--border)] mb-1 flex items-center justify-between">
                      <span>Visible Columns</span>
                      <button
                        type="button"
                        onClick={() => setVisibleColumns(columns.map(c => c.id))}
                        className="text-[10px] text-[var(--primary)] hover:underline cursor-pointer"
                      >
                        Reset All
                      </button>
                    </div>
                    <div className="max-h-60 overflow-y-auto space-y-0.5">
                      {columns.filter(c => c.id !== '_select' && c.hideable !== false).map(c => {
                        const isChecked = visibleColumns.includes(c.id);
                        return (
                          <label key={c.id} className="flex items-center gap-2 px-1.5 py-1 rounded hover:bg-[var(--surface-sunken)] cursor-pointer select-none">
                            <input
                              type="checkbox"
                              checked={isChecked}
                              onChange={() => {
                                setVisibleColumns(prev =>
                                  isChecked ? prev.filter(colId => colId !== c.id) : [...prev, c.id]
                                );
                              }}
                              className="rounded border-[var(--border)] text-[var(--primary)] focus:ring-[var(--primary)]"
                            />
                            <span className="text-[11px] text-[var(--foreground)] truncate">{c.header || c.label || c.id}</span>
                          </label>
                        );
                      })}
                    </div>
                  </div>
                )}
              </div>

              {/* Export Menu */}
              <div className="relative" ref={exportMenuRef}>
                <button
                  type="button"
                  onClick={() => setShowExportMenu(prev => !prev)}
                  className="h-8 px-2.5 rounded-lg bg-[var(--surface)] border border-[var(--border)] hover:border-[var(--primary)] text-xs font-medium flex items-center gap-1.5 text-[var(--muted-foreground)] hover:text-[var(--foreground)] transition-colors cursor-pointer"
                  title="Export Options"
                >
                  <Icon name="download" size={13} />
                  <span className="hidden sm:inline">Export</span>
                  <Icon name="chevronDown" size={11} />
                </button>
                {showExportMenu && (
                  <div className="absolute right-0 top-9 w-44 bg-[var(--surface-raised)] border border-[var(--border)] rounded-xl shadow-2xl z-40 p-1 text-xs animate-in fade-in zoom-in-95 duration-100">
                    <button
                      type="button"
                      onClick={() => {
                        exportToCSV(`${id}_export_${Date.now()}.csv`, visibleColDefs, filteredAndSortedData);
                        setShowExportMenu(false);
                      }}
                      className="w-full text-left px-2.5 py-1.5 rounded-lg hover:bg-[var(--surface-sunken)] flex items-center gap-2 text-[var(--foreground)] cursor-pointer"
                    >
                      <Icon name="fileText" size={13} className="text-[var(--success)]" />
                      <span>Export Excel (.csv)</span>
                    </button>
                    <button
                      type="button"
                      onClick={() => {
                        exportToCSV(`${id}_data_${Date.now()}.csv`, visibleColDefs, filteredAndSortedData);
                        setShowExportMenu(false);
                      }}
                      className="w-full text-left px-2.5 py-1.5 rounded-lg hover:bg-[var(--surface-sunken)] flex items-center gap-2 text-[var(--foreground)] cursor-pointer"
                    >
                      <Icon name="fileText" size={13} className="text-[var(--info)]" />
                      <span>Export to CSV</span>
                    </button>
                    <button
                      type="button"
                      onClick={() => {
                        setShowExportMenu(false);
                        window.print();
                      }}
                      className="w-full text-left px-2.5 py-1.5 rounded-lg hover:bg-[var(--surface-sunken)] flex items-center gap-2 text-[var(--foreground)] cursor-pointer border-t border-[var(--border)] mt-0.5"
                    >
                      <Icon name="printer" size={13} className="text-[var(--primary)]" />
                      <span>Export to PDF / Print</span>
                    </button>
                  </div>
                )}
              </div>

              {/* Print Action */}
              <button
                type="button"
                onClick={() => window.print()}
                className="h-8 px-2.5 rounded-lg bg-[var(--surface)] border border-[var(--border)] hover:border-[var(--primary)] text-xs font-medium flex items-center gap-1.5 text-[var(--muted-foreground)] hover:text-[var(--foreground)] transition-colors cursor-pointer"
                title="Print List"
              >
                <Icon name="printer" size={13} />
                <span className="hidden sm:inline">Print</span>
              </button>
            </div>
          </div>

          {/* Controls Bar: Search + Filter Chips + Saved Views + More Filters */}
          <div className="p-2.5 rounded-lg bg-[var(--surface)] border border-[var(--border)] flex flex-wrap items-center justify-between gap-2 shrink-0 shadow-xs">
            <div className="flex flex-wrap items-center gap-2 flex-1 min-w-[260px]">
              {/* Search Box */}
              <div className="relative flex-1 min-w-[200px] max-w-sm">
                <input
                  ref={searchInputRef}
                  type="text"
                  value={searchQuery}
                  onChange={(e) => {
                    setSearchQuery(e.target.value);
                    setCurrentPage(1);
                    setHighlightedIndex(0);
                  }}
                  onKeyDown={(e) => {
                    if (e.key === 'Escape') searchInputRef.current?.blur();
                  }}
                  placeholder={searchPlaceholder}
                  className="w-full h-8 pl-8 pr-7 rounded-md bg-[var(--surface-sunken)] border border-[var(--border)] text-xs text-[var(--foreground)] focus:border-[var(--primary)] focus:outline-none"
                />
                <Icon name="search" size={13} className="absolute left-2.5 top-2.5 text-[var(--muted-foreground)] pointer-events-none" />
                {searchQuery ? (
                  <button
                    type="button"
                    onClick={() => {
                      setSearchQuery('');
                      searchInputRef.current?.focus();
                    }}
                    className="absolute right-2 top-2 text-[var(--muted-foreground)] hover:text-[var(--foreground)] cursor-pointer"
                    title="Clear search"
                  >
                    <Icon name="x" size={14} />
                  </button>
                ) : (
                  <kbd className="absolute right-2 top-2 text-[9px] text-[var(--muted-foreground)] font-mono pointer-events-none px-1 rounded bg-[var(--surface)] border border-[var(--border)]">/</kbd>
                )}
              </div>

              {/* Saved Views Dropdown */}
              {savedViews.length > 0 && (
                <div className="flex items-center gap-1">
                  <span className="text-[11px] text-[var(--muted-foreground)] font-medium">View:</span>
                  <select
                    value={activeSavedView}
                    onChange={(e) => {
                      const viewId = e.target.value;
                      setActiveSavedView(viewId);
                      const view = savedViews.find(v => v.id === viewId);
                      if (view) {
                        if (view.filters) setActiveFilters(view.filters);
                        else setActiveFilters({});
                        if (view.datePreset) setActiveDatePreset(view.datePreset);
                        if (view.sortField) {
                          setSortField(view.sortField);
                          setSortDirection(view.sortDirection || 'desc');
                        }
                        if (view.search !== undefined) setSearchQuery(view.search);
                      } else {
                        setActiveFilters({});
                        setActiveDatePreset(initialDatePreset);
                        setSearchQuery('');
                      }
                      setCurrentPage(1);
                      setHighlightedIndex(0);
                    }}
                    className="h-8 px-2 rounded-md bg-[var(--surface-sunken)] border border-[var(--border)] text-xs text-[var(--foreground)] font-medium focus:border-[var(--primary)] cursor-pointer"
                  >
                    {savedViews.map(v => (
                      <option key={v.id} value={v.id}>{v.label}</option>
                    ))}
                  </select>
                </div>
              )}

              {/* Date Presets Selector */}
              {datePresets.length > 0 && (
                <div className="flex items-center gap-1">
                  <span className="text-[11px] text-[var(--muted-foreground)] font-medium">Date:</span>
                  <select
                    value={activeDatePreset}
                    onChange={(e) => {
                      setActiveDatePreset(e.target.value);
                      setCurrentPage(1);
                      setHighlightedIndex(0);
                    }}
                    className="h-8 px-2 rounded-md bg-[var(--surface-sunken)] border border-[var(--border)] text-xs text-[var(--foreground)] font-medium focus:border-[var(--primary)] cursor-pointer"
                  >
                    {datePresets.map(preset => (
                      <option key={preset} value={preset}>{preset}</option>
                    ))}
                  </select>
                  {activeDatePreset === 'Custom range' && (
                    <div className="flex items-center gap-1 ml-1">
                      <input
                        type="date"
                        value={customStartDate}
                        onChange={(e) => { setCustomStartDate(e.target.value); setCurrentPage(1); }}
                        className="h-8 px-2 rounded-md bg-[var(--surface-sunken)] border border-[var(--border)] text-xs text-[var(--foreground)]"
                      />
                      <span className="text-[10px] text-[var(--muted-foreground)]">to</span>
                      <input
                        type="date"
                        value={customEndDate}
                        onChange={(e) => { setCustomEndDate(e.target.value); setCurrentPage(1); }}
                        className="h-8 px-2 rounded-md bg-[var(--surface-sunken)] border border-[var(--border)] text-xs text-[var(--foreground)]"
                      />
                    </div>
                  )}
                </div>
              )}

              {/* Quick Filter Chips */}
              {filterChips.map(chipGroup => (
                <div key={chipGroup.id} className="flex items-center gap-1">
                  {chipGroup.options.map(opt => {
                    const isSelected = (activeFilters[chipGroup.id] || 'all') === opt.value;
                    return (
                      <button
                        key={opt.value}
                        type="button"
                        onClick={() => {
                          setActiveFilters(prev => ({ ...prev, [chipGroup.id]: opt.value }));
                          setCurrentPage(1);
                          setHighlightedIndex(0);
                        }}
                        className={`h-7 px-2.5 rounded-md text-xs font-medium transition-colors cursor-pointer ${
                          isSelected
                            ? 'bg-[var(--primary)] text-[var(--primary-foreground)] font-semibold shadow-xs'
                            : 'bg-[var(--surface-sunken)] text-[var(--muted-foreground)] hover:text-[var(--foreground)]'
                        }`}
                      >
                        {opt.label}
                      </button>
                    );
                  })}
                </div>
              ))}
            </div>

            {/* "More Filters" Button */}
            {moreFilters.length > 0 && (
              <button
                type="button"
                onClick={() => setShowMoreFiltersDrawer(true)}
                className={`h-8 px-3 rounded-md border text-xs font-medium flex items-center gap-1.5 transition-colors cursor-pointer ${
                  activeFilterCount > 0
                    ? 'bg-[var(--primary-soft)] border-[var(--primary)] text-[var(--primary)] font-semibold'
                    : 'bg-[var(--surface-sunken)] border-[var(--border)] text-[var(--muted-foreground)] hover:text-[var(--foreground)]'
                }`}
                title="Open More Filters Drawer"
              >
                <Icon name="filter" size={13} />
                <span>More Filters</span>
                {activeFilterCount > 0 && (
                  <span className="w-4 h-4 rounded-full bg-[var(--primary)] text-white text-[10px] font-bold flex items-center justify-center font-mono">
                    {activeFilterCount}
                  </span>
                )}
              </button>
            )}
          </div>

          {/* Table Container with Keyboard Navigation */}
          <div
            ref={tableContainerRef}
            tabIndex={0}
            onKeyDown={handleTableKeyDown}
            className="flex-1 rounded-lg bg-[var(--surface)] border border-[var(--border)] overflow-hidden flex flex-col shadow-xs focus:outline-none focus:ring-1 focus:ring-[var(--primary)]"
          >
            <div className="flex-1 overflow-y-auto overflow-x-auto">
              <table className="w-full text-left border-collapse text-xs">
                {/* Header */}
                <thead className="bg-[var(--surface-sunken)] sticky top-0 border-b border-[var(--border)] select-none text-[var(--muted-foreground)] font-semibold text-[11px] uppercase tracking-wider z-10">
                  <tr>
                    {/* Row Select Checkbox Column */}
                    <th className="py-2.5 px-3 w-8">
                      <input
                        type="checkbox"
                        checked={paginatedData.length > 0 && paginatedData.every(r => selectedIds.includes(r[keyField]))}
                        onChange={() => {
                          const allSelected = paginatedData.every(r => selectedIds.includes(r[keyField]));
                          if (allSelected) {
                            const pageIds = paginatedData.map(r => r[keyField]);
                            setSelectedIds(prev => prev.filter(id => !pageIds.includes(id)));
                          } else {
                            const newIds = paginatedData.map(r => r[keyField]);
                            setSelectedIds(prev => Array.from(new Set([...prev, ...newIds])));
                          }
                        }}
                        className="rounded border-[var(--border)] text-[var(--primary)] focus:ring-[var(--primary)] cursor-pointer"
                        title="Select All on this page"
                      />
                    </th>

                    {visibleColDefs.map(col => (
                      <th
                        key={col.id}
                        onClick={() => handleHeaderSort(col)}
                        className={`py-2.5 px-3 text-[11px] font-semibold text-[var(--muted-foreground)] select-none ${
                          col.align === 'right' ? 'text-right' : col.align === 'center' ? 'text-center' : 'text-left'
                        } ${col.sortable ? 'cursor-pointer hover:text-[var(--foreground)] hover:bg-[var(--surface)]' : ''}`}
                      >
                        <div className={`flex items-center gap-1 ${
                          col.align === 'right' ? 'justify-end' : col.align === 'center' ? 'justify-center' : 'justify-start'
                        }`}>
                          <span>{col.header || col.label}</span>
                          {col.sortable && (
                            <span className="text-[10px]">
                              {sortField === col.id && sortDirection === 'asc' ? '▲' :
                               sortField === col.id && sortDirection === 'desc' ? '▼' :
                               <span className="text-[var(--muted-foreground)]/40 font-mono">↕</span>}
                            </span>
                          )}
                        </div>
                      </th>
                    ))}
                  </tr>
                </thead>

                {/* Body: Skeleton Loading, Empty State, or Real Rows */}
                {loading ? (
                  <tbody className="divide-y divide-[var(--border)]">
                    {[1, 2, 3, 4, 5].map(i => (
                      <tr key={i} className="animate-pulse">
                        <td className="py-3 px-3"><div className="w-4 h-4 bg-[var(--border)] rounded"></div></td>
                        {visibleColDefs.map(col => (
                          <td key={col.id} className="py-3 px-3">
                            <div className="h-4 bg-[var(--border)] rounded w-3/4"></div>
                          </td>
                        ))}
                      </tr>
                    ))}
                  </tbody>
                ) : paginatedData.length === 0 ? (
                  <tbody>
                    <tr>
                      <td colSpan={visibleColDefs.length + 1} className="py-12 text-center">
                        <div className="flex flex-col items-center justify-center max-w-sm mx-auto space-y-2">
                          <div className="w-12 h-12 rounded-full bg-[var(--surface-sunken)] text-[var(--muted-foreground)] flex items-center justify-center mb-1">
                            <Icon name={emptyIllustration || "search"} size={24} />
                          </div>
                          <div className="font-bold text-sm text-[var(--foreground)]">{emptyTitle}</div>
                          <div className="text-xs text-[var(--muted-foreground)]">{emptyMessage}</div>
                          <button
                            type="button"
                            onClick={() => {
                              if (emptyCta?.action) emptyCta.action();
                              else clearAllFilters();
                            }}
                            className="mt-2 h-7 px-3 rounded-lg bg-[var(--primary-soft)] text-[var(--primary)] hover:bg-[var(--primary)] hover:text-white text-xs font-semibold transition-colors cursor-pointer"
                          >
                            {emptyCta?.label || 'Clear all filters'}
                          </button>
                        </div>
                      </td>
                    </tr>
                  </tbody>
                ) : (
                  <tbody className="divide-y divide-[var(--border)]">
                    {paginatedData.map((row, index) => {
                      const isHighlighted = highlightedIndex === index;
                      const isSelected = selectedIds.includes(row[keyField]);
                      return (
                        <tr
                          id={`${id}-row-${index}`}
                          key={row[keyField] || index}
                          onClick={() => {
                            setHighlightedIndex(index);
                            if (onRowClick) onRowClick(row);
                          }}
                          onContextMenu={(e) => {
                            setHighlightedIndex(index);
                            if (onRowContextMenu) onRowContextMenu(row, e);
                          }}
                          className={`transition-colors cursor-pointer select-none group ${
                            isSelected
                              ? 'bg-[var(--primary-soft)]/25'
                              : isHighlighted
                                ? 'bg-[var(--surface-sunken)] ring-1 ring-inset ring-[var(--primary)]'
                                : 'hover:bg-[var(--surface-sunken)]'
                          }`}
                        >
                          {/* Row Checkbox */}
                          <td className="py-2.5 px-3 w-8" onClick={(e) => e.stopPropagation()}>
                            <input
                              type="checkbox"
                              checked={isSelected}
                              onChange={() => {
                                const idVal = row[keyField];
                                setSelectedIds(prev =>
                                  prev.includes(idVal) ? prev.filter(x => x !== idVal) : [...prev, idVal]
                                );
                              }}
                              className="rounded border-[var(--border)] text-[var(--primary)] focus:ring-[var(--primary)] cursor-pointer"
                            />
                          </td>

                          {visibleColDefs.map(col => {
                            const val = col.accessor ? col.accessor(row) : row[col.id];
                            return (
                              <td
                                key={col.id}
                                className={`py-2.5 px-3 ${col.className || ''} ${
                                  col.align === 'right' ? 'text-right' : col.align === 'center' ? 'text-center' : 'text-left'
                                }`}
                              >
                                {col.cell ? (
                                  col.cell(row)
                                ) : col.type === 'money' ? (
                                  <span className="font-sans font-bold tabular-nums num text-[var(--foreground)]">
                                    {formatMoney(val)}
                                  </span>
                                ) : col.type === 'qty' || col.type === 'number' ? (
                                  <span className="font-mono font-medium num text-[var(--foreground)]">
                                    {val !== undefined && val !== null ? val : '—'}
                                  </span>
                                ) : (
                                  val !== undefined && val !== null ? String(val) : '—'
                                )}
                              </td>
                            );
                          })}
                        </tr>
                      );
                    })}
                  </tbody>
                )}

                {/* TOTALS FOOTER ROW: Mandatory for every money & qty column */}
                <tfoot className="bg-[var(--surface-sunken)] sticky bottom-0 border-t-2 border-[var(--border)] font-bold text-xs select-none z-10">
                  <tr>
                    <td className="py-2.5 px-3"></td>
                    {visibleColDefs.map((col, idx) => {
                      if (col.type === 'money') {
                        const sum = filteredAndSortedData.reduce((acc, row) => {
                          const val = col.accessor ? col.accessor(row) : row[col.id];
                          return acc + (Number(val) || 0);
                        }, 0);
                        return (
                          <td key={col.id} className="py-2.5 px-3 text-right font-sans font-bold tabular-nums num text-[var(--foreground)]">
                            {formatMoney(sum)}
                          </td>
                        );
                      }
                      if (col.type === 'qty' || col.type === 'number') {
                        const sum = filteredAndSortedData.reduce((acc, row) => {
                          const val = col.accessor ? col.accessor(row) : row[col.id];
                          return acc + (Number(val) || 0);
                        }, 0);
                        return (
                          <td key={col.id} className={`py-2.5 px-3 font-mono font-bold num text-[var(--foreground)] ${
                            col.align === 'right' ? 'text-right' : col.align === 'center' ? 'text-center' : 'text-left'
                          }`}>
                            {sum.toLocaleString()}
                          </td>
                        );
                      }
                      if (idx === 0) {
                        return (
                          <td key={col.id} className="py-2.5 px-3 text-[var(--foreground)] font-semibold">
                            Total ({filteredAndSortedData.length})
                          </td>
                        );
                      }
                      return <td key={col.id} className="py-2.5 px-3"></td>;
                    })}
                  </tr>
                </tfoot>
              </table>
            </div>

            {/* Pagination Controls */}
            <div className="p-2.5 px-3 border-t border-[var(--border)] bg-[var(--surface)] flex flex-wrap items-center justify-between gap-2 shrink-0 select-none text-xs">
              <div className="flex items-center gap-2">
                <span className="text-[11px] text-[var(--muted-foreground)]">Rows per page:</span>
                <select
                  value={pageSize}
                  onChange={(e) => {
                    setPageSize(Number(e.target.value));
                    setCurrentPage(1);
                    setHighlightedIndex(0);
                  }}
                  className="h-7 px-2 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs font-medium text-[var(--foreground)] cursor-pointer"
                >
                  {pageSizeOptions.map(size => (
                    <option key={size} value={size}>{size}</option>
                  ))}
                </select>
              </div>

              <div className="flex items-center gap-3">
                <span className="text-[11px] text-[var(--muted-foreground)] font-mono">
                  {filteredAndSortedData.length === 0 ? '0 of 0' : `${startIndex + 1}–${Math.min(startIndex + pageSize, filteredAndSortedData.length)} of ${filteredAndSortedData.length}`}
                </span>
                <div className="flex items-center gap-1">
                  <button
                    type="button"
                    onClick={() => {
                      setCurrentPage(prev => Math.max(1, prev - 1));
                      setHighlightedIndex(0);
                    }}
                    disabled={currentPage <= 1}
                    className="h-7 w-7 rounded-lg border border-[var(--border)] bg-[var(--surface-sunken)] hover:bg-[var(--surface)] disabled:opacity-30 disabled:pointer-events-none flex items-center justify-center text-[var(--foreground)] cursor-pointer transition-colors"
                    title="Previous Page"
                  >
                    ‹
                  </button>
                  <span className="text-[11px] font-semibold text-[var(--foreground)] px-1 font-mono">
                    {currentPage} / {totalPages}
                  </span>
                  <button
                    type="button"
                    onClick={() => {
                      setCurrentPage(prev => Math.min(totalPages, prev + 1));
                      setHighlightedIndex(0);
                    }}
                    disabled={currentPage >= totalPages}
                    className="h-7 w-7 rounded-lg border border-[var(--border)] bg-[var(--surface-sunken)] hover:bg-[var(--surface)] disabled:opacity-30 disabled:pointer-events-none flex items-center justify-center text-[var(--foreground)] cursor-pointer transition-colors"
                    title="Next Page"
                  >
                    ›
                  </button>
                </div>
              </div>
            </div>
          </div>

          {/* Bulk Actions Floating Bar */}
          {selectedIds.length > 0 && (
            <div className="p-2.5 px-4 rounded-xl bg-[var(--surface-raised)] border border-[var(--primary)] shadow-2xl flex items-center justify-between gap-3 animate-in fade-in slide-in-from-bottom-2 duration-150 shrink-0">
              <div className="flex items-center gap-3">
                <span className="w-6 h-6 rounded-full bg-[var(--primary)] text-white text-[11px] font-bold flex items-center justify-center font-mono">
                  {selectedIds.length}
                </span>
                <span className="text-xs font-semibold text-[var(--foreground)]">
                  {selectedIds.length} item{selectedIds.length > 1 ? 's' : ''} selected
                </span>
                <button
                  type="button"
                  onClick={() => setSelectedIds([])}
                  className="text-[11px] text-[var(--muted-foreground)] hover:text-[var(--danger)] hover:underline ml-1 cursor-pointer"
                >
                  Clear selection
                </button>
              </div>
              <div className="flex items-center gap-2">
                {bulkActions.map((action, i) => (
                  <button
                    key={i}
                    type="button"
                    onClick={() => {
                      const selectedRows = filteredAndSortedData.filter(r => selectedIds.includes(r[keyField]));
                      action.action(selectedRows);
                    }}
                    className="h-7 px-3 rounded-lg bg-[var(--primary)] text-[var(--primary-foreground)] hover:bg-[var(--primary-hover)] text-xs font-semibold flex items-center gap-1.5 transition-colors cursor-pointer shadow-xs"
                  >
                    {action.icon && <Icon name={action.icon} size={13} />}
                    <span>{action.label}</span>
                  </button>
                ))}
              </div>
            </div>
          )}

          {/* More Filters Drawer */}
          <Modal
            isOpen={showMoreFiltersDrawer}
            onClose={() => setShowMoreFiltersDrawer(false)}
            title={`More Filters · ${title || 'Records'}`}
            subtitle="Filter by specific attributes, categories, branches, and flags"
            icon="filter"
            size="drawer"
            footer={
              <div className="flex items-center justify-between w-full">
                <button
                  type="button"
                  onClick={() => {
                    setActiveFilters({});
                    setActiveDatePreset('All');
                    setCustomStartDate('');
                    setCustomEndDate('');
                    setCurrentPage(1);
                  }}
                  className="px-3 py-1.5 rounded-lg border border-[var(--border)] text-xs text-[var(--muted-foreground)] hover:text-[var(--danger)] hover:border-[var(--danger)] transition-colors cursor-pointer"
                >
                  Clear All Filters
                </button>
                <button
                  type="button"
                  onClick={() => setShowMoreFiltersDrawer(false)}
                  className="px-4 py-1.5 rounded-lg bg-[var(--primary)] text-[var(--primary-foreground)] text-xs font-semibold hover:bg-[var(--primary-hover)] transition-colors cursor-pointer"
                >
                  Apply Filters
                </button>
              </div>
            }
          >
            <div className="space-y-4">
              {moreFilters.map((filter) => (
                <div key={filter.id} className="space-y-1.5">
                  <label className="font-semibold text-xs text-[var(--foreground)] block">
                    {filter.label}
                  </label>
                  {filter.type === 'select' || !filter.type ? (
                    <select
                      value={activeFilters[filter.id] || 'all'}
                      onChange={(e) => {
                        setActiveFilters(prev => ({ ...prev, [filter.id]: e.target.value }));
                        setCurrentPage(1);
                      }}
                      className="w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs text-[var(--foreground)] font-medium focus:border-[var(--primary)]"
                    >
                      {(filter.options || []).map((opt) => (
                        <option key={opt.value} value={opt.value}>
                          {opt.label}
                        </option>
                      ))}
                    </select>
                  ) : filter.type === 'radio' ? (
                    <div className="space-y-1">
                      {(filter.options || []).map((opt) => (
                        <label key={opt.value} className="flex items-center gap-2 text-xs text-[var(--foreground)] cursor-pointer">
                          <input
                            type="radio"
                            name={filter.id}
                            value={opt.value}
                            checked={(activeFilters[filter.id] || 'all') === opt.value}
                            onChange={(e) => {
                              setActiveFilters(prev => ({ ...prev, [filter.id]: e.target.value }));
                              setCurrentPage(1);
                            }}
                            className="text-[var(--primary)] focus:ring-[var(--primary)]"
                          />
                          <span>{opt.label}</span>
                        </label>
                      ))}
                    </div>
                  ) : null}
                </div>
              ))}
            </div>
          </Modal>
        </div>
      );
    }

"""
