import {
    cleanup,
    fireEvent,
    render,
    screen,
    waitFor,
} from "@testing-library/react";
import { afterEach, beforeAll, describe, expect, it, vi } from "vite-plus/test";
import { WhatsAppReminderDialog } from "@/components/messaging/whatsapp-reminder-dialog";
import { i18n } from "@/i18n";
import type { MessagingProps } from "@/types/messaging";

vi.mock("@inertiajs/react", () => ({
    Link: ({ children, href }: { children: React.ReactNode; href: string }) => (
        <a href={href}>{children}</a>
    ),
}));
vi.mock("sonner", () => ({ toast: { success: vi.fn(), error: vi.fn() } }));

const t = (key: string, options: Record<string, unknown> = {}): string =>
    i18n.t(key, options);

const DEFAULT_TEXT =
    "প্রিয় {name}, {shop}-এ আপনার পূর্বের বকেয়া {due} টাকা পরিশোধের জন্য বিনীত অনুরোধ জানানো হচ্ছে। ধন্যবাদ, {shop} ({shop_phone})।";

function messaging(
    over: Partial<MessagingProps["whatsapp"]> = {},
): MessagingProps {
    return {
        can_send: true,
        whatsapp: {
            driver: "click_to_chat",
            status: "click_to_chat",
            configured: true,
            bulk: false,
            ...over,
        },
        sms: { configured: true },
        shop: "Different Fashion",
        shop_phone: "01711223344",
        defaults: {
            due_reminder: DEFAULT_TEXT,
            payment_received: "Thanks {name}, {amount}.",
        },
    };
}

const rahim = {
    uuid: "u-1",
    name: "Rahim Uddin",
    phone: "+8801712345678",
    due: 248_200_00,
};
const karim = {
    uuid: "u-2",
    name: "Karim",
    phone: "+8801812345678",
    due: 50_000,
};

beforeAll(() => {
    globalThis.ResizeObserver ??= class {
        observe() {}
        unobserve() {}
        disconnect() {}
    } as unknown as typeof ResizeObserver;
    Element.prototype.scrollIntoView = () => undefined;
});

afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
});

describe("WhatsAppReminderDialog", () => {
    it("shows the customer, the due, the editable Bangla template and a filled preview", () => {
        render(
            <WhatsAppReminderDialog
                open
                onOpenChange={() => undefined}
                messaging={messaging()}
                recipient={rahim}
            />,
        );

        expect(screen.getByText("Rahim Uddin")).toBeTruthy();
        const area = screen.getByLabelText(
            t("sms.whatsapp.text"),
        ) as HTMLTextAreaElement;
        expect(area.value).toBe(DEFAULT_TEXT);
        expect(
            screen.getByText(
                /Different Fashion-এ আপনার পূর্বের বকেয়া ৳?\s?2,48,200\.00/,
            ),
        ).toBeTruthy();
        expect(
            screen.getByRole("button", {
                name: new RegExp(t("sms.whatsapp.send")),
            }),
        ).toBeTruthy();
        // No bulk pool: no dispatch target selector.
        expect(screen.queryByRole("radiogroup")).toBeNull();
    });

    it("inserts a tag at the caret", () => {
        render(
            <WhatsAppReminderDialog
                open
                onOpenChange={() => undefined}
                messaging={messaging()}
                recipient={rahim}
            />,
        );
        const area = screen.getByLabelText(
            t("sms.whatsapp.text"),
        ) as HTMLTextAreaElement;
        fireEvent.change(area, { target: { value: "Hi " } });
        area.setSelectionRange(3, 3);

        fireEvent.click(
            screen.getByRole("button", {
                name: t("sms.hub.insert_variable", { name: "{last_invoice}" }),
            }),
        );

        expect(area.value).toBe("Hi {last_invoice}");
    });

    it("offers Bulk only when the provider can send unattended", () => {
        const { unmount } = render(
            <WhatsAppReminderDialog
                open
                onOpenChange={() => undefined}
                messaging={messaging()}
                recipient={rahim}
                bulk={[rahim, karim]}
            />,
        );
        const bulk = screen.getByRole("button", {
            name: t("sms.whatsapp.bulk_all", { n: "2" }),
        });
        expect(bulk.getAttribute("aria-disabled")).toBe("true");
        expect(bulk.getAttribute("title")).toBe(
            t("sms.whatsapp.bulk_needs_api"),
        );
        unmount();

        render(
            <WhatsAppReminderDialog
                open
                onOpenChange={() => undefined}
                messaging={messaging({
                    driver: "meta_cloud",
                    status: "connected",
                    bulk: true,
                })}
                recipient={rahim}
                bulk={[rahim, karim]}
            />,
        );
        fireEvent.click(
            screen.getByRole("button", {
                name: t("sms.whatsapp.bulk_all", { n: "2" }),
            }),
        );
        expect(
            screen.getByText(t("sms.whatsapp.bulk_title", { n: "2" })),
        ).toBeTruthy();
        expect(
            screen.getByRole("button", {
                name: (name) =>
                    name.includes(t("sms.whatsapp.send_all", { n: "2" })),
            }),
        ).toBeTruthy();
    });

    it("sends through /app/messages/direct and points the pre-opened tab at the wa.me link", async () => {
        const tab = {
            location: { href: "" },
            close: vi.fn(),
        } as unknown as Window;
        vi.spyOn(window, "open").mockReturnValue(tab);
        const fetchMock = vi.spyOn(globalThis, "fetch").mockResolvedValue(
            new Response(
                JSON.stringify({
                    queued: 1,
                    skipped: 0,
                    handoff: [
                        {
                            customer: "u-1",
                            name: "Rahim Uddin",
                            phone: "+8801712345678",
                            url: "https://wa.me/8801712345678?text=Hi",
                        },
                    ],
                }),
                {
                    status: 200,
                    headers: { "Content-Type": "application/json" },
                },
            ),
        );
        const onOpenChange = vi.fn();
        const onSent = vi.fn();

        render(
            <WhatsAppReminderDialog
                open
                onOpenChange={onOpenChange}
                messaging={messaging()}
                recipient={rahim}
                onSent={onSent}
            />,
        );
        fireEvent.click(
            screen.getByRole("button", {
                name: new RegExp(t("sms.whatsapp.send")),
            }),
        );

        await waitFor(() => expect(onSent).toHaveBeenCalled());
        const [url, init] = fetchMock.mock.calls[0] as [string, RequestInit];
        expect(url).toBe("/app/messages/direct");
        expect(JSON.parse(init.body as string)).toEqual({
            channel: "whatsapp",
            event: "due_reminder",
            customers: ["u-1"],
            body: DEFAULT_TEXT,
            values: {},
        });
        expect(tab.location.href).toBe("https://wa.me/8801712345678?text=Hi");
        expect(onOpenChange).toHaveBeenCalledWith(false);
    });

    it("shows the server message on a 422 and closes the pre-opened tab", async () => {
        const close = vi.fn();
        const tab = {
            location: { href: "" },
            close,
        } as unknown as Window;
        vi.spyOn(window, "open").mockReturnValue(tab);
        vi.spyOn(globalThis, "fetch").mockResolvedValue(
            new Response(
                JSON.stringify({
                    message: "Nope: click-to-chat is single only.",
                }),
                {
                    status: 422,
                    headers: { "Content-Type": "application/json" },
                },
            ),
        );

        render(
            <WhatsAppReminderDialog
                open
                onOpenChange={() => undefined}
                messaging={messaging()}
                recipient={rahim}
            />,
        );
        fireEvent.click(
            screen.getByRole("button", {
                name: new RegExp(t("sms.whatsapp.send")),
            }),
        );

        await waitFor(() =>
            expect(screen.getByRole("alert").textContent).toBe(
                "Nope: click-to-chat is single only.",
            ),
        );
        expect(close).toHaveBeenCalled();
    });

    it("says so and disables Send when no WhatsApp provider is configured", () => {
        render(
            <WhatsAppReminderDialog
                open
                onOpenChange={() => undefined}
                messaging={messaging({
                    driver: "null",
                    status: "off",
                    configured: false,
                })}
                recipient={rahim}
            />,
        );

        expect(screen.getByRole("alert").textContent).toContain(
            t("sms.whatsapp.not_configured"),
        );
        expect(
            (
                screen.getByRole("button", {
                    name: new RegExp(t("sms.whatsapp.send")),
                }) as HTMLButtonElement
            ).disabled,
        ).toBe(true);
    });
});
