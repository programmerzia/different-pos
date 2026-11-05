import { cleanup, fireEvent, render, screen } from "@testing-library/react";
import { afterEach, beforeAll, describe, expect, it, vi } from "vite-plus/test";
import { Composer, type ComposerConfig } from "@/components/messaging/composer";
import { i18n } from "@/i18n";

vi.mock("@inertiajs/react", () => ({
    router: { post: vi.fn() },
    Link: ({ children, href }: { children: React.ReactNode; href: string }) => (
        <a href={href}>{children}</a>
    ),
}));
vi.mock("sonner", () => ({ toast: { success: vi.fn(), error: vi.fn() } }));

const t = (key: string, options: Record<string, unknown> = {}): string =>
    i18n.t(key, options);

function config(over: Partial<ComposerConfig> = {}): ComposerConfig {
    return {
        configured: true,
        driver: "log",
        whatsapp: {
            driver: "meta_cloud",
            status: "connected",
            configured: true,
            bulk: true,
        },
        sending_enabled: true,
        cost_per_segment: 35,
        shop: "Different Fashion",
        max_body: 1000,
        variables: ["name", "shop", "due", "number"],
        templates: [],
        counts: { all: 10, due: 4 },
        groups: [],
        ...over,
    };
}

beforeAll(() => {
    globalThis.ResizeObserver ??= class {
        observe() {}
        unobserve() {}
        disconnect() {}
    } as unknown as typeof ResizeObserver;
});

afterEach(cleanup);

describe("Composer channel toggle", () => {
    it("says SMS sending is off instead of asking for a provider", () => {
        render(<Composer config={config({ sending_enabled: false })} />);
        expect(screen.getByText(t("sms.hub.sending_off"))).toBeTruthy();
        expect(screen.queryByText(t("sms.hub.not_configured"))).toBeNull();
    });

    it("starts on SMS, switches to WhatsApp and drops the per-part cost", () => {
        render(<Composer config={config()} />);
        const wa = screen.getByRole("button", { name: t("sms.hub.whatsapp") });
        const sms = screen.getByRole("button", { name: t("sms.hub.sms") });
        expect(sms.getAttribute("aria-pressed")).toBe("true");
        expect(
            screen.getByText(t("sms.hub.cost_estimate"), { exact: false }),
        ).toBeTruthy();

        fireEvent.click(wa);

        expect(wa.getAttribute("aria-pressed")).toBe("true");
        expect(sms.getAttribute("aria-pressed")).toBe("false");
        expect(
            screen.queryByText(t("sms.hub.cost_estimate"), { exact: false }),
        ).toBeNull();
        expect(
            screen.getAllByText(t("sms.whatsapp.composer_no_cost")).length,
        ).toBeGreaterThan(0);
    });

    it("with click-to-chat allows only the Single target and explains why", () => {
        render(
            <Composer
                config={config({
                    whatsapp: {
                        driver: "click_to_chat",
                        status: "click_to_chat",
                        configured: true,
                        bulk: false,
                    },
                })}
            />,
        );
        fireEvent.click(
            screen.getByRole("button", { name: t("sms.hub.whatsapp") }),
        );
        fireEvent.change(screen.getByLabelText(t("sms.hub.body")), {
            target: { value: "Hello" },
        });

        expect(
            screen.getByText(t("sms.whatsapp.composer_click_single")),
        ).toBeTruthy();
        // Bulk target "all customers with a due" stays selected: Send is disabled under click-to-chat.
        const send = screen.getByRole("button", {
            name: new RegExp(t("sms.hub.send_many", { n: "4" })),
        }) as HTMLButtonElement;
        expect(send.disabled).toBe(true);
    });

    it("warns when WhatsApp is not configured and SMS is", () => {
        render(
            <Composer
                config={config({
                    whatsapp: {
                        driver: "null",
                        status: "off",
                        configured: false,
                        bulk: false,
                    },
                })}
            />,
        );
        expect(screen.queryByRole("alert")).toBeNull();

        fireEvent.click(
            screen.getByRole("button", { name: t("sms.hub.whatsapp") }),
        );

        expect(screen.getByRole("alert").textContent).toContain(
            t("sms.whatsapp.not_configured"),
        );
    });
});
