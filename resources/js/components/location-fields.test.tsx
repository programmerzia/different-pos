import {
    act,
    cleanup,
    fireEvent,
    render,
    screen,
    waitFor,
} from "@testing-library/react";
import { useState } from "react";
import { afterEach, describe, expect, it, vi } from "vite-plus/test";
import {
    LocationFields,
    type LocationValue,
} from "@/components/location-fields";
import { i18n } from "@/i18n";
import { clearGeoCache } from "@/lib/geo";

const dhakaDiv = { id: 6, name_en: "Dhaka", name_bn: "ঢাকা" };
const rajshahi = { id: 2, name_en: "Rajshahi", name_bn: "রাজশাহী" };
const dhaka = { id: 47, name_en: "Dhaka", name_bn: "ঢাকা", division_id: 6 };
const joypurhat = {
    id: 20,
    name_en: "Joypurhat",
    name_bn: "জয়পুরহাট",
    division_id: 2,
};
const mirpur = {
    id: 1026,
    name_en: "Mirpur",
    name_bn: "মিরপুর",
    district_id: 47,
    type: "metro_thana",
    city_name_en: "Dhaka North City Corporation",
};
const kalai = {
    id: 161,
    name_en: "Kalai",
    name_bn: "কালাই",
    district_id: 20,
    type: "upazila",
    city_name_en: null,
};

const calls: string[] = [];

vi.mock("@/lib/api", () => ({
    apiRequest: vi.fn(async (url: string) => {
        calls.push(url);
        const u = new URL(url, "http://x");
        const parent = u.searchParams.get("parent");
        const q = (u.searchParams.get("q") ?? "").toLowerCase();
        if (u.pathname === "/app/api/geo/search") {
            const level = u.searchParams.get("level");
            if (level === "upazilas" && "mirpur".startsWith(q)) {
                return {
                    results: [
                        {
                            level: "upazilas",
                            ...mirpur,
                            chain: {
                                division: dhakaDiv,
                                district: dhaka,
                                upazila: mirpur,
                                union: null,
                            },
                        },
                    ],
                };
            }
            if (level === "districts" && "joypurhat".startsWith(q)) {
                return {
                    results: [
                        {
                            level: "districts",
                            ...joypurhat,
                            chain: {
                                division: rajshahi,
                                district: joypurhat,
                                upazila: null,
                                union: null,
                            },
                        },
                    ],
                };
            }

            return { results: [] };
        }
        if (u.pathname === "/app/api/geo/divisions") {
            return { divisions: [dhakaDiv, rajshahi] };
        }
        if (u.pathname === "/app/api/geo/districts") {
            return {
                districts: [dhaka, joypurhat].filter(
                    (d) => !parent || String(d.division_id) === parent,
                ),
            };
        }
        if (u.pathname === "/app/api/geo/upazilas") {
            return {
                upazilas: [mirpur, kalai].filter(
                    (d) => !parent || String(d.district_id) === parent,
                ),
            };
        }
        if (u.pathname === "/app/api/geo/post_codes") {
            return {
                post_codes:
                    parent === "1026"
                        ? [
                              {
                                  id: 1,
                                  post_code: "1216",
                                  post_office_en: "Mirpur",
                                  post_office_bn: null,
                              },
                              {
                                  id: 2,
                                  post_code: "1216",
                                  post_office_en: "Mirpur-2",
                                  post_office_bn: null,
                              },
                          ]
                        : [],
            };
        }

        return {};
    }),
}));

const empty: LocationValue = {
    geo_division_id: null,
    geo_district_id: null,
    geo_upazila_id: null,
    geo_union_id: null,
    post_code: "",
};

function Harness({ initial = empty }: { initial?: LocationValue }) {
    const [value, setValue] = useState(initial);

    return (
        <>
            <LocationFields
                value={value}
                onChange={(patch) => setValue((v) => ({ ...v, ...patch }))}
            />
            <pre data-testid="value">{JSON.stringify(value)}</pre>
        </>
    );
}

const t = (key: string) => i18n.t(key);
const box = (key: string) =>
    screen.getByRole("combobox", { name: new RegExp(`^${t(key)}`) });
const value = (): LocationValue =>
    JSON.parse(screen.getByTestId("value").textContent ?? "{}");

afterEach(() => {
    cleanup();
    clearGeoCache();
    calls.length = 0;
});

describe("LocationFields", () => {
    it("renders the four mockup fields in order", () => {
        render(<Harness />);
        const names = Array.from(document.querySelectorAll("input[name]")).map(
            (el) => el.getAttribute("name"),
        );
        expect(names).toEqual([
            "geo_division_id",
            "geo_district_id",
            "geo_upazila_id",
            "post_code",
        ]);
        expect(screen.getByLabelText(t("location.post_code"))).toHaveProperty(
            "inputMode",
            "numeric",
        );
    });

    it("typing an upazila and pressing Enter fills district and division, then loads post codes", async () => {
        render(<Harness />);
        const upazila = box("location.upazila");
        fireEvent.change(upazila, { target: { value: "Mir" } });
        await screen.findByRole("option", { name: /Mirpur/ });
        expect(
            screen.getByRole("option", { name: /Mirpur/ }).textContent,
        ).toContain("Dhaka");

        fireEvent.keyDown(upazila, { key: "Enter" });
        expect(value()).toMatchObject({
            geo_division_id: 6,
            geo_district_id: 47,
            geo_upazila_id: 1026,
        });
        expect((box("location.district") as HTMLInputElement).value).toBe(
            "Dhaka",
        );
        expect((box("location.division") as HTMLInputElement).value).toBe(
            "Dhaka",
        );
        expect((upazila as HTMLInputElement).value).toBe("Mirpur");
        await waitFor(() =>
            expect(
                calls.some((c) => c.includes("/geo/post_codes?parent=1026")),
            ).toBe(true),
        );
        await waitFor(() =>
            expect(document.querySelectorAll("datalist option")).toHaveLength(
                1,
            ),
        );
    });

    it("changing the division clears district and upazila; arrow keys browse the parent list", async () => {
        render(
            <Harness
                initial={{
                    ...empty,
                    geo_division_id: 6,
                    geo_district_id: 47,
                    geo_upazila_id: 1026,
                }}
            />,
        );
        const division = box("location.division");
        await waitFor(() =>
            expect((division as HTMLInputElement).value).toBe("Dhaka"),
        );

        fireEvent.keyDown(division, { key: "Backspace" });
        expect(value().geo_division_id).toBeNull();
        expect(value().geo_district_id).toBeNull();
        expect(value().geo_upazila_id).toBeNull();

        await screen.findByRole("option", { name: /Rajshahi/ });
        fireEvent.keyDown(division, { key: "ArrowDown" });
        fireEvent.keyDown(division, { key: "Enter" });
        expect(value().geo_division_id).toBe(2);
        expect((division as HTMLInputElement).value).toBe("Rajshahi");

        // The district list is now scoped to Rajshahi.
        const district = box("location.district");
        fireEvent.focus(district);
        await screen.findByRole("option", { name: /Joypurhat/ });
        expect(screen.queryByRole("option", { name: /^Dhaka/ })).toBeNull();
        expect(calls.some((c) => c === "/app/api/geo/districts?parent=2")).toBe(
            true,
        );
    });

    it("shows a server error under the field and Esc closes the list", async () => {
        render(
            <LocationFields
                value={empty}
                onChange={() => undefined}
                errors={{ geo_upazila_id: "Pick a matching upazila" }}
            />,
        );
        expect(screen.getByRole("alert").textContent).toContain(
            "Pick a matching upazila",
        );
        const upazila = box("location.upazila");
        expect(upazila.getAttribute("aria-invalid")).toBe("true");
        fireEvent.change(upazila, { target: { value: "Mir" } });
        await screen.findByRole("listbox");
        await act(async () => {
            fireEvent.keyDown(upazila, { key: "Escape" });
        });
        expect(screen.queryByRole("listbox")).toBeNull();
    });
});
