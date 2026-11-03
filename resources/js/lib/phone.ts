/**
 * Display form of a stored Bangladeshi mobile number (E.164 `+8801711234567` → `01711-234567`, the
 * mockup's style). Anything that is not a BD mobile number is returned unchanged.
 */
export function displayPhone(phone: string | null | undefined): string {
    if (!phone) {
        return '';
    }
    const digits = phone.replace(/\D/g, '');
    const national = digits.startsWith('880') ? `0${digits.slice(3)}` : digits;
    if (/^01[3-9]\d{8}$/.test(national)) {
        return `${national.slice(0, 5)}-${national.slice(5)}`;
    }

    return phone;
}
