import { useEffect, useMemo, useState } from "react";
import type { AnyFieldApi } from "@tanstack/react-form";

import { Field, FieldError, FieldLabel } from "@/components/ui/field";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import {
    Combobox,
    ComboboxClear,
    ComboboxContent,
    ComboboxEmpty,
    ComboboxInput,
    ComboboxInputGroup,
    ComboboxItem,
    ComboboxItemIndicator,
    ComboboxList,
    ComboboxTrigger,
} from "@/components/ui/combobox";
import { useFormOptionsByGroup } from "@/features/form-options/hooks/use-form-options";
import type { PublicFormOption } from "@/features/form-options/types";
import {
    FormCheckboxGroup,
    FormRadioGroup,
    FormSelectField,
} from "./form-fields";

/**
 * Wrappers that bind a form field to a form-options group: they fetch the
 * group's options once (shared React Query cache) and render the matching
 * field primitive, so call sites no longer repeat the
 * `useFormOptionsByGroup(...)` + `.map(...)` boilerplate.
 *
 * The raw `useFormOptionsByGroup` hook remains available for schema gating,
 * summary label maps, and reset effects that need the full option objects.
 */

export type FormOptionFilter = (option: PublicFormOption) => boolean;

type FormOptionFieldProps = {
    field: AnyFieldApi;
    label: string;
    group: string;
    /** Restrict to children of this option value (location groups). */
    parentValue?: string;
    /** Client-side filter applied to the fetched options (e.g. by parent). */
    filter?: FormOptionFilter;
    placeholder?: string;
    disabled?: boolean;
};

type SelectOption = { value: string; label: string };

/**
 * Form sections persist the readable Persian label (e.g. «تهران») instead of
 * the stable value key, so review/summary steps render stored data without any
 * lookup. Field primitives therefore use the label as both the stored value and
 * the displayed text.
 */
function toSelectOptions(
    options: PublicFormOption[] | undefined,
    filter?: FormOptionFilter,
): SelectOption[] | undefined {
    if (!options) return undefined;
    const source = filter ? options.filter(filter) : options;
    return source.map((option) => ({
        value: option.label,
        label: option.label,
    }));
}

export function FormOptionSelectField({
    field,
    label,
    group,
    parentValue,
    filter,
    placeholder,
    disabled,
}: FormOptionFieldProps) {
    const { data } = useFormOptionsByGroup(group, parentValue);
    const options = toSelectOptions(data, filter);
    console.log(data, options, filter, field.state.value);
    return (
        <FormSelectField
            field={field}
            label={label}
            options={options ?? []}
            placeholder={placeholder}
            disabled={disabled}
        />
    );
}

export function FormOptionRadioGroup({
    field,
    label,
    group,
    parentValue,
    filter,
}: FormOptionFieldProps) {
    const { data } = useFormOptionsByGroup(group, parentValue);
    const options = toSelectOptions(data, filter);

    return (
        <FormRadioGroup field={field} label={label} options={options ?? []} />
    );
}

export function FormOptionCheckboxGroup({
    field,
    label,
    group,
    parentValue,
    filter,
}: FormOptionFieldProps) {
    const { data } = useFormOptionsByGroup(group, parentValue);
    const options = toSelectOptions(data, filter);

    return (
        <FormCheckboxGroup
            field={field}
            label={label}
            options={options ?? []}
        />
    );
}

// ── Searchable Combobox ──

type FormOptionComboboxFieldProps = FormOptionFieldProps & {
    disabled?: boolean;
    /** Cap on items rendered in the popup. Defaults to 50. */
    limit?: number;
    /**
     * Fetch matches from the server per keystroke (debounced `?search=`)
     * instead of fetching the whole group and filtering client-side.
     */
    serverSearch?: boolean;
    /** Debounce delay in ms for server search. Defaults to 300. */
    searchDelay?: number;
    /**
     * Derive the option value to match from the stored form value (used when
     * the stored value is a composite, e.g. the combined «استان-شهر» place).
     * Defaults to the stored value itself.
     */
    deriveDisplayValue?: (stored: unknown) => string;
    /**
     * Transform a chosen option label into the value to store. Defaults to the
     * option label itself. Used by PlaceFields to persist the combined
     * «استان-شهر» string.
     */
    formatValue?: (optionValue: string) => string;
};

function useDebouncedValue(value: string, delay: number): string {
    const [debounced, setDebounced] = useState(value);

    useEffect(() => {
        const timer = setTimeout(() => setDebounced(value), delay);
        return () => clearTimeout(timer);
    }, [value, delay]);

    return debounced;
}

export function FormOptionComboboxField({
    field,
    label,
    group,
    parentValue,
    filter,
    placeholder = "انتخاب کنید",
    disabled,
    limit = 50,
    serverSearch = false,
    searchDelay = 300,
    deriveDisplayValue,
    formatValue,
}: FormOptionComboboxFieldProps) {
    const [query, setQuery] = useState("");
    const debouncedQuery = useDebouncedValue(query, searchDelay);

    const search = serverSearch ? debouncedQuery || undefined : undefined;
    const { data } = useFormOptionsByGroup(group, parentValue, search);

    const displayValue = deriveDisplayValue
        ? deriveDisplayValue(field.state.value)
        : (field.state.value as string | undefined);

    const items = useMemo(() => {
        const base =
            toSelectOptions(data, serverSearch ? undefined : filter) ?? [];
        if (
            displayValue &&
            !base.some((option) => option.value === displayValue)
        ) {
            base.push({ value: displayValue, label: displayValue });
        }
        return base;
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [data, filter, serverSearch, displayValue]);

    const selectedItem =
        items.find((option) => option.value === displayValue) ?? null;
    const isInvalid = field.state.meta.isTouched && !field.state.meta.isValid;

    return (
        <Field data-invalid={isInvalid}>
            <FieldLabel htmlFor={field.name}>{label}</FieldLabel>
            <Combobox
                value={selectedItem}
                onValueChange={(item) =>
                    field.handleChange(
                        formatValue
                            ? formatValue(item?.value ?? "")
                            : (item?.value ?? ""),
                    )
                }
                items={items}
                itemToStringLabel={(item) => item.label}
                filter={serverSearch ? null : undefined}
                limit={limit}
                disabled={disabled}
                onInputValueChange={(value, { reason }) => {
                    if (reason === "input-change") {
                        setQuery(value);
                    }
                }}
            >
                <ComboboxInputGroup aria-invalid={isInvalid || undefined}>
                    <ComboboxInput
                        id={field.name}
                        placeholder={placeholder}
                        aria-invalid={isInvalid || undefined}
                        onBlur={field.handleBlur}
                    />
                    {field.state.value ? (
                        <ComboboxClear aria-label="پاک کردن" />
                    ) : null}
                    <ComboboxTrigger aria-label="باز کردن لیست" />
                </ComboboxInputGroup>
                <ComboboxContent>
                    <ComboboxEmpty />
                    <ComboboxList>
                        {(item: SelectOption) => (
                            <ComboboxItem key={item.value} value={item}>
                                <ComboboxItemIndicator />
                                <span className="flex flex-1 shrink-0 gap-2 whitespace-nowrap">
                                    {item.label}
                                </span>
                            </ComboboxItem>
                        )}
                    </ComboboxList>
                </ComboboxContent>
            </Combobox>
            {isInvalid && <FieldError errors={field.state.meta.errors} />}
        </Field>
    );
}

/**
 * Generic location field(s) for a form field that stores a plain-text
 * location value.
 *
 * - `mode: "city"` (default): a province selector (UI-only local state) plus
 *   a searchable city combobox. The stored value is the combined readable
 *   place string «{استان}-{شهر}» (e.g. «تهران-تهران»), so no lookup is needed
 *   when displaying it. The province is re-derived on load by splitting on the
 *   first `-`.
 * - `mode: "province"`: a single province select bound directly to `field`
 *   (stores the province label).
 */
export function PlaceFields({
    field,
    mode = "city",
    provinceLabel = "استان",
    cityLabel = "شهر",
    provincePlaceholder = "انتخاب استان",
    cityPlaceholder = "انتخاب شهر",
}: {
    field: AnyFieldApi;
    mode?: "city" | "province";
    provinceLabel?: string;
    cityLabel?: string;
    provincePlaceholder?: string;
    cityPlaceholder?: string;
}) {
    if (mode === "province") {
        return (
            <FormOptionSelectField
                field={field}
                label={provinceLabel}
                group="province"
                placeholder={provincePlaceholder}
            />
        );
    }

    const value = (field.state.value as string | undefined) ?? "";
    const [province, setProvince] = useState(() => value.split("-")[0] ?? "");

    useEffect(() => {
        const derived = value.split("-")[0] ?? "";
        if (derived && derived !== province) {
            setProvince(derived);
        }
    }, [value, province]);

    const { data: provinceOptions } = useFormOptionsByGroup("province");

    const provinceValue = provinceOptions?.find(
        (option) => option.label === province,
    )?.value;

    const handleProvinceChange = (next: string | null) => {
        const nextProvince = next ?? "";
        setProvince(nextProvince);
        if (value.split("-")[0] !== nextProvince) {
            field.handleChange("");
        }
    };

    const provinceItems = provinceOptions?.map((option) => ({
        value: option.label,
        label: option.label,
    }));

    return (
        <>
            <Field>
                <FieldLabel htmlFor={`${field.name}.province`}>
                    {provinceLabel}
                </FieldLabel>
                <Select
                    value={province || null}
                    onValueChange={handleProvinceChange}
                    itemToStringLabel={(item) =>
                        provinceItems?.find((option) => option.value === item)
                            ?.label ??
                        item ??
                        ""
                    }
                >
                    <SelectTrigger id={`${field.name}.province`}>
                        <SelectValue placeholder={provincePlaceholder} />
                    </SelectTrigger>
                    <SelectContent>
                        {provinceItems?.map((option) => (
                            <SelectItem key={option.value} value={option.value}>
                                {option.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </Field>
            <FormOptionComboboxField
                field={field}
                label={cityLabel}
                group="city"
                parentValue={provinceValue || undefined}
                disabled={!province}
                placeholder={
                    province ? cityPlaceholder : "ابتدا استان را انتخاب کنید"
                }
                deriveDisplayValue={(stored) => {
                    const combined = (stored as string | undefined) ?? "";
                    return combined.split("-")[1] ?? combined;
                }}
                formatValue={(city) => (city ? `${province}-${city}` : "")}
            />
        </>
    );
}

/**
 * Province + city selectors bound to two separate form fields (used by the
 * address). Both store readable labels; the city list is filtered by the
 * selected province and cleared when the province changes.
 */
export function ProvinceCityFields({
    provinceField,
    cityField,
    provinceLabel = "استان",
    cityLabel = "شهر",
    provincePlaceholder = "انتخاب استان",
    cityPlaceholder = "انتخاب شهر",
}: {
    provinceField: AnyFieldApi;
    cityField: AnyFieldApi;
    provinceLabel?: string;
    cityLabel?: string;
    provincePlaceholder?: string;
    cityPlaceholder?: string;
}) {
    const province = (provinceField.state.value as string | undefined) ?? "";

    const { data: provinceOptions } = useFormOptionsByGroup("province");

    const provinceValue = provinceOptions?.find(
        (option) => option.label === province,
    )?.value;

    const handleProvinceChange = (next: string | null) => {
        const nextProvince = next ?? "";
        if (nextProvince !== province) {
            provinceField.handleChange(nextProvince);
            cityField.handleChange("");
        }
    };

    const provinceItems = provinceOptions?.map((option) => ({
        value: option.label,
        label: option.label,
    }));

    return (
        <>
            <Field>
                <FieldLabel htmlFor={provinceField.name}>
                    {provinceLabel}
                </FieldLabel>
                <Select
                    value={province || null}
                    onValueChange={handleProvinceChange}
                    itemToStringLabel={(item) =>
                        provinceItems?.find((option) => option.value === item)
                            ?.label ??
                        item ??
                        ""
                    }
                >
                    <SelectTrigger id={provinceField.name}>
                        <SelectValue placeholder={provincePlaceholder} />
                    </SelectTrigger>
                    <SelectContent>
                        {provinceItems?.map((option) => (
                            <SelectItem key={option.value} value={option.value}>
                                {option.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </Field>
            <FormOptionSelectField
                field={cityField}
                label={cityLabel}
                group="city"
                parentValue={provinceValue || undefined}
                disabled={!province}
                placeholder={
                    province ? cityPlaceholder : "ابتدا استان را انتخاب کنید"
                }
            />
        </>
    );
}
