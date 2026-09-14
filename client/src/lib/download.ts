/**
 * Save an axios blob response as a file download.
 *
 * One shared implementation for every export button — extracted from the
 * duplicated logic in features/audit and features/rbac. The server's
 * Content-Disposition filename wins when exposed; otherwise the caller's
 * fallback name is used.
 */
export function saveBlobResponse(
    response: { data: unknown; headers?: Record<string, unknown> },
    fallbackFilename: string,
    fallbackMimeType: string,
): void {
    const blob =
        response.data instanceof Blob
            ? response.data
            : new Blob([response.data as BlobPart], {
                  type: fallbackMimeType,
              });

    const disposition = response.headers?.["content-disposition"] as
        | string
        | undefined;
    const serverName = disposition
        ?.match(/filename="?([^";]+)"?/i)?.[1]
        ?.trim();

    const objectUrl = window.URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = objectUrl;
    link.download = serverName || fallbackFilename;
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(objectUrl);
}

/** `YYYY-MM-DD` stamp used by export filename fallbacks. */
export function exportDateStamp(): string {
    return new Date().toISOString().slice(0, 10);
}
