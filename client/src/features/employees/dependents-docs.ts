export type FormOptionLite = { value: string; label: string };

/**
 * Heading for one dependent row: the row's نسبت option label, falling back to
 * the generic «وابسته» while the field is empty. No row number.
 */
export function dependentRowLabel(
    relationshipValue: unknown,
    index: number,
    options?: FormOptionLite[],
): string {
    const value = typeof relationshipValue === "string" ? relationshipValue : "";
    const relationshipLabel = options?.find(
        (option) => option.value === value,
    )?.label;

    return relationshipLabel ?? "وابسته";
}