import { useCallback } from "react";

import { useResolvedFormOptions } from "@/features/form-options/hooks/use-form-options";
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

/**
 * Decode a composite place value — the city option's own value
 * («{province}-{city}») — into «استان، شهر». The province comes from the
 * city row's parent_value. Falls back to the raw value while unresolved or
 * for unknown values.
 */
export function usePlaceLabel(value: string | null | undefined): string {
    const { data: cities } = useResolvedFormOptions(
        "city",
        value ? [value] : [],
    );
    const city = cities?.find((option) => option.value === value);

    const { data: provinces } = useResolvedFormOptions(
        "province",
        city?.parent_value ? [city.parent_value] : [],
    );
    const province = provinces?.find(
        (option) => option.value === city?.parent_value,
    );

    if (!value) return "";
    if (!city) return value;
    return province ? `${province.label}، ${city.label}` : city.label;
}
