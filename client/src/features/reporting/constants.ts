/** Labels/variants for the unified inbox (کارتابل) vocabulary. */

/** Known source keys; unknown keys fall back to the raw key. */
export const SOURCE_LABELS: Record<string, string> = {
    automation: "اتوماسیون اداری",
    rahkaran: "راهکاران",
    bizagi: "بیزگی",
};

export const ALL_SOURCES = Object.keys(SOURCE_LABELS);

export function sourceLabel(key: string): string {
    return SOURCE_LABELS[key] ?? key;
}

/** Unified cartable buckets (mirrors the external mappers' translation). */
export const CARTABLE_ACTION_LABELS: Record<string, string> = {
    action_required: "جهت اقدام",
    signature: "جهت امضا",
    information: "جهت اطلاع",
};

export const STATUS_CONFIG: Record<
    string,
    { label: string; variant: "secondary" | "warning" | "success" }
> = {
    unseen: { label: "دیده نشده", variant: "warning" },
    seen: { label: "دیده شده", variant: "secondary" },
    done: { label: "انجام شده", variant: "success" },
};

/** Known unified document types; the list grows as real drivers arrive. */
export const DOCUMENT_TYPE_LABELS: Record<string, string> = {
    letter: "نامه",
    request: "درخواست",
    task: "تسک",
};

export const DOCUMENT_TYPES = Object.keys(DOCUMENT_TYPE_LABELS);

export function documentTypeLabel(key: string): string {
    return DOCUMENT_TYPE_LABELS[key] ?? key;
}
