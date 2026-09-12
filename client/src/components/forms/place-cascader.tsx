import type { AnyFieldApi } from "@tanstack/react-form";

import { OptionHierarchyField } from "./option-hierarchy-field";

/**
 * Province → city cascader: a thin facade over `OptionHierarchyField` with
 * the place groups, labels and the «prefixed» child value mode (a city's own
 * value is the combined «{province}-{cityCode}» string). See the generic's
 * docblock for the two value models and the two bindings.
 */
export function PlaceCascader({
    provinceField,
    cityField,
    combinedField,
    label,
    provinceLabel = "استان",
    cityLabel = "شهر",
    placeholder = "انتخاب استان و شهر",
    disabled,
    deepSearch = false,
}: {
    /** Two-field mode: the province form field. */
    provinceField?: AnyFieldApi;
    /** Two-field mode: the city form field. */
    cityField?: AnyFieldApi;
    /** Combined mode: single field storing «{province}-{city}». */
    combinedField?: AnyFieldApi;
    label?: string;
    provinceLabel?: string;
    cityLabel?: string;
    placeholder?: string;
    disabled?: boolean;
    /**
     * Opt in to «deep search across every level with path-annotated results».
     * Off by default because it requires loading the full province → city tree
     * up front; the cascader then fetches cities per-province on demand.
     */
    deepSearch?: boolean;
}) {
    return (
        <OptionHierarchyField
            parentGroup="province"
            childGroup="city"
            parentLabel={provinceLabel}
            childLabel={cityLabel}
            parentField={provinceField}
            childField={cityField}
            combinedField={combinedField}
            childValueMode="prefixed"
            label={label}
            placeholder={placeholder}
            disabled={disabled}
            deepSearch={deepSearch}
        />
    );
}
