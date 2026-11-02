import { apiRequest } from '@/lib/api';
import { search } from '@/routes/api';

export type SearchHit = {
    id: number;
    uuid: string;
    label: string;
    sub?: string;
    price?: number;
    total?: number;
};

export type SearchResults = {
    products: SearchHit[];
    customers: SearchHit[];
    sales: SearchHit[];
};

export const EMPTY_RESULTS: SearchResults = {
    products: [],
    customers: [],
    sales: [],
};

/** GET /app/api/search?q= : server already filters by the caller's permissions and tenant. */
export function searchApi(
    q: string,
    signal?: AbortSignal,
): Promise<SearchResults> {
    return apiRequest<SearchResults>(
        search.url({ query: { q } }),
        signal ? { signal } : {},
    );
}

/** Detail pages (route table 02_AppFlow §2: products.show, customers.show, sales.show). */
export const hitHref = {
    product: (hit: SearchHit) => `/app/products/${hit.id}`,
    customer: (hit: SearchHit) => `/app/customers/${hit.id}`,
    sale: (hit: SearchHit) => `/app/sales/${hit.id}`,
};
