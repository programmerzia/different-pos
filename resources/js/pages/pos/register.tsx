import { Head, router } from '@inertiajs/react';
import {
    Banknote,
    CircleCheck,
    Layers,
    Pause,
    Percent,
    Printer,
    RotateCcw,
    Search,
    ShieldCheck,
    Tag,
    Trash2,
    UserRound,
} from 'lucide-react';
import {
    useCallback,
    useEffect,
    useMemo,
    useReducer,
    useRef,
    useState,
} from 'react';
import { toast } from 'sonner';
import { actions, type AppAction } from '@/command/actions';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { Kbd } from '@/components/kbd';
import { RowContextMenu } from '@/components/row-context-menu';
import { Button } from '@/components/ui/button';
import { QTY_SCALE } from '@/engine/quantity';
import { useHotkeys } from '@/hotkeys/use-hotkeys';
import { useCan } from '@/hooks/use-can';
import { useDigits } from '@/hooks/use-digits';
import { useT } from '@/i18n';
import {
    saleBody,
    searchProducts,
    submitSale,
    type PosError,
} from '@/pos/register/api';
import {
    cartReducer,
    cartTotals,
    emptyCart,
    exceedsCap,
    newClientUuid,
    NO_DISCOUNT,
    totalQty,
} from '@/pos/register/cart';
import { CustomerDialog } from '@/pos/register/components/customer-dialog';
import { DiscountDialog } from '@/pos/register/components/discount-dialog';
import { HeldDialog } from '@/pos/register/components/held-dialog';
import { ManagerDialog } from '@/pos/register/components/manager-dialog';
import { PaymentDialog } from '@/pos/register/components/payment-dialog';
import { PriceCheckDialog } from '@/pos/register/components/price-check-dialog';
import { QtyDialog } from '@/pos/register/components/qty-dialog';
import {
    BillLine,
    Breakdown,
    CatalogTile,
    CategoryPill,
    CustomerStrip,
    EmptyBill,
    FnButton,
    PayBar,
} from '@/pos/register/components/register-parts';
import { TodayDialog } from '@/pos/register/components/today-dialog';
import { VariantPicker } from '@/pos/register/components/variant-picker';
import {
    heldKey,
    holdCart,
    loadHeld,
    resumeHeld,
    saveHeld,
    type HeldCart,
} from '@/pos/register/held';
import {
    POS_COMMAND_EVENT,
    POS_F1_EVENT,
    POS_KEYS,
    posHotkeySpecs,
    type PosCommand,
} from '@/pos/register/hotkeys';
import type { TenderRow } from '@/pos/register/tenders';
import type {
    CartLine,
    CatalogItem,
    CatalogVariant,
    CompletedSale,
    CustomerCard,
    PaymentMethod,
    RegisterSettings,
} from '@/pos/register/types';

type Props = {
    branch: { id: number; code: string; name: string };
    registers: { id: number; name: string; code: string }[];
    paymentMethods: PaymentMethod[];
    categories: { id: number; name: string; name_bn: string | null }[];
    settings: RegisterSettings;
    discountCapBp: number;
    customer: CustomerCard | null;
    receiptAvailable: boolean;
};

type DialogName =
    | 'customer'
    | 'line_discount'
    | 'bill_discount'
    | 'price'
    | 'today'
    | 'held'
    | 'pay'
    | 'qty'
    | 'clear';

// An Esc that a closing dialog already consumed (Radix prevents its default) is not "clear the bill".
const POS_SPECS = posHotkeySpecs((command, event) => {
    if (command === 'cancel' && event.defaultPrevented) {
        return;
    }
    window.dispatchEvent(
        new CustomEvent(POS_COMMAND_EVENT, { detail: command }),
    );
});

const registerStorageKey = (branchId: number) => `pos.register.${branchId}`;

function initialRegister(
    branchId: number,
    registers: Props['registers'],
): number | null {
    const stored = Number(
        window.localStorage.getItem(registerStorageKey(branchId)),
    );

    return (
        registers.find((r) => r.id === stored)?.id ?? registers[0]?.id ?? null
    );
}

/**
 * The online register (/app/pos). Prices the bill locally with the shared engine, then sends it to
 * CompleteSale, which re-prices and decides. Held bills live in this browser per register.
 */
export default function Register(props: Props) {
    const { t, locale } = useT();
    const d = useDigits();
    const can = useCan();
    const { settings, paymentMethods } = props;

    const [registerId, setRegisterId] = useState<number | null>(() =>
        initialRegister(props.branch.id, props.registers),
    );
    const [cart, dispatch] = useReducer(cartReducer, undefined, () => ({
        ...emptyCart(),
        customer: props.customer,
    }));
    const totals = useMemo(() => cartTotals(cart, settings), [cart, settings]);
    const total = totals.order.grand_total;

    const [query, setQuery] = useState('');
    const [category, setCategory] = useState<number | null>(null);
    const [items, setItems] = useState<CatalogItem[]>([]);
    const [loading, setLoading] = useState(false);
    const [highlight, setHighlight] = useState(-1);
    const [dialog, setDialog] = useState<DialogName | null>(null);
    const [picker, setPicker] = useState<CatalogItem | null>(null);
    const [approvalFor, setApprovalFor] = useState<string | null>(null);
    const [completed, setCompleted] = useState<CompletedSale | null>(null);
    const [lastSale, setLastSale] = useState<CompletedSale | null>(null);
    const [held, setHeld] = useState<HeldCart[]>(() =>
        loadHeld(window.localStorage, heldKey(props.branch.id, registerId)),
    );
    const scanRef = useRef<HTMLInputElement>(null);
    const gridRef = useRef<HTMLDivElement>(null);
    const rowRefs = useRef<(HTMLDivElement | null)[]>([]);

    const storageKey = heldKey(props.branch.id, registerId);
    const updateHeld = (next: HeldCart[]) => {
        setHeld(next);
        saveHeld(window.localStorage, storageKey, next);
    };

    // An expired approval is refused by the server (approval_expired) and then dropped.
    const approval = cart.approval;
    const capBp = Math.max(props.discountCapBp, approval?.capBp ?? 0);
    const selectedLine: CartLine | null = cart.lines[cart.selected] ?? null;

    const focusScan = useCallback(() => {
        requestAnimationFrame(() => {
            // A dialog opened in the meantime (F4 right after a scan) keeps the focus it just took.
            if (document.querySelector('[role="dialog"][data-state="open"]')) {
                return;
            }
            scanRef.current?.focus();
            scanRef.current?.select();
        });
    }, []);

    const closeDialog = useCallback(() => {
        setDialog(null);
        focusScan();
    }, [focusScan]);

    // Catalogue: debounced search, or the category / first page when the field is empty.
    useEffect(() => {
        const controller = new AbortController();
        const timer = setTimeout(
            () => {
                setLoading(true);
                searchProducts(query.trim(), category, controller.signal)
                    .then((r) => {
                        setItems(r.items);
                        setHighlight(query.trim() === '' ? -1 : 0);
                    })
                    .catch(() => undefined)
                    .finally(
                        () => !controller.signal.aborted && setLoading(false),
                    );
            },
            query === '' ? 0 : 200,
        );

        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [query, category]);

    const addVariants = useCallback(
        (rows: { variant: CatalogVariant; qty: number }[]) => {
            const allowed = rows.filter(({ variant, qty }) => {
                if (settings.allow_negative_stock || variant.stock === null) {
                    return true;
                }
                const onBill =
                    cart.lines.find((l) => l.productId === variant.id)?.qty ??
                    0;
                if (onBill + qty > variant.stock) {
                    toast.warning(
                        t('pos.stock.not_enough', {
                            name: variant.name,
                            qty: d.qty(Math.max(variant.stock, 0)),
                        }),
                    );

                    return false;
                }

                return true;
            });
            if (allowed.length > 0) {
                setCompleted(null);
                dispatch({ type: 'add', items: allowed });
            }
        },
        [cart.lines, settings.allow_negative_stock, t, d],
    );

    const choose = useCallback(
        (item: CatalogItem | undefined) => {
            if (!item) {
                return;
            }
            const only = item.variants[0];
            if (item.kind === 'product' && only) {
                addVariants([{ variant: only, qty: QTY_SCALE }]);
                setQuery('');
                focusScan();

                return;
            }
            setPicker(item);
        },
        [addVariants, focusScan],
    );

    /** Enter in the scan field: exact barcode / SKU adds at once, else the highlighted result. */
    const scan = async () => {
        const term = d.latin(query.trim());
        if (term === '') {
            return;
        }
        try {
            const result = await searchProducts(term, null);
            if (result.exact) {
                choose(result.items[0]);

                return;
            }
            const pick =
                highlight >= 0
                    ? items[highlight]
                    : result.items.length === 1
                      ? result.items[0]
                      : undefined;
            if (pick) {
                choose(pick);
            } else if (result.items.length === 0) {
                toast.warning(t('pos.scan.not_found', { q: term }));
            } else {
                setItems(result.items);
                setHighlight(0);
            }
        } catch {
            toast.error(t('pos.errors.network'));
        }
    };

    const newSale = useCallback(() => {
        setCompleted(null);
        setQuery('');
        focusScan();
    }, [focusScan]);

    const hold = () => {
        if (cart.lines.length === 0) {
            return;
        }
        updateHeld(holdCart(held, cart));
        dispatch({ type: 'clear', uuid: newClientUuid() });
        toast.success(t('pos.held.done'));
        focusScan();
    };

    const resume = (id: string) => {
        const { state, list } = resumeHeld(held, id);
        if (!state) {
            return;
        }
        // The bill on screen goes on hold in its place.
        updateHeld(cart.lines.length > 0 ? holdCart(list, cart) : list);
        setCompleted(null);
        dispatch({ type: 'load', state });
        closeDialog();
    };

    const receiptUrl = useCallback(
        (uuid: string) =>
            props.receiptAvailable
                ? `/app/sales/${encodeURIComponent(uuid)}/receipt?paper=${encodeURIComponent(settings.paper)}`
                : null,
        [props.receiptAvailable, settings.paper],
    );

    const printReceipt = (sale: CompletedSale | null) => {
        const url = sale ? (sale.receipt_url ?? receiptUrl(sale.uuid)) : null;
        if (url) {
            window.open(url, '_blank', 'noopener');
        } else if (sale) {
            toast.info(t('pos.done.no_receipt'));
        }
    };

    const pay = async (tenders: TenderRow[]): Promise<PosError | null> => {
        const result = await submitSale(
            saleBody(cart, tenders, total, registerId),
        );
        if (!result.ok) {
            if (result.error.code === 'approval_expired') {
                dispatch({ type: 'approval', approval: null });
            }
            if (result.error.status === 0) {
                return { ...result.error, message: t('pos.errors.network') };
            }
            if (
                result.error.status === 422 &&
                !result.error.code &&
                result.error.errors
            ) {
                return {
                    ...result.error,
                    detail: Object.values(result.error.errors).flat().join(' '),
                };
            }

            return result.error;
        }
        const sale = result.data.sale;
        setCompleted(sale);
        setLastSale(sale);
        dispatch({ type: 'clear', uuid: newClientUuid() });
        setDialog(null);
        if (sale.flags.includes('price_mismatch')) {
            toast.warning(t('pos.done.price_mismatch'));
        }
        if (settings.auto_print) {
            printReceipt(sale);
        }
        focusScan();

        return null;
    };

    const openPay = () => {
        if (cart.lines.length === 0) {
            toast.info(t('pos.bill.empty'));

            return;
        }
        if (exceedsCap(totals, capBp)) {
            toast.warning(t('pos.discount.over_cap_pay'));
        }
        setDialog('pay');
    };

    const run = (command: PosCommand | 'new_sale') => {
        switch (command) {
            case 'new_sale':
                newSale();
                break;
            case 'search':
                focusScan();
                break;
            case 'qty':
                if (selectedLine) {
                    setDialog('qty');
                }
                break;
            case 'customer':
                setDialog('customer');
                break;
            case 'price_check':
                setDialog('price');
                break;
            case 'line_discount':
                if (selectedLine) {
                    setDialog('line_discount');
                }
                break;
            case 'bill_discount':
                if (cart.lines.length > 0) {
                    setDialog('bill_discount');
                }
                break;
            case 'today':
                setDialog('today');
                break;
            case 'hold':
                hold();
                break;
            case 'held':
                setDialog('held');
                break;
            case 'pay':
                openPay();
                break;
            case 'returns':
                router.visit('/app/returns');
                break;
            case 'collect_due':
                if (
                    cart.customer &&
                    cart.customer.balance > 0 &&
                    can('customers.view')
                ) {
                    router.visit(`/app/dues/${cart.customer.uuid}`);
                }
                break;
            case 'reprint':
                printReceipt(lastSale);
                break;
            case 'cancel':
                if (completed) {
                    newSale();
                } else if (query !== '') {
                    setQuery('');
                    focusScan();
                } else if (cart.lines.length > 0) {
                    setDialog('clear');
                }
                break;
            case 'remove':
                if (selectedLine) {
                    dispatch({ type: 'remove', key: selectedLine.key });
                }
                break;
            case 'up':
                dispatch({ type: 'move', delta: -1 });
                break;
            case 'down':
                dispatch({ type: 'move', delta: 1 });
                break;
        }
    };
    const runRef = useRef(run);
    useEffect(() => {
        runRef.current = run;
    });

    useHotkeys('pos', POS_SPECS);

    // Register keys arrive as events; F1 is the shell's global key and here means "new sale / scan".
    useEffect(() => {
        const onCommand = (e: Event) =>
            runRef.current((e as CustomEvent<PosCommand>).detail);
        const onF1 = () => runRef.current('new_sale');
        window.addEventListener(POS_COMMAND_EVENT, onCommand);
        window.addEventListener(POS_F1_EVENT, onF1);

        return () => {
            window.removeEventListener(POS_COMMAND_EVENT, onCommand);
            window.removeEventListener(POS_F1_EVENT, onF1);
        };
    }, []);

    // The palette lists the register's commands while it is open; the bill rows get a context menu.
    useEffect(() => {
        const commands: AppAction[] = POS_KEYS.filter(
            (k) => !['cancel', 'remove', 'up', 'down'].includes(k.command),
        ).map((k) => ({
            id: `pos.cmd.${k.command}`,
            entity: null,
            labelKey: k.labelKey,
            keys: k.keys,
            permission: 'pos.sell',
            run: () => runRef.current(k.command),
        }));
        const lineActions: AppAction<CartLine>[] = [
            {
                id: 'pos.line.qty',
                entity: 'pos_line',
                labelKey: 'pos.keys.qty',
                icon: Tag,
                keys: 'f3',
                requires: 'handler',
                run: ({ row, handlers }) => handlers?.['pos.line.qty']?.(row),
            },
            {
                id: 'pos.line.discount',
                entity: 'pos_line',
                labelKey: 'pos.keys.line_discount',
                icon: Percent,
                keys: 'f6',
                requires: 'handler',
                run: ({ row, handlers }) =>
                    handlers?.['pos.line.discount']?.(row),
            },
            {
                id: 'pos.line.remove',
                entity: 'pos_line',
                labelKey: 'pos.keys.remove',
                keys: 'delete',
                group: 'danger',
                requires: 'handler',
                run: ({ row, handlers }) =>
                    handlers?.['pos.line.remove']?.(row),
            },
        ];
        const off = [
            actions.register(...commands),
            actions.register(...lineActions),
        ];

        return () => off.forEach((fn) => fn());
    }, []);

    const lineHandlers = useMemo(
        () => ({
            'pos.line.qty': (row: CartLine) => {
                dispatch({
                    type: 'select',
                    index: cart.lines.findIndex((l) => l.key === row.key),
                });
                setDialog('qty');
            },
            'pos.line.discount': (row: CartLine) => {
                dispatch({
                    type: 'select',
                    index: cart.lines.findIndex((l) => l.key === row.key),
                });
                setDialog('line_discount');
            },
            'pos.line.remove': (row: CartLine) =>
                dispatch({ type: 'remove', key: row.key }),
        }),
        [cart.lines],
    );

    // Pre-attach from ?customer= is in the props; keep the URL clean so a refresh starts fresh.
    useEffect(() => {
        if (props.customer) {
            window.history.replaceState(
                window.history.state,
                '',
                window.location.pathname,
            );
        }
        focusScan();
    }, [props.customer, focusScan]);

    useEffect(() => {
        rowRefs.current[cart.selected]?.scrollIntoView({ block: 'nearest' });
    }, [cart.selected]);

    const gridKeys = (e: React.KeyboardEvent<HTMLElement>, index: number) => {
        const grid = gridRef.current;
        const columns = grid
            ? getComputedStyle(grid).gridTemplateColumns.split(' ').length
            : 1;
        const move = {
            ArrowRight: 1,
            ArrowLeft: -1,
            ArrowDown: columns,
            ArrowUp: -columns,
        }[e.key];
        if (move !== undefined) {
            e.preventDefault();
            const next = index + move;
            if (next < 0) {
                focusScan();

                return;
            }
            const target = Math.min(items.length - 1, next);
            setHighlight(target);
            (grid?.children[target] as HTMLElement | undefined)?.focus();
        }
        if (e.key === 'Enter') {
            e.preventDefault();
            choose(items[index]);
        }
        if (e.key === 'Escape') {
            e.preventDefault();
            e.stopPropagation();
            focusScan();
        }
    };

    /** Scan field keys: Enter scans; with an empty field ↑↓ pick a bill line and Delete removes it. */
    const scanKeys = (e: React.KeyboardEvent<HTMLInputElement>) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            void scan();

            return;
        }
        if (query === '') {
            if (e.key === 'ArrowUp' || e.key === 'ArrowDown') {
                e.preventDefault();
                dispatch({ type: 'move', delta: e.key === 'ArrowUp' ? -1 : 1 });
            }
            if (e.key === 'Delete' && selectedLine) {
                e.preventDefault();
                dispatch({ type: 'remove', key: selectedLine.key });
            }

            return;
        }
        if (e.key === 'ArrowDown' && items.length > 0) {
            e.preventDefault();
            const target = Math.max(0, highlight);
            setHighlight(target);
            (
                gridRef.current?.children[target] as HTMLElement | undefined
            )?.focus();
        }
    };

    const customer = cart.customer;
    const name = (item: { name: string; name_bn: string | null }) =>
        locale === 'bn' && item.name_bn ? item.name_bn : item.name;
    const lineDiscountBase = selectedLine
        ? (totals.lines[cart.selected]?.gross ?? 0)
        : 0;
    const billDiscountBase =
        totals.order.subtotal -
        totals.order.line_discount_total -
        totals.order.group_discount;
    const pcs = d.qty(totalQty(cart.lines));
    const hasLines = cart.lines.length > 0;
    const canReturn = can(['returns.view', 'returns.create']);
    const collectDue =
        customer && can('customers.view')
            ? () => router.visit(`/app/dues/${customer.uuid}`)
            : null;
    const orderDiscountLabel =
        cart.orderDiscount.type === 'percent_bp'
            ? `${d.n(cart.orderDiscount.value / 100)}%`
            : null;

    /** − / + on a bill line; more than the stock is refused unless negative stock is allowed. */
    const stepLine = (line: CartLine, delta: number) => {
        const qty = line.qty + delta;
        if (
            delta > 0 &&
            !settings.allow_negative_stock &&
            line.stock !== null &&
            qty > line.stock
        ) {
            toast.warning(
                t('pos.stock.not_enough', {
                    name: line.name,
                    qty: d.qty(Math.max(line.stock, 0)),
                }),
            );

            return;
        }
        dispatch({ type: 'qty', key: line.key, qty });
    };
    const selectLine = (index: number) => dispatch({ type: 'select', index });

    return (
        <>
            <Head title={t('pos.title')} />
            <div className="flex min-h-0 flex-col overflow-hidden md:h-[calc(100svh-var(--topbar-h)-var(--statusbar-h))] lg:flex-row">
                {/* Left: scan bar, category pills, catalogue grid / completion, function bar */}
                <section
                    className="flex min-w-0 flex-1 flex-col overflow-hidden border-r"
                    aria-label={t('pos.catalog.label')}
                >
                    <div className="shrink-0 space-y-2 border-b bg-surface p-2">
                        <div className="flex items-center gap-2">
                            <div className="relative flex-1">
                                <Search
                                    className="pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                <input
                                    ref={scanRef}
                                    id="pos-barcode-input"
                                    value={query}
                                    onChange={(e) => {
                                        setQuery(e.target.value);
                                        if (
                                            completed &&
                                            e.target.value !== ''
                                        ) {
                                            setCompleted(null);
                                        }
                                    }}
                                    placeholder={t('pos.scan.placeholder')}
                                    aria-label={t('pos.scan.placeholder')}
                                    autoComplete="off"
                                    className="touch-target h-9 w-full rounded-lg border bg-surface-sunken pr-12 pl-8 text-xs text-foreground placeholder:text-muted-foreground focus:border-primary focus:ring-2 focus:ring-ring/40 focus:outline-none"
                                    onKeyDown={scanKeys}
                                />
                                <Kbd
                                    keys="f2"
                                    className="absolute top-1/2 right-2 -translate-y-1/2 text-[10px]"
                                />
                            </div>
                            {props.registers.length > 1 && (
                                <select
                                    className="touch-target h-9 rounded-lg border bg-surface-sunken px-2 text-xs"
                                    aria-label={t('pos.register_select')}
                                    value={registerId ?? ''}
                                    onChange={(e) => {
                                        const id = Number(e.target.value);
                                        setRegisterId(id);
                                        setHeld(
                                            loadHeld(
                                                window.localStorage,
                                                heldKey(props.branch.id, id),
                                            ),
                                        );
                                        window.localStorage.setItem(
                                            registerStorageKey(props.branch.id),
                                            String(id),
                                        );
                                    }}
                                >
                                    {props.registers.map((r) => (
                                        <option key={r.id} value={r.id}>
                                            {r.name}
                                        </option>
                                    ))}
                                </select>
                            )}
                            <button
                                type="button"
                                onClick={() => run('price_check')}
                                className="touch-target flex h-9 items-center gap-1 rounded-lg border bg-surface-sunken px-2.5 text-xs font-medium text-muted-foreground hover:border-primary"
                                title={t('pos.keys.price_check')}
                            >
                                <Tag className="size-3.5" aria-hidden="true" />
                                <span className="hidden sm:inline">
                                    {t('pos.price_check')}
                                </span>
                                <Kbd keys="f5" className="text-[10px]" />
                            </button>
                            {canReturn && (
                                <button
                                    type="button"
                                    onClick={() => run('returns')}
                                    className="touch-target flex h-9 items-center gap-1.5 rounded-lg border border-warning/30 bg-warning-soft px-3 text-xs font-semibold text-warning"
                                    title={t('pos.keys.returns')}
                                >
                                    <RotateCcw
                                        className="size-3.5"
                                        aria-hidden="true"
                                    />
                                    {t('pos.return_key')}
                                </button>
                            )}
                        </div>

                        {props.categories.length > 0 && (
                            <div
                                className="no-scrollbar flex items-center gap-1.5 overflow-x-auto pb-1 select-none"
                                role="toolbar"
                                aria-label={t('pos.catalog.categories')}
                            >
                                <CategoryPill
                                    active={category === null}
                                    onClick={() => setCategory(null)}
                                >
                                    {t('pos.catalog.all_items')}
                                </CategoryPill>
                                {props.categories.map((c) => (
                                    <CategoryPill
                                        key={c.id}
                                        active={category === c.id}
                                        onClick={() => setCategory(c.id)}
                                    >
                                        {name(c)}
                                    </CategoryPill>
                                ))}
                            </div>
                        )}
                    </div>

                    <div className="min-h-0 flex-1 overflow-y-auto p-3">
                        {completed ? (
                            <Completed
                                sale={completed}
                                canPrint={
                                    completed.receipt_url !== null ||
                                    props.receiptAvailable
                                }
                                onPrint={() => printReceipt(completed)}
                                onNew={newSale}
                            />
                        ) : items.length === 0 && !loading ? (
                            <p className="p-6 text-center text-xs text-muted-foreground">
                                {query === ''
                                    ? t('pos.catalog.empty')
                                    : t('pos.scan.none')}
                            </p>
                        ) : (
                            <div
                                ref={gridRef}
                                aria-label={t('pos.catalog.results')}
                                className="grid auto-rows-max grid-cols-2 content-start items-start gap-2.5 sm:grid-cols-3 xl:grid-cols-4"
                            >
                                {items.map((item, i) => (
                                    <CatalogTile
                                        key={item.key}
                                        item={item}
                                        highlighted={i === highlight}
                                        tabIndex={
                                            i === Math.max(0, highlight)
                                                ? 0
                                                : -1
                                        }
                                        onChoose={() => choose(item)}
                                        onFocus={() => setHighlight(i)}
                                        onKeyDown={(e) => gridKeys(e, i)}
                                    />
                                ))}
                            </div>
                        )}
                    </div>

                    {/* Function bar (mockup "POS Function Bar"). */}
                    <div
                        className="flex shrink-0 items-center justify-between gap-0.5 overflow-x-auto border-t bg-surface-sunken p-1.5 text-[11px]"
                        role="toolbar"
                        aria-label={t('pos.fn.label')}
                    >
                        <FnButton
                            icon={Pause}
                            label={t('pos.actions.hold')}
                            keys="f8"
                            disabled={!hasLines}
                            onClick={() => run('hold')}
                        />
                        <FnButton
                            icon={Layers}
                            label={
                                held.length > 0
                                    ? t('pos.fn.held_count', {
                                          count: held.length,
                                      })
                                    : t('pos.fn.held')
                            }
                            keys="shift+f8"
                            onClick={() => run('held')}
                        />
                        <FnButton
                            icon={UserRound}
                            label={t('pos.fn.customer')}
                            keys="f4"
                            onClick={() => run('customer')}
                        />
                        <FnButton
                            icon={Percent}
                            label={t('pos.fn.line_discount')}
                            keys="f6"
                            disabled={!selectedLine}
                            onClick={() => run('line_discount')}
                        />
                        <FnButton
                            icon={Tag}
                            label={t('pos.fn.order_discount')}
                            keys="shift+f6"
                            disabled={!hasLines}
                            onClick={() => run('bill_discount')}
                        />
                        <FnButton
                            icon={Banknote}
                            label={t('pos.fn.collect_due')}
                            keys="mod+d"
                            tone="warning"
                            disabled={
                                !collectDue ||
                                !customer ||
                                customer.balance <= 0
                            }
                            title={
                                collectDue && customer && customer.balance > 0
                                    ? undefined
                                    : t('pos.fn.no_due')
                            }
                            onClick={() => run('collect_due')}
                        />
                        {canReturn && (
                            <FnButton
                                icon={RotateCcw}
                                label={t('pos.fn.return')}
                                keys="f11"
                                tone="warning"
                                onClick={() => run('returns')}
                            />
                        )}
                        <FnButton
                            icon={Trash2}
                            label={t('pos.fn.clear')}
                            iconOnly
                            tone="danger"
                            disabled={!hasLines}
                            onClick={() => setDialog('clear')}
                        />
                    </div>
                </section>

                {/* Right: customer, bill lines, payment breakdown, PAY */}
                <aside
                    className="flex min-h-0 w-full shrink-0 flex-col overflow-hidden bg-surface lg:w-[420px] xl:w-[470px]"
                    aria-label={t('pos.bill.label')}
                >
                    <CustomerStrip
                        customer={customer}
                        onOpen={() => setDialog('customer')}
                        onCollect={collectDue}
                        onDetach={() =>
                            dispatch({ type: 'customer', customer: null })
                        }
                    />

                    <div className="min-h-0 flex-1 overflow-y-auto">
                        {cart.lines.length === 0 ? (
                            <EmptyBill />
                        ) : (
                            <table
                                aria-label={t('pos.bill.lines')}
                                className="w-full border-collapse"
                            >
                                <tbody className="divide-y">
                                    {cart.lines.map((line, i) => (
                                        <RowContextMenu
                                            key={line.key}
                                            entity="pos_line"
                                            row={line}
                                            handlers={lineHandlers}
                                        >
                                            <BillLine
                                                ref={(node) => {
                                                    rowRefs.current[i] = node;
                                                }}
                                                index={i}
                                                line={line}
                                                priced={totals.lines[i]}
                                                selected={i === cart.selected}
                                                onSelect={() => selectLine(i)}
                                                onQty={() => {
                                                    selectLine(i);
                                                    setDialog('qty');
                                                }}
                                                onStep={(delta) =>
                                                    stepLine(line, delta)
                                                }
                                                onDiscount={() => {
                                                    selectLine(i);
                                                    setDialog('line_discount');
                                                }}
                                                onRemove={() =>
                                                    dispatch({
                                                        type: 'remove',
                                                        key: line.key,
                                                    })
                                                }
                                            />
                                        </RowContextMenu>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </div>

                    <Breakdown
                        totals={totals}
                        pcs={pcs}
                        taxInclusive={settings.tax_inclusive}
                        groupName={customer?.group?.name ?? null}
                        orderDiscountLabel={orderDiscountLabel}
                        canDiscount={hasLines}
                        onOrderDiscount={() => run('bill_discount')}
                        onRemoveOrderDiscount={() =>
                            dispatch({
                                type: 'order_discount',
                                discount: NO_DISCOUNT,
                            })
                        }
                    />
                    {approval && (
                        <p className="flex items-center gap-1 bg-surface-sunken px-3 pb-1 text-[11px] text-success">
                            <ShieldCheck
                                className="size-3.5"
                                aria-hidden="true"
                            />{' '}
                            {t('pos.manager.approved_by', {
                                name: approval.managerName,
                            })}
                        </p>
                    )}
                    <PayBar
                        total={total}
                        pcs={pcs}
                        disabled={!hasLines}
                        onPay={() => run('pay')}
                    />
                </aside>
            </div>

            <VariantPicker
                item={picker}
                allowNegative={settings.allow_negative_stock}
                onAdd={(rows) => {
                    addVariants(rows);
                    setPicker(null);
                    setQuery('');
                    focusScan();
                }}
                onClose={() => {
                    setPicker(null);
                    focusScan();
                }}
            />
            <CustomerDialog
                open={dialog === 'customer'}
                current={customer}
                onAttach={(c) => {
                    dispatch({ type: 'customer', customer: c });
                    closeDialog();
                }}
                onDetach={() => {
                    dispatch({ type: 'customer', customer: null });
                    closeDialog();
                }}
                onClose={closeDialog}
            />
            <QtyDialog
                line={dialog === 'qty' ? selectedLine : null}
                allowNegative={settings.allow_negative_stock}
                onApply={(qty) => {
                    if (selectedLine) {
                        dispatch({ type: 'qty', key: selectedLine.key, qty });
                    }
                    closeDialog();
                }}
                onClose={closeDialog}
            />
            <DiscountDialog
                open={dialog === 'line_discount' && selectedLine !== null}
                title={t('pos.discount.line_title', {
                    name: selectedLine?.name ?? '',
                })}
                base={lineDiscountBase}
                current={selectedLine?.discount ?? { type: 'none', value: 0 }}
                capBp={capBp}
                onApply={(discount) => {
                    if (selectedLine) {
                        dispatch({
                            type: 'line_discount',
                            key: selectedLine.key,
                            discount,
                        });
                    }
                    closeDialog();
                }}
                onNeedApproval={() => setApprovalFor('pos.discount_override')}
                onClose={closeDialog}
            />
            <DiscountDialog
                open={dialog === 'bill_discount'}
                title={t('pos.discount.bill_title')}
                base={billDiscountBase}
                current={cart.orderDiscount}
                capBp={capBp}
                onApply={(discount) => {
                    dispatch({ type: 'order_discount', discount });
                    closeDialog();
                }}
                onNeedApproval={() => setApprovalFor('pos.discount_override')}
                onClose={closeDialog}
            />
            <PaymentDialog
                open={dialog === 'pay'}
                total={total}
                methods={paymentMethods}
                customer={customer}
                approval={approval}
                autoPrint={settings.auto_print}
                onSubmit={pay}
                onNeedApproval={setApprovalFor}
                onAttachCustomer={() => setDialog('customer')}
                onClose={closeDialog}
            />
            <ManagerDialog
                permission={approvalFor}
                onApproved={(a) => {
                    dispatch({ type: 'approval', approval: a });
                    setApprovalFor(null);
                    toast.success(
                        t('pos.manager.approved_by', { name: a.managerName }),
                    );
                }}
                onClose={() => setApprovalFor(null)}
            />
            <PriceCheckDialog
                open={dialog === 'price'}
                initial={dialog === 'price' ? query : ''}
                onClose={closeDialog}
            />
            <TodayDialog
                open={dialog === 'today'}
                registerId={registerId}
                receiptUrl={receiptUrl}
                onClose={closeDialog}
            />
            <HeldDialog
                open={dialog === 'held'}
                held={held}
                totalOf={(h) => cartTotals(h.state, settings).order.grand_total}
                onResume={resume}
                onDiscard={(id) => updateHeld(held.filter((h) => h.id !== id))}
                onClose={closeDialog}
            />
            <ConfirmDialog
                open={dialog === 'clear'}
                onOpenChange={(open) => !open && closeDialog()}
                title={t('pos.clear.title')}
                description={t('pos.clear.description', {
                    count: cart.lines.length,
                })}
                confirmLabel={t('pos.clear.confirm')}
                destructive
                onConfirm={() => {
                    dispatch({ type: 'clear', uuid: newClientUuid() });
                    focusScan();
                }}
            />
        </>
    );
}

function Completed({
    sale,
    canPrint,
    onPrint,
    onNew,
}: {
    sale: CompletedSale;
    canPrint: boolean;
    onPrint: () => void;
    onNew: () => void;
}) {
    const { t } = useT();
    const d = useDigits();

    return (
        <div
            className="mx-auto mt-8 max-w-md rounded-xl border bg-surface p-6 text-center shadow-sm"
            aria-live="polite"
        >
            <CircleCheck
                className="mx-auto size-12 text-success"
                aria-hidden="true"
            />
            <h2 className="mt-2 text-xl font-bold">{t('pos.done.title')}</h2>
            <p className="font-mono text-muted-foreground">{sale.number}</p>
            <dl className="num mt-4 grid grid-cols-2 gap-y-1 text-left text-sm">
                <dt>{t('pos.totals.total')}</dt>
                <dd className="text-right font-semibold">
                    {d.money(sale.total)}
                </dd>
                <dt>{t('pos.pay.tendered')}</dt>
                <dd className="text-right">
                    {d.money(sale.paid + sale.change)}
                </dd>
                <dt className="text-lg font-bold">{t('pos.pay.change')}</dt>
                <dd className="text-right text-2xl font-bold text-success">
                    {d.money(sale.change)}
                </dd>
                {sale.due > 0 && (
                    <>
                        <dt className="font-semibold text-warning">
                            {t('pos.pay.due')}
                        </dt>
                        <dd className="text-right font-semibold text-warning">
                            {d.money(sale.due)}
                        </dd>
                    </>
                )}
            </dl>
            <div className="mt-6 flex justify-center gap-2">
                {canPrint && (
                    <Button
                        type="button"
                        variant="outline"
                        className="min-h-11"
                        onClick={onPrint}
                    >
                        <Printer aria-hidden="true" /> {t('pos.done.print')}{' '}
                        <Kbd keys="mod+p" onButton />
                    </Button>
                )}
                <Button type="button" className="min-h-11" onClick={onNew}>
                    {t('pos.done.new')} <Kbd keys="f1" onButton />
                </Button>
            </div>
        </div>
    );
}
