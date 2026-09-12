import {
    createFilterQuery,
    createFilterRule,
    isFilterRule,
} from "@/components/reui/filters/filters-query";
import type {
    FilterQuery,
    FilterRule,
} from "@/components/reui/filters/filters-types";
import type { ColumnFiltersState } from "@tanstack/react-table";

/**
 * Bridges reui's FilterQuery tree and the URL-driven ColumnFiltersState used
 * by every list page. The list contract stays exactly one flat param per
 * filter (status, is_active, event, ...) — the tree is the reui FilterBar's
 * own shape and is built/torn down here on every render.
 *
 * Mapping rules:
 * - One column filter «{id, value: [v]}» becomes one rule
 *   «{path: [id], operator: "is", value: v}» — "is" because list filters
 *   are exact server-side matches.
 * - A NEGATABLE field (see ListFilterFieldDef.negatable) is one FilterField
 *   with TWO operators («برابر» is / «مخالف» is_not, inverse-linked) whose
 *   rule occupies one of TWO URL params: the plain one for «برابر» and
 *   «{columnId}_not» for «مخالف» — so `status` and `status_not` can even
 *   coexist (equal to A and not equal to B).
 * - Unknown field ids in the query (a stale URL param the page no longer
 *   declares) are dropped rather than crashing the bar.
 */
export type ListFilterFieldDef = {
    /** Column id — also the FilterField id and the rule path. */
    id: string;
    label: string;
    type?: "select" | "multiselect" | "boolean" | "text" | "date";
    options?: { value: string; label: string }[];
    /**
     * Offers the «مخالف» (is_not) operator; its column filter and URL param
     * follow the «{id}_not» / «{searchKey}_not» convention and the backend
     * must accept the negated param as an exclusion (`where ≠`).
     */
    negatable?: boolean;
};

const OPERATOR_BY_FIELD_TYPE: Record<string, string> = {
    text: "contains",
    select: "is",
    multiselect: "is_any_of",
    boolean: "is",
    date: "is",
};

/** The column-filter id a rule's operator lands on. */
function columnIdOf(fieldId: string, operator: string): string {
    return operator === "is_not" ? `${fieldId}_not` : fieldId;
}

/** ColumnFiltersState (URL-driven) → reui FilterQuery tree. */
export function columnFiltersToQuery(
    columnFilters: ColumnFiltersState,
    fields: readonly ListFilterFieldDef[],
    /**
     * The previous query, when the bar is controlled across renders: a rule
     * keeps the id its path+operator already had, so a URL round-trip does
     * not remount the chip mid-flow (remounting drops the operator→value
     * handoff focus the bar parked on the old node).
     */
    previous?: FilterQuery,
): FilterQuery {
    const query = createFilterQuery();
    const byId = new Map(fields.map((field) => [field.id, field]));
    const previousIds = new Map<string, string>();
    for (const node of previous?.rules ?? []) {
        if (isFilterRule(node) && node.path.length === 1) {
            previousIds.set(`${node.path[0]}:${node.operator}`, node.id);
        }
    }
    for (const filter of columnFilters) {
        // Strip the negation suffix to find the owning field def.
        const negated = filter.id.endsWith("_not");
        const fieldId = negated ? filter.id.slice(0, -4) : filter.id;
        const field = byId.get(fieldId);
        if (!field) continue;
        if (negated && !field.negatable) continue;

        const values = Array.isArray(filter.value)
            ? (filter.value as unknown[])
            : [filter.value];
        if (values.length === 0) continue;

        const operator = negated
            ? "is_not"
            : OPERATOR_BY_FIELD_TYPE[field.type ?? "select"] ?? "is";

        query.rules.push(
            createFilterRule({
                // Stable across rebuilds: the path+operator key the bar's
                // live tree already carries, else the column id.
                id: previousIds.get(`${fieldId}:${operator}`) ?? filter.id,
                path: [fieldId],
                operator,
                // Scalars for arity-one operators (what the bar's editors
                // commit), arrays for "many" (multiselect) — so a restored
                // rule and a freshly built one are the same shape.
                value:
                    operator === "is_any_of"
                        ? values
                        : (values[0] as unknown),
            }),
        );
    }
    return query;
}

/** reui FilterQuery tree → ColumnFiltersState for the URL state hook. */
export function queryToColumnFilters(
    query: FilterQuery,
    fields: readonly ListFilterFieldDef[],
): ColumnFiltersState {
    const byId = new Map(fields.map((field) => [field.id, field]));
    // Last rule per column wins: the URL holds one value per param.
    const byColumn = new Map<string, unknown[]>();
    for (const node of query.rules) {
        if (!isFilterRule(node)) continue;
        const field = byId.get(node.path[0] ?? "");
        if (!field) continue;
        const value = ruleValues(node);
        if (value.length === 0) continue;
        const columnId = columnIdOf(field.id, node.operator);
        if (columnId === `${field.id}_not` && !field.negatable) continue;
        byColumn.set(columnId, value);
    }
    return [...byColumn.entries()].map(([id, value]) => ({ id, value }));
}

/** The rule's value flattened to the array shape column filters store. */
function ruleValues(rule: FilterRule): unknown[] {
    if (Array.isArray(rule.value)) return rule.value as unknown[];
    if (rule.value === undefined || rule.value === null || rule.value === "") {
        return [];
    }
    return [rule.value];
}

function fieldType(
    id: string,
    fields: readonly ListFilterFieldDef[],
): string | undefined {
    return fields.find((field) => field.id === id)?.type;
}

/**
 * Content equality for column filters, order-insensitive: our own URL
 * round-trips land exactly what we last wrote, everything else is an
 * external change (back/forward, a shared link, another writer).
 */
export function columnFiltersEqual(
    a: ColumnFiltersState,
    b: ColumnFiltersState,
): boolean {
    if (a === b) return true;
    if (a.length !== b.length) return false;
    const byId = new Map(
        a.map((filter) => [
            filter.id,
            Array.isArray(filter.value) ? filter.value : [filter.value],
        ]),
    );
    for (const filter of b) {
        const av = byId.get(filter.id);
        if (!av) return false;
        const bv = Array.isArray(filter.value) ? filter.value : [filter.value];
        if (av.length !== bv.length) return false;
        if (!av.every((entry, index) => String(entry) === String(bv[index]))) {
            return false;
        }
    }
    return true;
}
