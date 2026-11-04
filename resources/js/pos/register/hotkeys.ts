import type { HotkeySpec } from '@/hotkeys/use-hotkeys';

export const POS_PATH = '/app/pos';
/** The shell's global F1 fires this while the register is open (F1 = new sale / scan). */
export const POS_F1_EVENT = 'pos:f1';
/** The register's own keys are delivered to the page as this event (detail = PosCommand). */
export const POS_COMMAND_EVENT = 'pos:command';

export type PosCommand =
    | 'search'
    | 'browse'
    | 'qty'
    | 'customer'
    | 'member_pin'
    | 'price_check'
    | 'line_discount'
    | 'bill_discount'
    | 'today'
    | 'hold'
    | 'held'
    | 'pay'
    | 'returns'
    | 'collect_due'
    | 'reprint'
    | 'chalan'
    | 'cancel'
    | 'remove'
    | 'up'
    | 'down';

type Def = {
    command: PosCommand;
    keys: string;
    labelKey: string;
    allowInInput?: boolean;
};

/** The register's keys (scope `pos`). F1 is the shell's global key, routed here by POS_F1_EVENT. */
export const POS_KEYS: readonly Def[] = [
    { command: 'search', keys: 'f2', labelKey: 'pos.keys.search' },
    // Browse catalogue (the product grid over the bill); Alt+G = "grid", also from the scan field.
    { command: 'browse', keys: 'alt+g', labelKey: 'pos.keys.browse' },
    { command: 'qty', keys: 'f3', labelKey: 'pos.keys.qty' },
    { command: 'customer', keys: 'f4', labelKey: 'pos.keys.customer' },
    // Member PIN: verify / set the attached customer's PIN (02_AppFlow §4.4).
    { command: 'member_pin', keys: 'alt+p', labelKey: 'pos.keys.member_pin' },
    { command: 'price_check', keys: 'f5', labelKey: 'pos.keys.price_check' },
    {
        command: 'line_discount',
        keys: 'f6',
        labelKey: 'pos.keys.line_discount',
    },
    {
        command: 'bill_discount',
        keys: 'shift+f6',
        labelKey: 'pos.keys.bill_discount',
    },
    { command: 'today', keys: 'f7', labelKey: 'pos.keys.today' },
    { command: 'hold', keys: 'f8', labelKey: 'pos.keys.hold' },
    { command: 'held', keys: 'shift+f8', labelKey: 'pos.keys.held' },
    { command: 'pay', keys: 'f9', labelKey: 'pos.keys.pay' },
    { command: 'returns', keys: 'f11', labelKey: 'pos.keys.returns' },
    {
        command: 'collect_due',
        keys: 'mod+d',
        labelKey: 'pos.keys.collect_due',
    },
    { command: 'reprint', keys: 'mod+p', labelKey: 'pos.keys.reprint' },
    // Delivery chalan of the last sale (A4, no prices).
    { command: 'chalan', keys: 'alt+c', labelKey: 'pos.keys.chalan' },
    { command: 'cancel', keys: 'escape', labelKey: 'pos.keys.cancel' },
    { command: 'remove', keys: 'delete', labelKey: 'pos.keys.remove' },
    { command: 'up', keys: 'arrowup', labelKey: 'pos.keys.up' },
    { command: 'down', keys: 'arrowdown', labelKey: 'pos.keys.down' },
];

export function posHotkeySpecs(
    run: (command: PosCommand, event: KeyboardEvent) => void,
): HotkeySpec[] {
    return POS_KEYS.map((def) => ({
        id: `pos.${def.command}`,
        keys: def.keys,
        labelKey: def.labelKey,
        ...(def.allowInInput ? { allowInInput: true } : {}),
        handler: (event: KeyboardEvent) => run(def.command, event),
    }));
}
