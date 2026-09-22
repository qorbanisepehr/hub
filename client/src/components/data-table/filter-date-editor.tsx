import { DatePicker } from "@/components/ui/date-picker";
import type { FilterEditorProps } from "@/components/reui/filters/filters-types";

/**
 * A `date` list-filter value editor: our Persian DatePicker bound to the
 * reui FilterEditor contract. The value is a plain `yyyy-MM-dd` string — the
 * same wire format the audit `date_from`/`date_to` params use.
 */
export function FilterDateEditor({
    value,
    onValueChange,
    commit,
}: FilterEditorProps<string | undefined>) {
    // The committed shape is a scalar string, but a URL-restored rule may
    // arrive as a single-element array (the columnFilters wire shape).
    const scalar =
        typeof value === "string"
            ? value
            : Array.isArray(value) && typeof value[0] === "string"
              ? value[0]
              : undefined;

    return (
        <div className="p-1">
            <DatePicker
                value={scalar}
                onChange={(next) => {
                    onValueChange(next);
                    // A date pick IS the value — a separate Apply step would
                    // ask the user to confirm what they already pointed at.
                    commit(next);
                }}
                placeholder="انتخاب تاریخ"
            />
        </div>
    );
}
