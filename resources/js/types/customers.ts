export type CustomerRow = {
    id: string;
    uuid: string;
    customer_no: string | null;
    name: string;
    name_bn: string | null;
    shop_name: string | null;
    phone: string | null;
    group: string | null;
    /** Paisa; positive = the customer owes. */
    balance: number;
    last_purchase_at: string | null;
    card_status: string | null;
    is_active: boolean;
};

export type CustomerDetail = {
    uuid: string;
    customer_no: string | null;
    name: string;
    name_bn: string | null;
    shop_name: string | null;
    phone: string | null;
    alt_phone: string | null;
    email: string | null;
    customer_group_id: number | null;
    group: string | null;
    credit_limit: number | null;
    balance: number;
    address_line: string | null;
    area: string | null;
    post_code: string | null;
    dob: string | null;
    anniversary_date: string | null;
    notes: string | null;
    member_since: string | null;
    last_purchase_at: string | null;
    is_active: boolean;
    is_walk_in: boolean;
};

export type CustomerCard = {
    id: number;
    card_code: string;
    status: string;
    issue_no: number;
    issued_at: string;
    print_count: number;
    member_since: string | null;
};

export type CardRow = {
    id: string;
    card_code: string;
    status: string;
    issue_no: number;
    issued_at: string;
    print_count: number;
    last_printed_at: string | null;
    customer_uuid: string | null;
    customer_no: string | null;
    name: string | null;
    shop_name: string | null;
};

export type TableMetaProp = {
    page: number;
    perPage: number;
    total: number;
    lastPage: number;
};

export type GroupOption = { id: number; name: string };
