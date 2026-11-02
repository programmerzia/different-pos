import { apiRequest } from '@/lib/api';
import type { Discount } from '@/engine/pricing';
import type {
    CartState,
    CatalogItem,
    CompletedSale,
    CustomerCard,
    CustomerHit,
    TodaySummary,
} from './types';
import type { TenderRow } from './tenders';

export const POS_API = {
    products: '/app/api/pos/products',
    customers: '/app/api/pos/customers',
    today: '/app/api/pos/today',
    authorize: '/app/api/pos/authorize',
    sales: '/app/pos/sales',
} as const;

/** A 422 from the register endpoints: translated headline, server detail, stable code. */
export type PosError = {
    status: number;
    message: string;
    detail?: string;
    code?: string;
    errors?: Record<string, string[]>;
};

function xsrf(): string {
    const match = /(?:^|;\s*)XSRF-TOKEN=([^;]+)/.exec(document.cookie);

    return match?.[1] ? decodeURIComponent(match[1]) : '';
}

/** POST that hands back the JSON body for errors too (apiRequest only keeps the status). */
export async function postJson<T>(
    url: string,
    body: unknown,
): Promise<
    { ok: true; status: number; data: T } | { ok: false; error: PosError }
> {
    let response: Response;
    try {
        response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': xsrf(),
            },
            body: JSON.stringify(body),
        });
    } catch {
        return { ok: false, error: { status: 0, message: '' } };
    }

    const json = (await response.json().catch(() => ({}))) as Record<
        string,
        unknown
    >;
    if (response.ok) {
        return { ok: true, status: response.status, data: json as T };
    }

    return {
        ok: false,
        error: {
            status: response.status,
            message: typeof json['message'] === 'string' ? json['message'] : '',
            ...(typeof json['detail'] === 'string'
                ? { detail: json['detail'] }
                : {}),
            ...(typeof json['code'] === 'string' ? { code: json['code'] } : {}),
            ...(typeof json['errors'] === 'object' && json['errors'] !== null
                ? { errors: json['errors'] as Record<string, string[]> }
                : {}),
        },
    };
}

export function searchProducts(
    q: string,
    category: number | null,
    signal?: AbortSignal,
): Promise<{ exact: boolean; items: CatalogItem[] }> {
    const params = new URLSearchParams();
    if (q !== '') {
        params.set('q', q);
    }
    if (category !== null) {
        params.set('category', String(category));
    }

    return apiRequest(
        `${POS_API.products}?${params.toString()}`,
        signal ? { signal } : {},
    );
}

export function searchCustomers(
    q: string,
    signal?: AbortSignal,
): Promise<{ customers: CustomerHit[] }> {
    return apiRequest(
        `${POS_API.customers}?q=${encodeURIComponent(q)}`,
        signal ? { signal } : {},
    );
}

export function customerCard(
    uuid: string,
): Promise<{ customer: CustomerCard }> {
    return apiRequest(`${POS_API.customers}/${encodeURIComponent(uuid)}`);
}

export function todaySummary(registerId: number | null): Promise<TodaySummary> {
    return apiRequest(
        `${POS_API.today}${registerId === null ? '' : `?register_id=${registerId}`}`,
    );
}

export type ApprovalResponse = {
    token: string;
    manager: { id: number; name: string };
    discount_cap_bp: number;
    expires_in: number;
};

export function requestApproval(
    login: string,
    secret: string,
    permission: string,
) {
    return postJson<ApprovalResponse>(POS_API.authorize, {
        login,
        secret,
        permission,
    });
}

const wireDiscount = (d: Discount) => ({ type: d.type, value: d.value });

/** The request body for POST /app/pos/sales. */
export function saleBody(
    state: CartState,
    tenders: readonly TenderRow[],
    expectedTotal: number,
    registerId: number | null,
) {
    return {
        client_uuid: state.clientUuid,
        register_id: registerId,
        customer_id: state.customer?.id ?? null,
        lines: state.lines.map((l) => ({
            product_id: l.productId,
            qty: l.qty,
            discount: wireDiscount(l.discount),
        })),
        order_discount: wireDiscount(state.orderDiscount),
        tenders: tenders
            .filter((t) => t.amount > 0)
            .map((t) => ({
                payment_method_id: t.methodId,
                amount: t.amount,
                reference:
                    t.reference.trim() === '' ? null : t.reference.trim(),
            })),
        expected_total: expectedTotal,
        approval_token:
            state.approval && state.approval.expiresAt > Date.now()
                ? state.approval.token
                : null,
    };
}

export function submitSale(body: ReturnType<typeof saleBody>) {
    return postJson<{ sale: CompletedSale }>(POS_API.sales, body);
}
