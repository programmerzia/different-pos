import type { Manual } from './types';

/**
 * English user manual. Every statement here describes behaviour that exists in the app today; features
 * planned for the next phase are listed only in the last chapter. Keep `bn.ts` in step (same ids, same
 * block structure: `manual.test.ts` checks it).
 */
export const en: Manual = {
    describes: 'online register release (November 2026)',
    chapters: [
        {
            id: 'start',
            title: 'Getting started',
            summary:
                'Signing in, choosing a branch, language and the screen layout.',
            sections: [
                {
                    id: 'login',
                    title: 'Signing in',
                    blocks: [
                        {
                            type: 'steps',
                            items: [
                                'Open the app address in Chrome or Edge and type your **email, username or phone number**.',
                                'If you sign in with a username or phone, also type the **business code** your administrator gave you. The browser remembers it: next time you see the business name and logo with a **Change** link.',
                                'Type your password and press [[Enter]]. Tick **Remember me** on your own device only.',
                            ],
                        },
                        {
                            type: 'bullets',
                            items: [
                                'The same error appears for a wrong business code, user or password, so nobody can tell which part was wrong.',
                                'After **5 failed attempts in one minute** the form locks and shows a countdown ("Try again in N s").',
                                'A deactivated account cannot sign in: ask an administrator.',
                                'If your account was moved from the old system, you sign in once with your old password and must then **choose a new password** before anything else opens.',
                                'If two-factor authentication is on for your account, the app asks for the code from your authenticator app, or a recovery code.',
                                'Forgot your password? Use **Forgot your password?** on the sign-in page (needs an email on your account).',
                            ],
                        },
                    ],
                },
                {
                    id: 'branch',
                    title: 'Choosing a branch',
                    blocks: [
                        {
                            type: 'p',
                            text: 'Everything you do (sales, stock, dues) belongs to the branch you are working in. Owners who can switch branches see the branch list after sign-in: use [[↑]] [[↓]] and [[Enter]], or press [[1]] to [[9]]. The last used branch is listed first. Other staff go straight to their own branch and see its name in the top bar.',
                        },
                        {
                            type: 'note',
                            tone: 'tip',
                            text: 'Owners can switch at any time with [[Alt+B]] or the branch switcher in the top bar. The switcher appears only when the business has at least two branches.',
                        },
                    ],
                },
                {
                    id: 'language',
                    title: 'Language, numbers and appearance',
                    blocks: [
                        {
                            type: 'bullets',
                            items: [
                                '**Language:** press [[Alt+L]] anywhere (even inside a field or a dialog) to switch between English and বাংলা. The choice is saved to your account.',
                                '**Bangla digits:** turn on "Show numbers in Bangla digits" under Profile → Appearance to see amounts as ৳১,২৫০.',
                                '**Theme:** [[Alt+T]] opens the appearance popover: Light / Dark / System, six colour schemes (Teal, Indigo, Violet, Rose, Ocean, Graphite) and Comfortable / Compact density.',
                            ],
                        },
                    ],
                },
                {
                    id: 'layout',
                    title: 'The screen',
                    blocks: [
                        {
                            type: 'bullets',
                            items: [
                                '**Sidebar (left):** your role and branch at the top, then the menu groups Sell · Customers · Catalog & Stock · Insights · Admin & System. You only see pages you have permission for. [[Ctrl+B]] collapses it to icons. The footer has this **User manual** and the **Shortcuts guide**.',
                                "**Top bar:** branch, the search / command palette ([[Ctrl+K]]), the Online / Offline pill, the Today ticker (today's net sales and invoice count, if you may see the dashboard), notifications ([[Alt+N]]), the clock, language, theme and your account menu.",
                                '**Status bar (bottom, desktop only):** branch, user and role, connection state, the Shortcuts button and the version.',
                                '**On a phone or tablet** the sidebar becomes bottom tabs: Home · POS · Sales · Stock · More.',
                            ],
                        },
                        {
                            type: 'note',
                            tone: 'warning',
                            text: 'The Online / Offline pill only shows whether your browser has a connection. In this release the register works online only: if the connection drops, finish the sale after it returns (see the chapter "What is not here yet").',
                        },
                    ],
                },
                {
                    id: 'palette',
                    title: 'Search and command palette',
                    blocks: [
                        {
                            type: 'p',
                            text: 'Press [[Ctrl+K]] and start typing. The palette finds pages ("Due collection"), actions ("New sale", "Receive purchase", "Switch language") and recently opened items. Use [[↑]] [[↓]] and [[Enter]]. Every keyboard shortcut in the app is also listed in the palette.',
                        },
                    ],
                },
                {
                    id: 'context-menu',
                    title: 'Right-click menus',
                    blocks: [
                        {
                            type: 'p',
                            text: 'Right-click any table row, product tile, bill line or sidebar item for its actions (Open, Edit, Copy, Return, Collect due…). The same menu opens from the keyboard with [[Shift+F10]] on the focused row, and with a long press on touch screens. Actions you lack permission for are simply not shown. Inside text fields the normal browser menu (copy / paste) stays.',
                        },
                    ],
                },
                {
                    id: 'account',
                    title: 'Your account',
                    blocks: [
                        {
                            type: 'bullets',
                            items: [
                                '**Profile:** click your name in the top bar → Profile & settings to change your name and email.',
                                '**Security:** change your password (it asks for the current one), set up two-factor authentication with recovery codes, or add a passkey (fingerprint / face / security key) for password-free sign-in.',
                                '**Log out** from the same menu. Close the browser on shared counters when you leave.',
                            ],
                        },
                    ],
                },
            ],
        },
        {
            id: 'keyboard',
            title: 'Keyboard shortcuts',
            summary: 'The app is designed to be used without a mouse.',
            sections: [
                {
                    id: 'rules',
                    title: 'How shortcuts work',
                    blocks: [
                        {
                            type: 'bullets',
                            items: [
                                'Single-letter keys (such as [[N]] or [[/]]) never fire while you are typing in a field. Function keys, [[Esc]], [[Enter]] and [[Ctrl]] / [[Alt]] combinations always work.',
                                '"[[G S]]" means press [[G]], then [[S]] (a chord), not together.',
                                'Press [[?]] on any page to see exactly which shortcuts work there.',
                            ],
                        },
                    ],
                },
                {
                    id: 'global',
                    title: 'Everywhere',
                    blocks: [
                        {
                            type: 'keys',
                            rows: [
                                {
                                    keys: 'mod+k',
                                    label: 'Search / command palette',
                                },
                                {
                                    keys: 'f1',
                                    label: 'Go to the POS register (on the register: new sale)',
                                },
                                { keys: 'g h', label: 'Open this user manual' },
                                { keys: 'g d', label: 'Dashboard' },
                                { keys: 'g s', label: 'Sales history' },
                                { keys: 'g t', label: 'Returns & exchange' },
                                { keys: 'g u', label: 'Due collection' },
                                { keys: 'g c', label: 'Customers' },
                                { keys: 'g a', label: 'Customer statement' },
                                { keys: 'g w', label: 'WhatsApp & SMS' },
                                { keys: 'g i', label: 'Products & SKUs' },
                                { keys: 'g k', label: 'Stock & transfer' },
                                { keys: 'g p', label: 'Purchases (GRN)' },
                                { keys: 'g v', label: 'Vendor directory' },
                                { keys: 'g r', label: 'Reports' },
                                { keys: 'g ,', label: 'Settings' },
                                { keys: 'alt+l', label: 'Switch language' },
                                { keys: 'alt+t', label: 'Appearance' },
                                {
                                    keys: 'alt+b',
                                    label: 'Switch branch (owner)',
                                },
                                { keys: 'alt+n', label: 'Notifications' },
                                {
                                    keys: 'mod+b',
                                    label: 'Collapse / expand the sidebar',
                                },
                                { keys: 'alt+arrowleft', label: 'Back' },
                                {
                                    keys: 'shift+f10',
                                    label: 'Context menu for the focused item',
                                },
                                { keys: '?', label: 'Shortcuts on this page' },
                            ],
                        },
                    ],
                },
                {
                    id: 'lists',
                    title: 'On list pages',
                    blocks: [
                        {
                            type: 'keys',
                            rows: [
                                { keys: '/', label: 'Focus the search box' },
                                { keys: 'n', label: 'New record' },
                                {
                                    keys: 'alt+arrowdown',
                                    label: 'Jump into the list (first row)',
                                },
                                {
                                    keys: 'arrowup',
                                    label: 'Previous row ([[K]] also works)',
                                },
                                {
                                    keys: 'arrowdown',
                                    label: 'Next row ([[J]] also works)',
                                },
                                { keys: 'enter', label: 'Open the row' },
                                {
                                    keys: 'space',
                                    label: 'Tick / untick the row',
                                },
                                { keys: 'e', label: 'Edit the row' },
                                {
                                    keys: 'delete',
                                    label: 'Delete / deactivate the row',
                                },
                                { keys: '[', label: 'Previous page' },
                                { keys: ']', label: 'Next page' },
                                {
                                    keys: 'mod+s',
                                    label: 'Save a form ([[Ctrl+Enter]] also works)',
                                },
                                {
                                    keys: 'escape',
                                    label: 'Close the dialog / clear the selection',
                                },
                            ],
                        },
                    ],
                },
                {
                    id: 'pos-keys',
                    title: 'At the register',
                    blocks: [
                        {
                            type: 'keys',
                            rows: [
                                { keys: 'f1', label: 'New sale' },
                                { keys: 'f2', label: 'Scan / search field' },
                                {
                                    keys: 'alt+g',
                                    label: 'Browse the catalogue grid',
                                },
                                {
                                    keys: 'f3',
                                    label: 'Quantity of the selected line',
                                },
                                { keys: 'f4', label: 'Attach a customer' },
                                {
                                    keys: 'f5',
                                    label: 'Price check (adds nothing to the bill)',
                                },
                                { keys: 'f6', label: 'Line discount' },
                                { keys: 'shift+f6', label: 'Bill discount' },
                                { keys: 'f7', label: 'Today at this register' },
                                { keys: 'f8', label: 'Hold the bill' },
                                { keys: 'shift+f8', label: 'Held bills' },
                                { keys: 'f9', label: 'Pay' },
                                { keys: 'f11', label: 'Returns & exchange' },
                                {
                                    keys: 'mod+d',
                                    label: "Collect the attached customer's due",
                                },
                                {
                                    keys: 'mod+p',
                                    label: 'Reprint the last receipt',
                                },
                                {
                                    keys: 'arrowup',
                                    label: 'Previous bill line',
                                },
                                { keys: 'arrowdown', label: 'Next bill line' },
                                {
                                    keys: 'delete',
                                    label: 'Remove the selected line',
                                },
                                {
                                    keys: 'escape',
                                    label: 'Step back: search → catalogue → clear the bill',
                                },
                            ],
                        },
                    ],
                },
            ],
        },
        {
            id: 'pos',
            title: 'POS register',
            summary:
                'Making a sale: scan, customer, discounts, payment, receipt.',
            permission: 'pos.sell',
            navIds: ['pos'],
            sections: [
                {
                    id: 'overview',
                    title: 'The register screen',
                    blocks: [
                        {
                            type: 'p',
                            text: 'Press [[F1]] from anywhere. The bill is on the left: the scan field at the top, then the bill lines, then the function bar (Hold, Held list, Customer, Line disc, Order disc, Collect due, Return / Exch, Clear). On the right: the customer strip, held-bill banner, the totals breakdown and the **PAY** bar. Pick your register (R1, R2…) in the header once: the browser remembers it per branch.',
                        },
                    ],
                },
                {
                    id: 'add-items',
                    title: 'Adding items',
                    blocks: [
                        {
                            type: 'bullets',
                            items: [
                                '**Scan** a barcode, or type a barcode or SKU and press [[Enter]]: an exact match is added at once.',
                                '**Type a name** (English or Bangla): up to 8 matches drop down. [[↑]] [[↓]] then [[Enter]] adds one; "Show all in the catalogue" opens the grid.',
                                '**Browse** with [[Alt+G]]: category chips and product tiles; arrow keys move, [[Enter]] adds, [[Esc]] goes back to the bill.',
                                'A product with **sizes or colours** opens a picker where you can enter a quantity per variant and press "Add to bill".',
                                'Scanning the same item again increases its quantity. [[F3]] changes the quantity of the selected line; [[Del]] removes it.',
                                'If the branch does not allow selling below zero stock, you see "Not enough stock of … : N left" and the item is not added.',
                            ],
                        },
                        {
                            type: 'note',
                            tone: 'tip',
                            text: '[[F5]] is a **price check**: scan or search a product to see its price and stock without adding it to the bill.',
                        },
                    ],
                },
                {
                    id: 'customer',
                    title: 'Attaching a customer',
                    blocks: [
                        {
                            type: 'p',
                            text: 'Press [[F4]] and type at least 2 characters: a **customer number** (with or without #; 8 digits finds exactly, 4 to 7 digits finds by prefix), the **last digits of a phone** (at least 3), or a **name or shop name** in English or Bangla. Only active customers appear. "Walk-in (remove customer)" detaches the customer again.',
                        },
                        {
                            type: 'bullets',
                            items: [
                                "A named customer is needed for a **due** sale and for paying with the customer's **advance** (customer credit).",
                                'The customer strip shows the balance; [[Ctrl+D]] opens their due collection page when they owe money.',
                                'A named customer with a mobile number receives the **"Sale completed" SMS** automatically when that template is switched on.',
                            ],
                        },
                    ],
                },
                {
                    id: 'discounts',
                    title: 'Discounts',
                    blocks: [
                        {
                            type: 'bullets',
                            items: [
                                '[[F6]] gives a **line discount** on the selected item, [[Shift+F6]] a **bill discount**. Each can be a percentage or an amount.',
                                'A customer group discount is applied automatically and does not count towards your cap.',
                                'Every role has a **highest discount (%)**. Above it the register asks for a **manager approval**: a manager types their login and password or staff PIN. The approval lasts 120 seconds and is written to the audit log.',
                                "Order of calculation: line discount, then group discount, then bill discount spread over the lines, then VAT, then the branch's cash rounding.",
                            ],
                        },
                    ],
                },
                {
                    id: 'pay',
                    title: 'Taking payment',
                    blocks: [
                        {
                            type: 'steps',
                            items: [
                                'Press [[F9]] (or click **PAY**). The panel shows one row per active payment method; cash is pre-filled with the exact amount.',
                                'Jump to a method with [[Alt+C]] cash, [[Alt+K]] card, [[Alt+B]] bKash, [[Alt+N]] Nagad, [[Alt+R]] Rocket, [[Alt+A]] bank. Keys [[1]] [[2]] [[3]] add ৳100 / ৳500 / ৳1000 to cash; [[Alt+E]] sets exact cash.',
                                'Methods that need a **reference / TrxID** (for example bKash) will not complete without one.',
                                'Leave part of the total unpaid and press [[Alt+D]] "Rest as due" to record a due (named customer only). The panel shows the customer\'s balance after the sale.',
                                'Press [[F10]] or [[Enter]]: **Complete Sale**, **Complete Sale & Print Receipt** (when the branch prints automatically) or **Confirm Partial / Due Sale**.',
                            ],
                        },
                        {
                            type: 'bullets',
                            items: [
                                '**Change** is always given from cash.',
                                '**Customer credit** appears as a method only when the attached customer has an advance, and is capped at that advance.',
                                'A due that takes the customer **over their credit limit** (the customer\'s own limit, else the business default) needs a manager approval ("Ask a manager").',
                                'Selling **below cost** is refused unless a manager with that permission approves it.',
                                "If the server's total differs from the screen, the sale is still saved and **flagged** for review; you see a warning.",
                            ],
                        },
                    ],
                },
                {
                    id: 'after',
                    title: 'After the sale',
                    blocks: [
                        {
                            type: 'bullets',
                            items: [
                                'The **Sale completed** card shows Total, Received, Change and Due with **Print receipt** and **New sale** ([[F1]]).',
                                'The receipt opens in a new tab at the branch paper size (80 mm or 58 mm) and prints through the browser dialog. [[Ctrl+P]] reprints the last receipt of this session. **Delivery chalan** ([[Alt+C]]) opens an A4 delivery note for the same sale: CH-invoice number, items and quantities with Vehicle, Driver and signature lines, and no prices or payments.',
                                'A completed sale cannot be edited. Same-day mistakes are **voided** from Sales history; later ones are handled as a **return**.',
                            ],
                        },
                    ],
                },
                {
                    id: 'hold',
                    title: 'Holding a bill',
                    blocks: [
                        {
                            type: 'p',
                            text: 'Press [[F8]] to park the current bill and start another. [[Shift+F8]] lists held bills: [[Enter]] resumes one (the bill you were working on is held in its place), [[Del]] discards it. Held bills live only in this browser, for this branch and register: they are not visible on another computer.',
                        },
                    ],
                },
                {
                    id: 'today',
                    title: 'Today at this register',
                    blocks: [
                        {
                            type: 'p',
                            text: '[[F7]] shows the business day so far: number of sales, total, received, due and the latest sales of the selected register (or your own sales when no register is selected).',
                        },
                    ],
                },
            ],
        },
        {
            id: 'sales',
            title: 'Sales history',
            summary: 'Finding an invoice, reprinting, sharing, voiding.',
            permission: 'sales.view',
            navIds: ['sales'],
            sections: [
                {
                    id: 'find',
                    title: 'Finding a sale',
                    blocks: [
                        {
                            type: 'bullets',
                            items: [
                                'The list shows completed and voided sales of **this branch**. Date presets: Today, Yesterday, This week (Saturday to today), This month, or a custom range.',
                                'Status chips: Paid, Partial, Due, Void. Views: Unpaid & due invoices, Flagged sync.',
                                '[[/]] searches by invoice number, customer, shop or phone. More filters ([[Shift+F]]): cashier, has return / exchange.',
                                '[[Shift+E]] exports the current page as CSV; [[Shift+R]] starts a new return.',
                            ],
                        },
                    ],
                },
                {
                    id: 'invoice',
                    title: 'The invoice dialog',
                    blocks: [
                        {
                            type: 'p',
                            text: 'Open a row ([[Enter]]) to see items, discounts, VAT, payments, the customer, linked returns, SMS status and the activity log. Actions, each with its key:',
                        },
                        {
                            type: 'keys',
                            rows: [
                                {
                                    keys: 'p',
                                    label: 'Print the 80 mm receipt ([[Alt+P]] 58 mm, [[Shift+P]] A4)',
                                },
                                {
                                    keys: 'd',
                                    label: 'Print the delivery chalan (A4, quantities only, no prices) to send with the goods',
                                },
                                {
                                    keys: 'r',
                                    label: 'Return items from this sale',
                                },
                                { keys: 'x', label: 'Exchange items' },
                                {
                                    keys: 'c',
                                    label: 'Collect the due (named customer with a due)',
                                },
                                {
                                    keys: 's',
                                    label: 'Copy the public receipt link',
                                },
                                {
                                    keys: 'm',
                                    label: 'Resend the receipt SMS (when the last one failed)',
                                },
                                { keys: 'v', label: 'Void the sale' },
                            ],
                        },
                        {
                            type: 'note',
                            tone: 'tip',
                            text: 'The **receipt link** (…/r/…) opens without a login and shows no phone, customer number or cost, so it is safe to send to the customer. It can be shown in English or Bangla.',
                        },
                    ],
                },
                {
                    id: 'void',
                    title: 'Voiding a sale',
                    blocks: [
                        {
                            type: 'bullets',
                            items: [
                                'Only on the **same business day**, with a reason of at least 3 characters, and only with the void permission.',
                                'Not possible when the sale already has a return, or when it is the replacement sale of an exchange: use a return instead.',
                                "Stock goes back to the shelf. Payments on this sale are voided; a payment that also covered other invoices keeps those parts and the rest becomes the customer's advance.",
                            ],
                        },
                    ],
                },
            ],
        },
        {
            id: 'returns',
            title: 'Returns & exchange',
            summary:
                'Taking goods back for a refund, store credit or a replacement.',
            permission: 'returns.view',
            navIds: ['returns'],
            sections: [
                {
                    id: 'return',
                    title: 'Making a return',
                    blocks: [
                        {
                            type: 'steps',
                            items: [
                                'Press [[F11]] at the register or [[N]] on the Returns page.',
                                '**Find the invoice:** scan or type the sale number.',
                                '**Select items:** set the quantity (up to what was sold and not yet returned; "Return all" fills everything) and the condition: **Sellable** (goes back to the shelf) or **Damaged / write-off**.',
                                '**Choose what the customer gets:** **Refund** by any payment method, **Store credit** (not for walk-in customers) or **Direct item exchange**.',
                                'Pick a reason (defect, size, colour, changed mind, wrong item, exchange, other), add a memo if needed, and press **Complete return**. The return receipt can be printed.',
                            ],
                        },
                        {
                            type: 'bullets',
                            items: [
                                'If the business has a **return window** (days), older sales are refused with a message.',
                                'The return value first settles any **open due** on that sale. Only the remainder is refunded or kept as credit.',
                                'Sellable items go back into the same stock batches they were sold from.',
                            ],
                        },
                    ],
                },
                {
                    id: 'exchange',
                    title: 'Exchanging items',
                    blocks: [
                        {
                            type: 'p',
                            text: 'Choose **Direct item exchange** in step 3 (or press [[X]] in the invoice dialog). Add the replacement items by name, SKU or barcode. The return value pays for them; if the customer owes more, take the difference with one payment method; if the return is worth more, give the surplus back as a refund or store credit. Press **Save exchange**: the return and the new sale (to the same customer) are saved together.',
                        },
                    ],
                },
            ],
        },
        {
            id: 'dues',
            title: 'Due collection',
            summary: 'Seeing who owes what and recording payments.',
            permission: 'customers.view',
            navIds: ['dues'],
            sections: [
                {
                    id: 'list',
                    title: 'The dues list',
                    blocks: [
                        {
                            type: 'bullets',
                            items: [
                                'Tiles at the top group debtors by age: 0–30, 31–60, 61–90 and 90+ days.',
                                'Columns: customer number, name, phone, credit-limit meter, open invoices, total due, last purchase, oldest invoice.',
                                'Filters: age, customer group, credit level (near limit above 75 %, over the limit). Views: Critical overdue (90+ days), Over the credit limit.',
                                'Dues are collected in the branch that made the sale, so the list shows this branch only.',
                            ],
                        },
                    ],
                },
                {
                    id: 'collect',
                    title: 'Collecting a payment',
                    blocks: [
                        {
                            type: 'steps',
                            items: [
                                "Press **Collect** on the row ([[Ctrl+D]]), or [[Enter]] to open the customer's dues page first.",
                                'Type the amount (partial is fine) and pick the **payment tender**; add a reference or note under "More".',
                                'Allocation is **Oldest first** automatically, or choose **Choose invoices** and tick the ones to settle.',
                                'Press **Print Money Receipt & Settle**. The money receipt opens for printing and the customer\'s "Payment received" SMS is sent when enabled.',
                            ],
                        },
                        {
                            type: 'note',
                            tone: 'tip',
                            text: 'Paying **more than the open invoices** is allowed: the extra stays on the account as an **advance** and can be used as "customer credit" at the register.',
                        },
                    ],
                },
            ],
        },
        {
            id: 'customers',
            title: 'Customers',
            summary: 'Customer records, numbers, profile and statements.',
            permission: 'customers.view',
            navIds: ['customers', 'statement'],
            sections: [
                {
                    id: 'list',
                    title: 'The customer list',
                    blocks: [
                        {
                            type: 'bullets',
                            items: [
                                '[[/]] searches by name (English or Bangla), shop name, the start of the customer number, or a phone number (type at least 3 digits).',
                                'Views: Overdue due debtors, Inactive customers. Filters: last purchase (today, 7 / 30 / 90 days, this year), group, balance (due / zero / advance), card status.',
                                'The totals row counts the whole filtered list (customers, balance, credit limit).',
                                '[[Shift+E]] exports the filtered list as CSV (opens correctly in Excel with Bangla names).',
                            ],
                        },
                    ],
                },
                {
                    id: 'add',
                    title: 'Adding or editing a customer',
                    blocks: [
                        {
                            type: 'p',
                            text: 'Press [[N]] (or **Add Customer**). Required: the English name and a Bangladeshi mobile number (013–019). Optional: Bangla name, shop name, second phone, email, group / tier, credit limit, area, postal code, address, date of birth, anniversary, notes.',
                        },
                        {
                            type: 'bullets',
                            items: [
                                'A phone number that already belongs to someone is flagged live ("This number already belongs to …") with an **Open existing** button, so you do not create duplicates.',
                                'The **customer number** is assigned by the server when you save: 8 digits (shown as 1000 0017), permanent and never reused. It is printed on the membership card and can be typed at the register.',
                                'Leave the credit limit empty to use the business default.',
                            ],
                        },
                    ],
                },
                {
                    id: 'profile',
                    title: 'The customer profile',
                    blocks: [
                        {
                            type: 'p',
                            text: 'Open a customer to see tiles for lifetime spend (after returns), store visits, outstanding due and credit utilisation, the membership card, and tabs Overview · Sales · Returns · Payments · Messages (the last 50 each; a tab appears only if you may view that data). Buttons: Collect due, SMS, New sale, Statement, Edit.',
                        },
                    ],
                },
                {
                    id: 'merge',
                    title: 'Deactivate and merge',
                    blocks: [
                        {
                            type: 'bullets',
                            items: [
                                '**Deactivate** hides a customer from the register search; their history stays. The walk-in customer cannot be deactivated.',
                                "**Merge into another** moves all sales, payments and dues to the customer you pick, who keeps their number; balances are added together and the merged customer's card is revoked. This cannot be undone.",
                            ],
                        },
                    ],
                },
                {
                    id: 'statement',
                    title: 'Customer statement',
                    blocks: [
                        {
                            type: 'p',
                            text: 'Open **Customer Statement** ([[G A]]), search the customer, and choose a period (Last 7 days, This month, Last month, or From / To + **Apply**). It lists the completed invoices with total, paid, due and status, and a totals row. **Print statement** uses the browser print dialog (save as PDF from there). **SMS statement summary** sends one SMS with the period, invoice count, total, paid and due; it needs the customer to have a valid mobile.',
                        },
                    ],
                },
            ],
        },
        {
            id: 'cards',
            title: 'Membership cards',
            summary: 'Issuing, printing and replacing customer cards.',
            permission: 'cards.view',
            navIds: ['cards'],
            sections: [
                {
                    id: 'issue',
                    title: 'Issuing and printing',
                    blocks: [
                        {
                            type: 'bullets',
                            items: [
                                'Every active customer with a number is a member. Filter by group or status (issued / not issued / lost) and search by name or number.',
                                'Select customers and press **Issue card** (up to 200 at a time). Printing a customer without a card issues one automatically.',
                                'Card codes are **MC** + the 8-digit customer number + a 2-digit issue number (01 for the first card).',
                                '**Print** offers CR80 PVC (85.6 × 54 mm), CR80 with bleed and crop marks for the press, or A4 with 10 cards per sheet, up to 500 cards per print. The print view opens in a pop-up: allow pop-ups for this site. Save as PDF from the browser if you need a file.',
                                'After printing, confirm **Mark printed**: the card\'s "Times printed" count goes up.',
                                '**Download data CSV** gives the press the customer number, names, shop, phone, member-since date and card code.',
                            ],
                        },
                    ],
                },
                {
                    id: 'lost',
                    title: 'Lost cards',
                    blocks: [
                        {
                            type: 'p',
                            text: '**Report lost** blocks the card immediately (reason optional). **Reissue** creates a new card with the next issue number, for example …-02, and prints it like any other. The customer number never changes.',
                        },
                    ],
                },
                {
                    id: 'design',
                    title: 'The card',
                    blocks: [
                        {
                            type: 'p',
                            text: 'Front: dark with gold, your logo and business name, the customer\'s name in English and Bangla, the tier ("Platinum VIP" for groups whose name contains VIP, otherwise "Gold privilege") and the member-since year; the number is masked until **Reveal number**. Back: signature strip, barcode and QR code of the customer number, and the terms text.',
                        },
                    ],
                },
            ],
        },
        {
            id: 'messages',
            title: 'SMS',
            summary:
                'Automatic receipts and reminders, the message log, gateway settings.',
            permission: 'sms.view',
            navIds: ['messages'],
            sections: [
                {
                    id: 'auto',
                    title: 'Automatic messages',
                    blocks: [
                        {
                            type: 'p',
                            text: 'Four templates are sent automatically when switched on in SMS settings, to a named customer with a mobile number: **Sale completed**, **Payment received** (after a due collection), **Sale returned** (after a return, with the amount handed back; an exchange sends none) and **Sale voided** (after a void). Statement summaries are sent by hand from the statement page. Messages are only sent when a provider is configured.',
                        },
                    ],
                },
                {
                    id: 'compose',
                    title: 'Sending a message',
                    blocks: [
                        {
                            type: 'steps',
                            items: [
                                'Open **WhatsApp & SMS** ([[G W]]) → **Composer & Dispatch**.',
                                'Choose the recipients: one customer, a customer group, everyone with a due, or all customers. Only customers with a valid mobile are counted.',
                                'Write the text (up to 1000 characters) and insert variables: {name}, {shop}, {due}, {number}. The phone preview shows the message; the part counter shows 160 characters per part for English and 70 for Bangla, with a cost estimate.',
                                'Press **Send message** and confirm.',
                            ],
                        },
                        {
                            type: 'note',
                            tone: 'warning',
                            text: 'Bangla text uses Unicode, so one SMS part holds 70 characters instead of 160. Keep reminders short to control cost.',
                        },
                    ],
                },
                {
                    id: 'log',
                    title: 'The message log',
                    blocks: [
                        {
                            type: 'p',
                            text: "Every message shows its time, customer, phone, type, text, status (**Queued**, **Sent**, **Failed**), channel and cost. Search by phone or text and filter by status. **Send again** is available for failed messages. A message stays Queued until the server's queue worker has delivered it.",
                        },
                    ],
                },
                {
                    id: 'settings',
                    title: 'SMS settings (administrator)',
                    blocks: [
                        {
                            type: 'bullets',
                            items: [
                                'Settings → SMS. Provider: **None (SMS off)**, **Log only (testing)**, **SSL Wireless** or **OneCodeSoft**.',
                                'For SSL Wireless enter the Sender ID, the API token (stored encrypted, never shown again) and optionally the base URL and sender name.',
                                'For OneCodeSoft enter the base URL and API key from its Dashboard > API (the key is stored encrypted, never shown again). The sender name is optional: leave it blank and OneCodeSoft picks an approved sender.',
                                '**Price per SMS part** is used only for the cost estimate in the composer.',
                                'Per template: **Send automatically**, the text (up to 480 characters) or **Use default text**.',
                                '**Send test SMS** sends one message to a number of your choice to confirm the gateway works.',
                            ],
                        },
                    ],
                },
            ],
        },
        {
            id: 'products',
            title: 'Products & SKUs',
            summary:
                'Catalogue, prices, barcodes, sizes and colours, categories and brands.',
            permission: 'products.view',
            navIds: ['products'],
            sections: [
                {
                    id: 'list',
                    title: 'The product list',
                    blocks: [
                        {
                            type: 'bullets',
                            items: [
                                '[[/]] searches by name (English or Bangla), SKU or barcode. Filters: category, brand, status, stock level, date added. Views: Low stock & reorder needed, Out of stock, Inactive.',
                                'Columns include cost (only with the cost permission), retail price, stock per branch and total stock.',
                                'Row menu: Edit, Activate / deactivate, Copy SKU, Copy barcode, Delete. **Export Product Catalog** downloads a CSV.',
                            ],
                        },
                    ],
                },
                {
                    id: 'add',
                    title: 'Adding a product',
                    blocks: [
                        {
                            type: 'p',
                            text: 'Press [[N]] (or **Add Product**). Required: the English name, a **SKU** (unique in your business), a **unit of measure** and the **Retail MRP**. Optional: Bangla name, barcode, HS code, type (stocked product or service), category, brand, primary vendor, VAT category, cost price, wholesale price, lowest allowed sale price, minimum sale quantity, reorder level, description and photo.',
                        },
                        {
                            type: 'bullets',
                            items: [
                                '**Barcode:** leave it empty and the system assigns an in-store EAN-13 starting with 20. Barcodes must be unique and may not start with MC (reserved for membership cards).',
                                '**Lowest allowed sale price:** selling below it at the register needs a manager approval.',
                                '**Photo:** PNG, JPG or WebP up to 4 MB. **Reuse uploaded** picks a photo already used by another product, so the same file is not uploaded twice.',
                                'A **service** has no stock and is never counted.',
                            ],
                        },
                    ],
                },
                {
                    id: 'variants',
                    title: 'Sizes and colours (variants)',
                    blocks: [
                        {
                            type: 'p',
                            text: 'When creating a product, open **Sizes and colours**, pick a first option (for example Size) and optionally a second (Colour), and tick the values; you can add a new value inline. One product is created per combination (up to 200), each with its own SKU and barcode and, if you wish, its own price and cost. The style page shows the **Variant matrix**: typing a price in an empty cell creates that variant. **Add to style** attaches an existing product to the style.',
                        },
                    ],
                },
                {
                    id: 'delete',
                    title: 'Deactivate or delete',
                    blocks: [
                        {
                            type: 'p',
                            text: 'A product that has ever been sold, returned, purchased, adjusted or stocked is only **deactivated** (it disappears from the register but its history stays). Only an unused product is deleted. The same rule applies to categories and brands that products use.',
                        },
                    ],
                },
                {
                    id: 'taxonomy',
                    title: 'Categories and brands',
                    blocks: [
                        {
                            type: 'p',
                            text: 'The **Category Management** tab of the Products page creates, renames and deletes categories (with a parent category) and brands. Categories drive the chips at the register and the sales-by-category report.',
                        },
                    ],
                },
            ],
        },
        {
            id: 'stock',
            title: 'Stock',
            summary: 'Stock on hand, movements and adjustments.',
            permission: 'stock.view',
            navIds: ['stock', 'adjustments'],
            sections: [
                {
                    id: 'onhand',
                    title: 'Stock on hand',
                    blocks: [
                        {
                            type: 'bullets',
                            items: [
                                '**Stock & Transfer** ([[G K]]) lists every stocked product with the quantity per branch, the combined total, unit cost and stock value (cost columns need the cost permission; "avg" marks a weighted average when the product has no cost price).',
                                'Views: Low stock reorder alert. Filters: stock health (low / out / ok), category, moved in the last 7 / 30 / 90 days or no movement in 90 days.',
                                '**Export Valuation** downloads a CSV.',
                                "Open a product's **movement history** to see date, type (opening stock, purchase, sale, sale voided, sale return, adjustment), change and running balance for this branch (latest 500).",
                            ],
                        },
                        {
                            type: 'note',
                            tone: 'tip',
                            text: 'Stock is kept in **batches**: each purchase or adjustment creates a batch at its cost, and sales take from the oldest batch first (FIFO). That is how the stock valuation and the profit reports know the real cost.',
                        },
                    ],
                },
                {
                    id: 'adjust',
                    title: 'Stock adjustments',
                    blocks: [
                        {
                            type: 'steps',
                            items: [
                                'Open **Stock** → **New adjustment** (needs the adjust permission).',
                                'Pick a reason: Damage, Loss, Found, Opening stock, Correction, Stocktake, Other. Add a note if useful.',
                                'Scan or search products. Each line is **Add** or **Remove** with a quantity and an optional unit cost (1 to 200 lines).',
                                'Press **Apply adjustment**. Adding creates a new batch (at the product cost price if you left cost empty); removing takes from the oldest batches.',
                            ],
                        },
                        {
                            type: 'p',
                            text: 'Adjustments are numbered and listed with their reason; they cannot be edited afterwards. Make a reverse adjustment to correct one.',
                        },
                    ],
                },
            ],
        },
        {
            id: 'purchases',
            title: 'Purchases and vendors',
            summary: 'Receiving stock from suppliers and paying them.',
            permission: ['purchases.view', 'vendors.view'],
            navIds: ['purchases', 'vendors'],
            sections: [
                {
                    id: 'receive',
                    title: 'Receiving a purchase (GRN)',
                    blocks: [
                        {
                            type: 'steps',
                            items: [
                                'Open **Purchases (GRN)** ([[G P]]) → [[N]], or use "Receive purchase" in the command palette.',
                                "Choose the vendor, type the vendor's invoice number and date, and a note if needed.",
                                'Scan or search products and enter **quantity, unit cost and discount** per line (up to 300 lines). Add an order discount and shipping if any.',
                                'Optionally enter **Paid now** with a payment method (needs the pay permission). Leave it empty to keep the whole amount as payable.',
                                'Press **Receive purchase**. Stock goes up immediately: there is no draft step.',
                            ],
                        },
                        {
                            type: 'bullets',
                            items: [
                                "Each stocked line becomes a **stock batch** at its landed cost (shipping minus order discount is spread over the lines), and the product's cost price is updated to it.",
                                'The list shows totals (inward bills, items received, amount paid, outstanding payables) and filters by status, payment, vendor, branch and date.',
                            ],
                        },
                    ],
                },
                {
                    id: 'cancel',
                    title: 'Cancelling a purchase',
                    blocks: [
                        {
                            type: 'p',
                            text: 'Open the purchase and press **Cancel purchase**. This is possible only while none of its stock has been sold or used and no payment has been applied to it. Stock is reversed.',
                        },
                    ],
                },
                {
                    id: 'vendors',
                    title: 'Vendors',
                    blocks: [
                        {
                            type: 'bullets',
                            items: [
                                '**Vendor Directory** ([[G V]]) lists suppliers with open bills and payable balance; search by name, company, phone or address; views for overdue payables and cleared balances. **Export CSV** is available.',
                                '**Create Vendor Profile:** name (required), company, phone, email, address, notes.',
                                'The vendor page shows the latest 50 purchases in this branch and the balance line ("You owe this vendor", "Paid in advance" or "Nothing owed").',
                                '**Pay Vendor:** amount, payment method and reference. The payment settles the oldest bills first; any extra becomes an advance.',
                                '**Delete** only deactivates: history and balance stay, and the vendor no longer appears when receiving stock.',
                            ],
                        },
                    ],
                },
            ],
        },
        {
            id: 'insights',
            title: 'Dashboard and reports',
            summary: "Today's numbers, trends and exportable reports.",
            permission: ['dashboard.view', 'reports.view'],
            navIds: ['dashboard', 'reports'],
            sections: [
                {
                    id: 'dashboard',
                    title: 'Dashboard',
                    blocks: [
                        {
                            type: 'bullets',
                            items: [
                                'Period: Today (default), Yesterday, 7 days, 30 days, This month or a custom range (up to 366 days); every figure is compared with the previous period of the same length.',
                                'Tiles: Net sales, Gross profit, Transactions, Average basket, Dues outstanding, Collections, Returns, FIFO stock value, Low-stock count. Profit and stock value need the profit permission.',
                                'Widgets: sales trend (hourly or daily), payment-method mix, hourly heat map, branch comparison (all-branches permission), dues aging, top products and customers, low-stock alerts, recent invoices, overdue balances with a **Collect** button.',
                                'Each widget has a menu: refresh, change period, open the full report, export CSV. [[R]] refreshes everything; the page does not refresh by itself.',
                                'The **Store Setup Checklist** guides a new business through its first steps and can be dismissed.',
                            ],
                        },
                    ],
                },
                {
                    id: 'reports',
                    title: 'Reports',
                    blocks: [
                        {
                            type: 'table',
                            head: ['Group', 'Report', 'What it answers'],
                            rows: [
                                [
                                    'Money',
                                    'Daily cash summary',
                                    'Cash and other tenders received per day',
                                ],
                                [
                                    'Sales',
                                    'Sales by day',
                                    'Net sales, invoices and returns per day',
                                ],
                                [
                                    'Sales',
                                    'Sales by product',
                                    'Quantity, revenue and margin per product (filter by category or brand)',
                                ],
                                ['Sales', 'Sales by cashier', 'Sales per user'],
                                [
                                    'Sales',
                                    'Sales by payment method',
                                    'Split by cash, card, bKash…',
                                ],
                                [
                                    'Sales',
                                    'Returns',
                                    'Returns and exchanges in the period',
                                ],
                                [
                                    'Customers',
                                    'Top customers by purchase',
                                    'Lifetime value ranking (top 10 to 1000)',
                                ],
                                [
                                    'Customers',
                                    'Customers by last purchase',
                                    'Who has not bought for 1, 2, 3+ months',
                                ],
                                [
                                    'Customers',
                                    'Dues aging',
                                    'Open dues by age, as of today',
                                ],
                                [
                                    'Inventory',
                                    'Stock valuation',
                                    'FIFO stock value per product, as of today',
                                ],
                            ],
                        },
                        {
                            type: 'bullets',
                            items: [
                                'Period presets: Today, Yesterday, **This week (Saturday to today)**, This month, Last quarter, **Fiscal year (from 1 July)**, or a custom range of up to 366 days. "As of today" reports take no period.',
                                'Reports show your current branch; with the all-branches permission you can choose one branch or all branches.',
                                'Cost and profit columns appear only with the profit permission. **Export** (CSV, Excel or PDF) needs the export permission. **Print** uses the browser dialog.',
                            ],
                        },
                    ],
                },
            ],
        },
        {
            id: 'settings',
            title: 'Settings and administration',
            summary:
                'Business profile, branches, registers, payment methods, taxes, units, users, roles, audit log.',
            permission: ['settings.manage', 'users.manage', 'audit.view'],
            navIds: ['settings', 'users', 'audit'],
            sections: [
                {
                    id: 'business',
                    title: 'Business',
                    blocks: [
                        {
                            type: 'bullets',
                            items: [
                                '**Profile:** name, legal name, VAT BIN, VAT address, phone, email, address, and the **logo** (PNG, JPG or WebP up to 2 MB) shown on the sign-in page, the app, receipts and cards. The page also shows your **business code** for signing in.',
                                '**Regional:** default language, time zone, currency.',
                                '**Sales rules:** prices include VAT; **back-date limit** (0–365 days); **return window** in days (empty = no limit); **default credit limit** (empty = no limit).',
                            ],
                        },
                    ],
                },
                {
                    id: 'branches',
                    title: 'Branches and registers',
                    blocks: [
                        {
                            type: 'bullets',
                            items: [
                                '**Branch:** code, name, phone, address, receipt header and footer, and its own rules: receipt paper (80 mm or 58 mm), stock batch selection, cash rounding step (0 = none), allow selling below zero stock, print the receipt automatically.',
                                '**Register:** a counter in a branch with a name and a short code (up to 6 letters or digits) that becomes the sale-number prefix.',
                                'Nothing here is deleted: switch the **Active** flag off instead, and history stays intact.',
                            ],
                        },
                    ],
                },
                {
                    id: 'methods',
                    title: 'Payment methods, taxes, units',
                    blocks: [
                        {
                            type: 'bullets',
                            items: [
                                '**Payment methods:** name, type (cash, card, mobile wallet, bank, customer credit, exchange credit), **requires reference** (TrxID), sort order. Only active methods appear at the register.',
                                '**Taxes:** VAT rates in % with one default rate.',
                                '**Units:** name, short name and whether fractions are allowed (kg, metre).',
                            ],
                        },
                    ],
                },
                {
                    id: 'users',
                    title: 'Users and roles',
                    blocks: [
                        {
                            type: 'bullets',
                            items: [
                                '**Add Staff Member:** full name; at least one of email, username or Bangladeshi mobile; password; "Must choose a new password at next sign-in"; at least one role; at least one branch (the first is the default); an optional 4–6 digit **register PIN** used for manager approvals.',
                                '**Deactivate** stops a user signing in. You cannot deactivate yourself, and one active Owner must remain. Only an Owner may grant or remove the Owner role.',
                                '**Reset PIN** sets a new PIN and clears a lockout.',
                                '**Permissions matrix:** tick what each role may do and set its **highest discount (%)**; each role has its own Save button. Custom roles can be added; a role is deleted only when it is not a system role and nobody holds it.',
                            ],
                        },
                        {
                            type: 'table',
                            head: ['System role', 'Can do', 'Discount cap'],
                            rows: [
                                [
                                    'Owner',
                                    'Everything, in every branch',
                                    '100 %',
                                ],
                                [
                                    'Manager',
                                    'Everything except users, roles, settings, the audit log, branch switching and all-branch reports',
                                    '20 %',
                                ],
                                [
                                    'Cashier',
                                    'Sell, view sales, returns, stock, products and cards, view and add customers, collect dues',
                                    '0 %',
                                ],
                                [
                                    'Accountant',
                                    'View sales, returns, customers and vendors, export customers, SMS hub, reports with profit and export, collect dues, pay vendors',
                                    '0 %',
                                ],
                                [
                                    'Stock Keeper',
                                    'Products, stock, purchases and vendors, view reports (cannot pay vendors)',
                                    '0 %',
                                ],
                            ],
                        },
                    ],
                },
                {
                    id: 'audit',
                    title: 'Audit log',
                    blocks: [
                        {
                            type: 'p',
                            text: 'Settings → Audit Security Log records who changed what and when: products, categories, settings, users, roles and PIN resets, every sale completed, voided, returned or exchanged, every register override (discount, price, credit limit, below cost), cards, customers, SMS settings and the legacy import. Filter by text, user, record type, event and date; open a row for the before / after details. Passwords, tokens and PINs are never stored in it.',
                        },
                    ],
                },
            ],
        },
        {
            id: 'faq',
            title: 'Common questions',
            summary: 'Quick answers and what to do when something goes wrong.',
            sections: [
                {
                    id: 'questions',
                    title: 'Quick answers',
                    blocks: [
                        {
                            type: 'table',
                            head: ['Question', 'Answer'],
                            rows: [
                                [
                                    'I charged the wrong amount.',
                                    'Same day: void the sale from Sales history (reason required) and sell again. Later: make a return.',
                                ],
                                [
                                    'The receipt did not print.',
                                    'Press [[Ctrl+P]] at the register to reprint the last receipt, or open the sale in Sales history and press [[P]]. Check the browser print dialog and the paper size in Branch settings.',
                                ],
                                [
                                    'The customer wants the receipt on their phone.',
                                    'Open the sale and press [[S]] to copy the public receipt link, then send it by SMS or WhatsApp from your phone.',
                                ],
                                [
                                    'Why does the register ask for a manager?',
                                    'The discount exceeds your role cap, the due exceeds the credit limit, or the price is below cost. A manager types their login and password or staff PIN.',
                                ],
                                [
                                    'A barcode does not scan.',
                                    'Type the SKU or the product name instead. Check the product for a barcode and that it is active.',
                                ],
                                [
                                    'Where did my held bill go?',
                                    'Held bills are kept in the browser of the computer and register where you held them. Use [[Shift+F8]] on that computer.',
                                ],
                                [
                                    'The SMS says "Queued" for a long time.',
                                    'Messages are sent by a background worker on the server. Ask your administrator to check it and the SMS provider settings.',
                                ],
                                [
                                    'Stock is wrong.',
                                    "Open the product's movement history to see every change, then record a Stock adjustment with the right reason.",
                                ],
                                [
                                    'I see fewer menu items than a colleague.',
                                    "Menu items follow your role's permissions. An administrator can change them in Settings → Users & Roles.",
                                ],
                            ],
                        },
                    ],
                },
                {
                    id: 'next',
                    title: 'What is not here yet',
                    blocks: [
                        {
                            type: 'p',
                            text: 'This release runs the register **online**. The following are planned for the next phase and are shown in the app as "Next phase" or "Coming in the next phase" where a button exists:',
                        },
                        {
                            type: 'bullets',
                            items: [
                                'Offline selling on a dedicated register device with automatic sync.',
                                'Customer PIN verification at the register and PIN reset.',
                                'Branch transfers and stocktakes.',
                                'WhatsApp sending from the SMS hub, scheduled messages and a due-reminder schedule.',
                                'Barcode label printing.',
                                'The money module (accounts, expenses, fixed assets), notifications feed, backups and the lock screen.',
                            ],
                        },
                    ],
                },
            ],
        },
    ],
};
