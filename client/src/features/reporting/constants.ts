/** Labels/variants for the unified inbox (کارتابل) vocabulary. */

import {
    Icon,
    IconMail,
    IconMailCheck,
    IconMailOpened,
} from "@tabler/icons-react";

/** Known source keys; unknown keys fall back to the raw key. */
export const SOURCE_LABELS: Record<string, string> = {
    automation: "اتوماسیون اداری",
    rahkaran: "راهکاران",
    bizagi: "بیزاجی",
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

export type StatusVariant = "secondary" | "default" | "success";

export const STATUS_CONFIG: Record<
    string,
    { label: string; variant: StatusVariant; icon: Icon }
> = {
    unseen: { label: "دیده نشده", variant: "default", icon: IconMail },
    seen: { label: "دیده شده", variant: "secondary", icon: IconMailOpened },
    done: { label: "انجام شده", variant: "success", icon: IconMailCheck },
};

/** Label for a status key, falling back to the raw key when unknown. */
export function statusLabel(key: string): string {
    return STATUS_CONFIG[key]?.label ?? key;
}

/** Icon for a status key, falling back to the unseen icon when unknown. */
export function statusIcon(key: string): Icon {
    return STATUS_CONFIG[key]?.icon ?? IconMail;
}

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
