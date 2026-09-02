import type { FormOption } from "@/features/form-options/types";

/**
 * Editable form state for a FormOption, held as strings because the dialog
 * edits raw input and normalises to the server payload only on save.
 */
export type OptionFormState = {
    value: string;
    label: string;
    parent_value: string;
    group_label: string;
    meta: string;
    sort_order: string;
    is_active: boolean;
};

export type OptionFormActions = {
    patch: (patch: Partial<OptionFormState>) => void;
};

export const EMPTY_PARENT_VALUE = "";
export const EMPTY_GROUP_LABEL = "";

export function emptyForm(nextSortOrder: number): OptionFormState {
    return {
        value: "",
        label: "",
        parent_value: EMPTY_PARENT_VALUE,
        group_label: EMPTY_GROUP_LABEL,
        meta: "",
        sort_order: String(nextSortOrder),
        is_active: true,
    };
}

export function formFromOption(option: FormOption): OptionFormState {
    return {
        value: option.value,
        label: option.label,
        parent_value: option.parent_value ?? EMPTY_PARENT_VALUE,
        group_label: option.group_label ?? EMPTY_GROUP_LABEL,
        meta: option.meta ? JSON.stringify(option.meta, null, 2) : "",
        sort_order: String(option.sort_order),
        is_active: option.is_active,
    };
}

export function parseMeta(raw: string): Record<string, unknown> | null {
    const trimmed = raw.trim();
    if (!trimmed) return null;
    try {
        const parsed = JSON.parse(trimmed);
        return parsed && typeof parsed === "object" && !Array.isArray(parsed)
            ? parsed
            : null;
    } catch {
        return null;
    }
}

export const EMPTY_FORM_OPTION_ID = null;

export function saveDisabled(editing: boolean, form: OptionFormState): boolean {
    return (editing ? false : !form.value.trim()) || !form.label.trim();
}

export function buildUpdatePayload(form: OptionFormState) {
    return {
        label: form.label.trim(),
        parent_value: form.parent_value.trim() || null,
        group_label: form.group_label.trim() || null,
        meta: parseMeta(form.meta),
        sort_order: Number(form.sort_order) || 0,
        is_active: form.is_active,
    };
}