import * as React from "react";
import {
    IconFile,
    IconFileText,
    IconFileTypePdf,
    IconFileSpreadsheet,
    IconFileTypeZip,
    IconPhoto,
} from "@tabler/icons-react";

// ─── File Colors ──────────────────────────────────────────────────────────────

const FILE_COLOR_RULES: Array<{
    pattern: RegExp;
    classes: string;
}> = [
    {
        pattern: /pdf/,
        classes:
            "bg-red-50 text-red-600 border-red-200 dark:bg-red-950/50 dark:text-red-400 dark:border-red-800",
    },
    {
        pattern: /msword|officedocument\.wordprocessingml/,
        classes:
            "bg-blue-50 text-blue-600 border-blue-200 dark:bg-blue-950/50 dark:text-blue-400 dark:border-blue-800",
    },
    {
        pattern: /officedocument\.spreadsheetml|excel/,
        classes:
            "bg-emerald-50 text-emerald-600 border-emerald-200 dark:bg-emerald-950/50 dark:text-emerald-400 dark:border-emerald-800",
    },
    {
        pattern: /^image\//,
        classes:
            "bg-purple-50 text-purple-600 border-purple-200 dark:bg-purple-950/50 dark:text-purple-400 dark:border-purple-800",
    },
    {
        pattern: /csv|text\//,
        classes:
            "bg-slate-50 text-slate-600 border-slate-200 dark:bg-slate-950/50 dark:text-slate-400 dark:border-slate-800",
    },
    {
        pattern: /zip|rar|compressed|archive/,
        classes:
            "bg-amber-50 text-amber-600 border-amber-200 dark:bg-amber-950/50 dark:text-amber-400 dark:border-amber-800",
    },
];

const DEFAULT_CLASSES = "bg-muted text-muted-foreground border-border";

export function getFileColorClasses(mimeType: string): string {
    const match = FILE_COLOR_RULES.find(({ pattern }) =>
        pattern.test(mimeType),
    );
    return match?.classes ?? DEFAULT_CLASSES;
}

// ─── File Icons ───────────────────────────────────────────────────────────────

const FILE_ICON_RULES: Array<{
    pattern: RegExp;
    Icon: React.ComponentType<{ className?: string }>;
}> = [
    { pattern: /^image\//, Icon: IconPhoto },
    { pattern: /pdf/, Icon: IconFileTypePdf },
    { pattern: /msword|officedocument\.wordprocessingml/, Icon: IconFileText },
    {
        pattern: /officedocument\.spreadsheetml|excel/,
        Icon: IconFileSpreadsheet,
    },
    { pattern: /zip|rar|compressed/, Icon: IconFileTypeZip },
];

export function getFileIcon(
    mimeType: string,
    className?: string,
): React.ReactNode {
    const match = FILE_ICON_RULES.find(({ pattern }) => pattern.test(mimeType));
    const Icon = match?.Icon ?? IconFile;

    return <Icon className={className ?? "size-4"} />;
}

// ─── File Size ────────────────────────────────────────────────────────────────

export function formatBytes(bytes: number): string {
    if (bytes === 0) return "0 بایت";

    const units = ["بایت", "کیلوبایت", "مگابایت", "گیگابایت"];
    const index = Math.min(
        Math.floor(Math.log(bytes) / Math.log(1024)),
        units.length - 1,
    );

    return `${(bytes / 1024 ** index).toFixed(index === 0 ? 0 : 1)} ${units[index]}`;
}

// ─── File Type Labels ─────────────────────────────────────────────────────────

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
