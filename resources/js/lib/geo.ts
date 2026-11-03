import { apiRequest } from "@/lib/api";

/** One place of the Bangladesh geo reference (/app/api/geo/*). */
export type GeoPlace = { id: number; name_en: string; name_bn: string };

export type GeoChain = {
    division: GeoPlace | null;
    district: GeoPlace | null;
    upazila: GeoPlace | null;
    union: GeoPlace | null;
};

export type GeoLevel = "divisions" | "districts" | "upazilas" | "unions";

export type GeoOption = GeoPlace & {
    type?: string;
    city_name_en?: string | null;
};

export type GeoSearchHit = GeoOption & {
    level: GeoLevel | "post_codes";
    post_code?: string;
    chain: GeoChain;
};

export type GeoPostCode = {
    id: number;
    post_code: string;
    post_office_en: string;
    post_office_bn: string | null;
};

/** Saved location ids as the customer form keeps them. */
export type GeoIds = {
    geo_division_id: number | null;
    geo_district_id: number | null;
    geo_upazila_id: number | null;
    geo_union_id: number | null;
};

export const GEO_LEVELS: readonly GeoLevel[] = [
    "divisions",
    "districts",
    "upazilas",
    "unions",
];

const cache = new Map<string, Promise<unknown>>();

function cached<T>(key: string, load: () => Promise<T>): Promise<T> {
    let hit = cache.get(key) as Promise<T> | undefined;
    if (!hit) {
        hit = load().catch((error: unknown) => {
            cache.delete(key);
            throw error;
        });
        cache.set(key, hit);
    }

    return hit;
}

/** Tests reset the memo between cases. */
export function clearGeoCache(): void {
    cache.clear();
}

function list<T>(data: unknown, key: string): T[] {
    const rows =
        data && typeof data === "object"
            ? (data as Record<string, unknown>)[key]
            : undefined;

    return Array.isArray(rows) ? (rows as T[]) : [];
}

/** The rows of one level under a parent (divisions ignore the parent). Memoised per level + parent. */
export function loadGeoLevel(
    level: GeoLevel,
    parent: number | null,
): Promise<GeoOption[]> {
    const query = parent !== null ? `?parent=${parent}` : "";

    return cached(`${level}:${parent ?? ""}`, async () =>
        list<GeoOption>(
            await apiRequest<unknown>(`/app/api/geo/${level}${query}`),
            level,
        ),
    );
}

/** Post offices of an upazila (the server falls back to the district when the upazila has none). */
export function loadPostCodes(upazila: number): Promise<GeoPostCode[]> {
    return cached(`post_codes:${upazila}`, async () =>
        list<GeoPostCode>(
            await apiRequest<unknown>(
                `/app/api/geo/post_codes?parent=${upazila}`,
            ),
            "post_codes",
        ),
    );
}

/** Cross-level search (EN, BN, old spellings); each hit carries its parent chain. */
export async function searchGeo(
    q: string,
    level: GeoLevel,
    parent: number | null,
    signal?: AbortSignal,
): Promise<GeoSearchHit[]> {
    const params = new URLSearchParams({ q, level });
    if (parent !== null) {
        params.set("parent", String(parent));
    }

    return list<GeoSearchHit>(
        await apiRequest<unknown>(
            `/app/api/geo/search?${params.toString()}`,
            signal ? { signal } : {},
        ),
        "results",
    );
}

/** The name in the UI language (falls back to English). */
export function geoName(
    place: GeoPlace | null | undefined,
    locale: "en" | "bn",
): string {
    if (!place) {
        return "";
    }

    return locale === "bn" && place.name_bn ? place.name_bn : place.name_en;
}

/** "Upazila, District" (or whichever is known) for list rows and address lines. */
export function geoLabel(
    chain: Pick<GeoChain, "district" | "upazila"> | null | undefined,
    locale: "en" | "bn",
): string {
    if (!chain) {
        return "";
    }
    const upazila = geoName(chain.upazila, locale);
    const district = geoName(chain.district, locale);

    return [upazila, upazila === district ? "" : district]
        .filter(Boolean)
        .join(", ");
}
