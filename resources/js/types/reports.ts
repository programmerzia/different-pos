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

export type DashboardKpi = {
    today: number;
    yesterday: number;
    month: number;
    last_month: number;
};

export type DashboardProps = {
    generated_at: string;
    kpis: {
        sales: DashboardKpi;
        invoices: DashboardKpi;
        average: DashboardKpi;
        collections: DashboardKpi;
        open_dues: number;
        low_stock: number;
    };
    trend: { date: string; net: number }[];
    top_customers: { customer: string; invoices: number; net: number }[];
    top_products: { product: string; qty: number; sales: number }[];
    aging: {
        d30: number;
        d60: number;
        d90: number;
        d90_plus: number;
        total: number;
    };
    low_stock: {
        product: string;
        sku: string;
        on_hand: number;
        reorder: number;
    }[];
};
