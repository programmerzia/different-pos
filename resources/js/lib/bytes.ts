/** "14.2 MB": binary units, one decimal above bytes; `n` renders digits in the user's script. */
export function formatBytes(
    bytes: number | null,
    n: (value: number | string) => string = String,
): string {
    if (bytes === null) {
        return '-';
    }
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    let value = bytes;
    let unit = 0;
    while (value >= 1024 && unit < units.length - 1) {
        value /= 1024;
        unit++;
    }

    return `${n(unit === 0 ? value : value.toFixed(1))} ${units[unit]}`;
}
