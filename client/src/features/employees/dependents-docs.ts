export type FormOptionLite = { value: string; label: string };

/**
 * Heading for one dependent row: «فرزند 1» derived from the row's نسبت
 * option, falling back to the generic «وابسته N» while the field is empty.
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

    return `${relationshipLabel ?? "وابسته"} ${index + 1}`;
}
