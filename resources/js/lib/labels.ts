import type { TFunction } from 'i18next';

/** Plain-language label of a permission code (roles.perm.<group>_<action>), falling back to the code. */
export function permissionLabel(t: TFunction, name: string): string {
    return t(`roles.perm.${name.replaceAll('.', '_')}`, { defaultValue: name });
}

/**
 * Label of an audit event code (audit.events.<code>, e.g. `sale.voided`), falling back to the code
 * for events without a translation yet (or a code that names a group rather than an event).
 */
export function auditEventLabel(t: TFunction, code: string): string {
    const label: unknown = t(`audit.events.${code}`, {
        defaultValue: code,
        returnObjects: false,
    });

    return typeof label === 'string' && !label.startsWith('key ')
        ? label
        : code;
}
