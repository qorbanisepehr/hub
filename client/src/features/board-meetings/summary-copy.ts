import {
    IconChecks,
    IconHash,
    IconSignLeft,
    IconSparkles,
    IconTextCaption,
} from "@tabler/icons-react";
import type { ElementType } from "react";

import type {
    MeetingSummaryFieldName,
    MeetingSummaryFieldStatus,
    MeetingSummaryReason,
    MeetingSummaryStatus,
} from "./types";

/**
 * The summary's display vocabulary, and it lives here on purpose.
 *
 * The server stores stable keys — `partial`, `not_configured`, `empty` — rather
 * than prose, precisely so a row written in one year reads correctly in
 * whatever language the UI is in next. That only works if exactly one place
 * turns those keys into words, and that place is the client, which is
 * Persian-only. Translating them server-side as well would be a second
 * translation of one contract, free to drift from the first.
 *
 * A key the server adds later lands in {@link SUMMARY_REASONS} /
 * {@link SUMMARY_STATUSES} as a missing case, and the fallbacks below say
 * something honest rather than rendering `undefined` into a badge.
 */

export interface SummaryFieldConfig {
    name: MeetingSummaryFieldName;
    label: string;
    icon: ElementType;
    /** One value vs a list — drives how the value and the diff are rendered. */
    isList: boolean;
    /** A prose field reads as a paragraph; a list reads as bullets. */
}

/**
 * The five fields in reading order, not enum order: a person reads a summary
 * from the abstract down to the decisions, and the order here is the order the
 * section renders them in.
 */
export const SUMMARY_FIELDS: readonly SummaryFieldConfig[] = [
    {
        name: "executive_summary",
        label: "خلاصهٔ مدیریتی",
        icon: IconSparkles,
        isList: false,
    },
    {
        name: "topics",
        label: "موضوعات مطرح‌شده",
        icon: IconSignLeft,
        isList: true,
    },
    {
        name: "bullet_points",
        label: "نکات کلیدی",
        icon: IconTextCaption,
        isList: true,
    },
    {
        name: "key_decisions",
        label: "تصمیمات و مصوبات",
        icon: IconChecks,
        isList: true,
    },
    {
        name: "suggested_tags",
        label: "برچسب‌های پیشنهادی",
        icon: IconHash,
        isList: true,
    },
];

export const SUMMARY_FIELD_LABELS: Readonly<
    Record<MeetingSummaryFieldName, string>
> = Object.fromEntries(
    SUMMARY_FIELDS.map((field) => [field.name, field.label]),
) as Record<MeetingSummaryFieldName, string>;

export const SUMMARY_STATUSES: Readonly<Record<MeetingSummaryStatus, string>> =
    {
        ok: "کامل",
        partial: "ناقص",
        failed: "ناموفق",
        pending: "در حال پردازش",
        none: "بدون خلاصهٔ پذیرفته‌شده",
    };

/** The Badge variant each row state gets, so colour means the same thing here. */
export const SUMMARY_STATUS_VARIANTS: Readonly<
    Record<
        MeetingSummaryStatus,
        "success" | "warning" | "destructive" | "secondary"
    >
> = {
    ok: "success",
    partial: "warning",
    failed: "destructive",
    pending: "secondary",
    none: "secondary",
};

/**
 * Why a run or a field produced nothing. `empty` is the common one and gets a
 * sentence rather than a shrug, because "the model left this blank" and
 * "something went wrong" are different things to a person deciding whether to
 * regenerate.
 */
export const SUMMARY_REASONS: Readonly<Record<MeetingSummaryReason, string>> = {
    not_configured: "هیچ ارائه‌دهندهٔ فعالی برای هوش مصنوعی تنظیم نشده است.",
    no_structured_output: "مدل به‌جای ساختار خواسته‌شده، متن آزاد برگرداند.",
    no_fields_returned:
        "مدل ساختار درستی برگرداند اما هیچ‌کدام از فیلدها خالی بود.",
    empty: "مدل برای این فیلد چیزی ننوشت.",
};

export const SUMMARY_FIELD_STATUSES: Readonly<
    Record<MeetingSummaryFieldStatus, string>
> = {
    ok: "تولید شد",
    failed: "تولید نشد",
};

/** Shown instead of a field label the server has introduced since this build. */
export function fieldLabel(name: string): string {
    return SUMMARY_FIELD_LABELS[name as MeetingSummaryFieldName] ?? name;
}

export function reasonText(reason: string | null): string | null {
    if (reason === null) {
        return null;
    }

    return (
        SUMMARY_REASONS[reason as MeetingSummaryReason] ??
        "دلیل نامشخصی ثبت شده است."
    );
}

export function statusText(status: string): string {
    return (
        SUMMARY_STATUSES[status as MeetingSummaryStatus] ??
        SUMMARY_STATUSES.failed
    );
}
