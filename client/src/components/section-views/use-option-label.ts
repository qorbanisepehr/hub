import { useCallback } from "react";

import { useFormOptionsByGroup } from "@/features/form-options/hooks/use-form-options";

type LabeledOption = { value: string; label: string };

/**
 * Resolve a stored option slug to its human-readable label. Falls back to the
 * raw value when options aren't loaded yet or the value isn't found (e.g.
 * free-text fields).
 */
export function resolveOptionLabel(
    options: LabeledOption[] | undefined,
    value: string | null | undefined,
): string {
    if (!value) return "";
    return options?.find((o) => o.value === value)?.label ?? value;
}

/**
 * Stable per-group label resolver for contexts where hooks can't be called
 * per item (repeater tables, multi-value fields). Single-value views should
 * prefer {@link useOptionLabel}.
 */
export function useOptionLabelResolver(
    group: string,
): (value: string | null | undefined) => string {
    const { data: options } = useFormOptionsByGroup(group);
    return useCallback(
        (value: string | null | undefined) => resolveOptionLabel(options, value),
        [options],
    );
}

/** Resolve one stored slug to its label for read-only section views. */
export function useOptionLabel(
    group: string,
    value: string | undefined | null,
): string {
    const resolve = useOptionLabelResolver(group);
    return resolve(value);
}

/**
 * Resolve a multi-value field's slugs to a Persian-joined label list, keeping
 * raw values visible until the group's options arrive.
 */
export function useOptionLabels(
    group: string,
    values: string[] | undefined,
): string {
    const resolve = useOptionLabelResolver(group);
    if (!values) return "";
    return values.map((value) => resolve(value)).join("، ");
}
