import { useEffect, useMemo, useState } from "react";
import type { AnyFieldApi } from "@tanstack/react-form";
import { IconLoader2 } from "@tabler/icons-react";

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
    ComboboxChip,
    ComboboxChipRemove,
    ComboboxChips,
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
import {
    useResolvedFormOptions,
    useFormOptionsByGroup,
} from "@/features/form-options/hooks/use-form-options";
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
 * Form sections persist the stable value key (slug) instead of the Persian
 * label, so review/summary steps and saved data use consistent identifiers.
 * Field primitives use the value as the stored key and the label for display.
 */
function toSelectOptions(
    options: PublicFormOption[] | undefined,
    filter?: FormOptionFilter,
): SelectOption[] | undefined {
    if (!options) return undefined;
    const source = filter ? options.filter(filter) : options;
    return source.map((option) => ({
        value: option.value,
        label: option.label,
    }));
}

function normalizeStoredValues(stored: unknown): string[] {
    if (typeof stored === "string") return stored ? [stored] : [];
    if (Array.isArray(stored)) {
        return stored.filter(
            (value): value is string => typeof value === "string" && value !== "",
        );
    }
    return [];
}

/**
 * Merge resolved rows for stored-but-missing values into the active option
 * list. A record can hold a value that was deactivated (or whose parent
 * changed) after it was saved; without this merge the UI degrades to showing
 * raw value keys or nothing at all.
 */
function useMergedOptions(
    group: string,
    options: PublicFormOption[] | undefined,
    stored: unknown,
): PublicFormOption[] | undefined {
    const values = normalizeStoredValues(stored);
    const missing =
        options === undefined
            ? []
            : [
                  ...new Set(
                      values.filter(
                          (value) => !options.some((o) => o.value === value),
                      ),
                  ),
              ];

    const { data: resolved } = useResolvedFormOptions(group, missing);

    if (!resolved?.length) return options;

    const extras = resolved.filter(
        (option) => !options?.some((o) => o.value === option.value),
    );

    return extras.length ? [...extras, ...options ?? []] : options;
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
    const { data, isLoading } = useFormOptionsByGroup(group, parentValue);
    const merged = useMergedOptions(group, data, field.state.value);
    const options = toSelectOptions(merged, filter);

    return (
        <FormSelectField
            field={field}
            label={label}
            options={options ?? []}
            placeholder={placeholder}
            disabled={disabled}
            loading={isLoading}
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
    const { data, isLoading } = useFormOptionsByGroup(group, parentValue);
    const merged = useMergedOptions(group, data, field.state.value);
    const options = toSelectOptions(merged, filter);

    return (
        <FormRadioGroup
            field={field}
            label={label}
            options={options ?? []}
            loading={isLoading}
        />
    );
}

export function FormOptionCheckboxGroup({
    field,
    label,
    group,
    parentValue,
    filter,
}: FormOptionFieldProps) {
    const { data, isLoading } = useFormOptionsByGroup(group, parentValue);
    const merged = useMergedOptions(group, data, field.state.value);
    const options = toSelectOptions(merged, filter);

    return (
        <FormCheckboxGroup
            field={field}
            label={label}
            options={options ?? []}
            loading={isLoading}
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
    const { data, isLoading } = useFormOptionsByGroup(group, parentValue, search);

    // Keystroke-driven server searches swap the query key per debounce tick;
    // disabling the input then would fight the user. Only the first fetch of
    // the untyped list blocks interaction.
    const isInitialLoading = isLoading && debouncedQuery === "";

    const displayValue = deriveDisplayValue
        ? deriveDisplayValue(field.state.value)
        : (field.state.value as string | undefined);

    const merged = useMergedOptions(group, data, displayValue);

    const items = useMemo(() => {
        const base =
            toSelectOptions(merged, serverSearch ? undefined : filter) ?? [];
        // Values unknown even after resolution (e.g. a deleted option) stay
        // visible as-is so the user can see and clear the stored value.
        if (
            displayValue &&
            !base.some((option) => option.value === displayValue)
        ) {
            base.push({ value: displayValue, label: displayValue });
        }
        return base;
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [merged, filter, serverSearch, displayValue]);

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
                disabled={disabled || isInitialLoading}
                onInputValueChange={(value, { reason }) => {
                    if (reason === "input-change") {
                        setQuery(value);
                    }
                }}
            >
                <ComboboxInputGroup
                    aria-invalid={isInvalid || undefined}
                    aria-busy={isInitialLoading || undefined}
                >
                    <ComboboxInput
                        id={field.name}
                        placeholder={
                            isInitialLoading ? "در حال بارگذاری…" : placeholder
                        }
                        aria-invalid={isInvalid || undefined}
                        aria-busy={isInitialLoading || undefined}
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
 * Multi-select variant of FormOptionComboboxField: stores an array of option
 * value keys on the form field and renders each selection as a removable chip.
 */
export function FormOptionMultiComboboxField({
    field,
    label,
    group,
    filter,
    placeholder = "انتخاب کنید",
    disabled,
    limit = 50,
}: FormOptionFieldProps & {
    disabled?: boolean;
    /** Cap on items rendered in the popup. Defaults to 50. */
    limit?: number;
}) {
    const { data, isLoading } = useFormOptionsByGroup(group);
    const selected = normalizeStoredValues(field.state.value);
    const merged = useMergedOptions(group, data, selected);
    const items = useMemo(
        () => toSelectOptions(merged, filter) ?? [],
        [merged, filter],
    );
    const selectedItems = items.filter((item) =>
        selected.includes(item.value),
    );
    const isInvalid = field.state.meta.isTouched && !field.state.meta.isValid;

    return (
        <Field data-invalid={isInvalid}>
            <FieldLabel htmlFor={field.name}>{label}</FieldLabel>
            <Combobox
                multiple
                value={selectedItems}
                onValueChange={(nextItems) =>
                    field.handleChange(nextItems.map((item) => item.value))
                }
                items={items}
                itemToStringLabel={(item) => item.label}
                limit={limit}
                disabled={disabled || (isLoading && selected.length === 0)}
            >
                <ComboboxInputGroup
                    aria-invalid={isInvalid || undefined}
                    aria-busy={isLoading || undefined}
                >
                    <ComboboxChips>
                        {selectedItems.map((item) => (
                            <ComboboxChip key={item.value}>
                                {item.label}
                                <ComboboxChipRemove aria-label="حذف" />
                            </ComboboxChip>
                        ))}
                        <ComboboxInput
                            id={field.name}
                            placeholder={
                                isLoading && selected.length === 0
                                    ? "در حال بارگذاری…"
                                    : placeholder
                            }
                            aria-invalid={isInvalid || undefined}
                            onBlur={field.handleBlur}
                        />
                    </ComboboxChips>
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
 *   a searchable city combobox. The stored value is the combined place string
 *   «{provinceValue}-{cityCode}» which matches the city option's own value
 *   column (e.g. «123-1230001001576»). The province is re-derived on load by
 *   splitting on the first `-`.
 * - `mode: "province"`: a single province select bound directly to `field`
 *   (stores the province value key).
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

    const { data: provinceOptions, isLoading: provinceLoading } =
        useFormOptionsByGroup("province");
    const mergedProvinceOptions = useMergedOptions(
        "province",
        provinceOptions,
        province,
    );

    const handleProvinceChange = (next: string | null) => {
        const nextProvince = next ?? "";
        setProvince(nextProvince);
        if (value.split("-")[0] !== nextProvince) {
            field.handleChange("");
        }
    };

    const provinceItems = toSelectOptions(mergedProvinceOptions);

    return (
        <>
            <Field>
                <FieldLabel htmlFor={`${field.name}.province`}>
                    {provinceLabel}
                </FieldLabel>
                <Select
                    value={province || null}
                    onValueChange={handleProvinceChange}
                    disabled={provinceLoading}
                    itemToStringLabel={(item) =>
                        provinceItems?.find((option) => option.value === item)
                            ?.label ??
                        item ??
                        ""
                    }
                >
                    <SelectTrigger
                        id={`${field.name}.province`}
                        disabled={provinceLoading}
                        className="gap-2"
                    >
                        {provinceLoading ? (
                            <span className="flex items-center gap-2 truncate text-sm text-muted-foreground">
                                <IconLoader2 className="size-4 shrink-0 animate-spin" />
                                در حال بارگذاری…
                            </span>
                        ) : (
                            <SelectValue placeholder={provincePlaceholder} />
                        )}
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
                parentValue={province || undefined}
                disabled={!province}
                placeholder={
                    province ? cityPlaceholder : "ابتدا استان را انتخاب کنید"
                }
                deriveDisplayValue={(stored) => {
                    const combined = (stored as string | undefined) ?? "";
                    return combined;
                }}
                formatValue={(city) => city ?? ""}
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

    const { data: provinceOptions, isLoading: provinceLoading } =
        useFormOptionsByGroup("province");
    const mergedProvinceOptions = useMergedOptions(
        "province",
        provinceOptions,
        province,
    );

    const handleProvinceChange = (next: string | null) => {
        const nextProvince = next ?? "";
        if (nextProvince !== province) {
            provinceField.handleChange(nextProvince);
            cityField.handleChange("");
        }
    };

    const provinceItems = toSelectOptions(mergedProvinceOptions);

    return (
        <>
            <Field>
                <FieldLabel htmlFor={provinceField.name}>
                    {provinceLabel}
                </FieldLabel>
                <Select
                    value={province || null}
                    onValueChange={handleProvinceChange}
                    disabled={provinceLoading}
                    itemToStringLabel={(item) =>
                        provinceItems?.find((option) => option.value === item)
                            ?.label ??
                        item ??
                        ""
                    }
                >
                    <SelectTrigger
                        id={provinceField.name}
                        disabled={provinceLoading}
                        className="gap-2"
                    >
                        {provinceLoading ? (
                            <span className="flex items-center gap-2 truncate text-sm text-muted-foreground">
                                <IconLoader2 className="size-4 shrink-0 animate-spin" />
                                در حال بارگذاری…
                            </span>
                        ) : (
                            <SelectValue placeholder={provincePlaceholder} />
                        )}
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
                parentValue={province || undefined}
                disabled={!province}
                placeholder={
                    province ? cityPlaceholder : "ابتدا استان را انتخاب کنید"
                }
            />
        </>
    );
}
