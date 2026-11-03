export type ColumnType = 'money' | 'qty' | 'int' | 'text' | 'date';

export type ReportColumn = {
    key: string;
    label: string;
    type: ColumnType;
    profit: boolean;
    /** Text cells hold a code that the UI translates as `{valuePrefix}{value}`. */
    valuePrefix: string | null;
};

export type ReportRow = Record<string, string | number | null>;

export type ReportResultProps = {
    columns: ReportColumn[];
    rows: ReportRow[];
    totals: Record<string, number> | null;
};

export type ReportParams = {
    from: string;
    to: string;
    branch: string;
    filters: Record<string, string>;
};

export type ReportShowProps = {
    report: { name: string; usesPeriod: boolean; filters: string[] };
    params: ReportParams;
    result: ReportResultProps;
    canAllBranches: boolean;
    canExport: boolean;
    options: {
        categories: { id: number; name: string }[];
        brands: { id: number; name: string }[];
        buckets: string[];
    };
};

/** A KPI value and the same figure for the comparison window. */
export type DashboardKpi = { value: number; previous: number };

export type DashboardPeriodKey =
    | 'today'
    | 'yesterday'
    | '7d'
    | '30d'
    | 'this_month'
    | 'custom';

export type DashboardTrendPoint = {
    /** `HH` (hourly) or `YYYY-MM-DD` (daily). */
    key: string;
    net: number;
    previous: number;
};

export type DashboardHeatCell = { dow: number; hour: number; count: number };

export type DashboardBranchRow = {
    id: number;
    code: string;
    name: string;
    sales: number;
    previous: number;
    /** Absent without reports.profit. */
    profit?: number;
};

export type DashboardSetup = {
    logo: boolean;
    payment_methods: boolean;
    products: boolean;
    customers: boolean;
    first_sale: boolean;
};

export type DashboardProps = {
    generated_at: string;
    period: {
        key: DashboardPeriodKey;
        from: string;
        to: string;
        previous_from: string;
        previous_to: string;
    };
    kpis: {
        sales: DashboardKpi;
        /** Absent without reports.profit. */
        profit?: DashboardKpi & { revenue: number };
        invoices: DashboardKpi;
        average: DashboardKpi;
        dues: { value: number; debtors: number };
        collections: DashboardKpi;
        returns: DashboardKpi & { count: number };
        /** Absent without reports.profit. */
        stock_value?: { value: number; skus: number };
        low_stock: { value: number };
    };
    trend: { granularity: 'hour' | 'day'; points: DashboardTrendPoint[] };
    payment_mix: { method: string; type: string; amount: number }[];
    heatmap: { from: string; to: string; cells: DashboardHeatCell[] };
    /** Null without reports.all_branches. */
    branches: DashboardBranchRow[] | null;
    aging: {
        d30: number;
        d60: number;
        d90: number;
        d90_plus: number;
        total: number;
    };
    top_products: {
        product: string;
        sku: string | null;
        qty: number;
        sales: number;
    }[];
    top_customers: {
        customer: string | null;
        customer_no: string | null;
        invoices: number;
        net: number;
    }[];
    low_stock: {
        product: string;
        sku: string | null;
        branch: string;
        on_hand: number;
        reorder: number;
        cost?: number;
    }[];
    recent_sales: {
        id: number;
        number: string | null;
        customer: string | null;
        walk_in: boolean;
        sold_at: string;
        total: number;
        payment_status: string | null;
    }[];
    debtors: {
        id: number;
        customer: string | null;
        customer_no: string | null;
        phone: string | null;
        due: number;
        days: number;
    }[];
    setup: DashboardSetup;
};

export type DashboardPageProps = {
    dashboard: DashboardProps;
    filters: {
        period: DashboardPeriodKey;
        from: string;
        to: string;
        branch: string;
    };
    branches: { id: number; code: string; name: string }[];
    currentBranch: { id: number; code: string; name: string };
    can: { allBranches: boolean; profit: boolean };
};
