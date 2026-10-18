/**
 * Media helpers shared by the uploader and the admin (TECHNICAL_PLAN §6.6).
 */

export function formatBytes(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${Math.round(bytes / 1024)} KB`;
    }

    const mb = bytes / (1024 * 1024);

    return `${Number.isInteger(mb) ? mb : mb.toFixed(1)} MB`;
}

export function formatKilobytes(kb: number): string {
    return formatBytes(kb * 1024);
}
