import { DEFAULT_FILTER_OPERATOR_LABELS } from "@/components/reui/filters/filters-operators";
import type { FilterLabels } from "@/components/reui/filters/filters-types";

/**
 * Persian chrome copy for the reui FilterBar, spread over the shipped English
 * defaults (resolveFilterLabels shallow-merges, so only the overridden keys
 * matter and function-valued labels never fall back half-translated).
 */
export const FA_FILTER_LABELS: Partial<FilterLabels> = {
    addFilter: "افزودن فیلتر",
    advancedFilter: "فیلتر پیشرفته",
    showRecords: "در این نمایش، رکوردها را نشان بده",
    builderEmpty: "فیلتری وجود ندارد",
    builderEmptyHint: "برای محدود کردن نتایج، یک فیلتر اضافه کنید.",
    addCondition: "افزودن فیلتر",
    addConditionGroup: "افزودن گروه",
    addToGroup: "افزودن فیلتر به این گروه",
    removeGroup: "حذف گروه",
    wrapInGroup: "قرار دادن در گروه",
    ungroup: "خروج از گروه",
    moveToTopLevel: "انتقال به سطح اصلی",
    moveToGroup: (position) => `انتقال به گروه ${position}`,
    reorder: "جابجایی",
    reorderHint: "برای جابجایی، Alt را با کلیدهای بالا/پایین نگه دارید",
    groupAll: "همه موارد زیر برقرار باشند…",
    groupAny: "حداقل یکی از موارد زیر برقرار باشد…",
    groupPlaceholder: "فیلترها را اینجا بکشید",
    rowLabel: (condition, depth) => `${condition}، سطح ${depth}`,
    groupLabel: (description, depth) => `${description}، سطح ${depth}`,
    groupAnnouncement: (added) => (added ? "گروه اضافه شد" : "گروه حذف شد"),
    reorderAnnouncement: (label, position, total) =>
        `${label} به موقعیت ${position} از ${total} منتقل شد`,
    moveAnnouncement: (label, destination, position, total) =>
        `${label} به ${destination}، موقعیت ${position} از ${total} منتقل شد`,
    clearAll: "پاک کردن همه",
    groupMenu: "گزینه‌های گروه",
    searchFields: "جستجوی ویژگی…",
    searchOperators: "جستجوی شرط…",
    searchOptions: "جستجو…",
    back: "بازگشت",
    clear: "پاک کردن",
    apply: "اعمال",
    discard: "لغو تغییرات",
    empty: "نتیجه‌ای یافت نشد",
    loading: "در حال بارگذاری…",
    loadingMore: "در حال بارگذاری موارد بیشتر…",
    loadMore: "بارگذاری موارد بیشتر",
    error: "خطا در بارگذاری",
    retry: "تلاش مجدد",
    where: "با شرط",
    and: "و",
    or: "یا",
    combinator: "تغییر ترکیب‌کننده",
    combinatorLabel: (word) => `${word}، تغییر ترکیب‌کننده`,
    duplicate: "تکثیر",
    negate: "نفی کردن",
    convertToAdvanced: "ویرایشگر پیشرفته",
    remove: "حذف",
    chipMenu: (fieldLabel) => `گزینه‌های فیلتر ${fieldLabel}`,
    filtersLabel: "فیلترها",
    filterLabel: (condition) => condition,
    readOnly: "فقط‌خواندنی. این فیلترها قابل تغییر نیستند.",
    pathSeparator: " > ",
    valuePlaceholder: "مقدار را وارد کنید…",
    selectPlaceholder: "انتخاب کنید…",
    noValue: "بدون مقدار",
    selectCondition: "انتخاب شرط",
    incomplete: "فیلتر ناقص",
    branchAffordance: "باز کردن فهرست",
    exclusiveHint: "قابل ترکیب با سایر گزینه‌ها نیست",
    exclusiveAnnouncement: (label, cleared) =>
        cleared === 1
            ? `${label} انتخاب شد. یک انتخاب دیگر پاک شد.`
            : `${label} انتخاب شد. ${cleared} انتخاب دیگر پاک شد.`,
    itemCount: (count) => `${count} مورد`,
    fieldsLabel: "ویژگی‌ها",
    resultsAnnouncement: (count) =>
        count === 1 ? "یک نتیجه" : `${count} نتیجه`,
    actionsLabel: "عملیات",
    stepAnnouncement: (step, label) => {
        if (step === "field") return `یک ویژگی انتخاب کنید. ${label}`;
        if (step === "operator") return `برای ${label} شرط را انتخاب کنید`;
        return `مقدار ${label} را وارد کنید`;
    },
    countAnnouncement: (count) =>
        count === 1 ? "یک فیلتر اعمال شد" : `${count} فیلتر اعمال شد`,
    valueCount: (count) => `${count} انتخاب شده`,
    valueDetail: (summary, values) => `${summary}: ${values.join("، ")}`,
    valueRange: (from, to) => `از ${from} تا ${to}`,
    rangeFrom: (fieldLabel) => `${fieldLabel} از`,
    rangeTo: (fieldLabel) => `${fieldLabel} تا`,
    rangeSeparator: "تا",
    negated: (operatorLabel) => `${operatorLabel} نباشد`,
    issueOperator: "شرط را انتخاب کنید",
    issueValue: "مقدار را وارد کنید",
    issueRange: "هر دو سر بازه را وارد کنید",
    issueRangeOrder: "پایان بازه قبل از شروع آن است",
    issueEmptyGroup: "این گروه هنوز شرطی ندارد",
    issueSummary: (count) =>
        count === 1
            ? "یک ردیف نیاز به توجه دارد"
            : `${count} ردیف نیاز به توجه دارند`,
};

/** Persian operator wording for the shipped operator catalog. */
export const FA_FILTER_OPERATOR_LABELS = {
    ...DEFAULT_FILTER_OPERATOR_LABELS,
    contains: "شامل",
    not_contains: "شامل نباشد",
    starts_with: "شروع شود با",
    ends_with: "پایان یابد به",
    is: "برابر",
    is_not: "مخالف",
    empty: "خالی",
    not_empty: "پر",
    eq: "مساوی =",
    neq: "نامساوی ≠",
    gt: "بزرگ‌تر از >",
    gte: "بزرگ‌تر یا مساوی ≥",
    lt: "کوچک‌تر از <",
    lte: "کوچک‌تر یا مساوی ≤",
    between: "بین",
    not_between: "خارج از بازه",
    is_any_of: "یکی از",
    is_none_of: "هیچ‌کدام از",
} as const;
