const MIME_TYPE_LABELS: Array<{ pattern: RegExp; label: string }> = [
    { pattern: /pdf/, label: "PDF" },
    { pattern: /msword|officedocument\.wordprocessingml/, label: "Word" },
    {
        pattern: /officedocument\.spreadsheetml|excel/,
        label: "Excel",
    },
    { pattern: /csv|text\//, label: "Text" },
    { pattern: /^image\/jpeg/, label: "JPEG" },
    { pattern: /^image\/png/, label: "PNG" },
    { pattern: /^image\/gif/, label: "GIF" },
    { pattern: /^image\/webp/, label: "WebP" },
    { pattern: /^image\//, label: "Image" },
    { pattern: /zip|rar|compressed|archive/, label: "Archive" },
];

const DEFAULT_LABEL = "File";

export function getFileTypeLabel(mimeType: string): string {
    const match = MIME_TYPE_LABELS.find(({ pattern }) => pattern.test(mimeType));
    return match?.label ?? DEFAULT_LABEL;
}
