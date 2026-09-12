import { useEffect, useMemo, useRef, useState } from "react";
import type { ColumnFiltersState, OnChangeFn } from "@tanstack/react-table";

import { Filters } from "@/components/reui/filters/filters";
import type {
    FilterField,
    FilterOperator,
    FilterQuery,
} from "@/components/reui/filters/filters-types";
import { toPersianDate } from "@/lib/date-format";
import {
    columnFiltersToQuery,
    columnFiltersEqual,
    queryToColumnFilters,
    type ListFilterFieldDef,
} from "./filter-query-adapter";
import {
    FA_FILTER_LABELS,
    FA_FILTER_OPERATOR_LABELS,
} from "./filter-labels-fa";
import { FilterDateEditor } from "./filter-date-editor";

type TableFilterBarProps = {
    fields: readonly ListFilterFieldDef[];
    columnFilters: ColumnFiltersState;
    onColumnFiltersChange: OnChangeFn<ColumnFiltersState>;
    className?: string;
};

function operatorsFor(field: ListFilterFieldDef): FilterOperator[] {
    const is = {
        value: "is",
        label: FA_FILTER_OPERATOR_LABELS.is,
        inverse: "is_not",
    };
    if (!field.negatable) return [is];
    return [
        is,
        {
            value: "is_not",
            label: FA_FILTER_OPERATOR_LABELS.is_not,
            inverse: "is",
        },
    ];
}

export function TableFilterBar({
    fields,
    columnFilters,
    onColumnFiltersChange,
    className,
}: TableFilterBarProps) {
    const filterFields = useMemo<FilterField[]>(
        () =>
            fields.map((field) => {
                const operators = operatorsFor(field);
                return {
                    id: field.id,
                    label: field.label,
                    type:
                        field.type === "date"
                            ? undefined
                            : (field.type ?? "select"),
                    options: field.options,
                    ...(field.type === "date"
                        ? ({
                              editor: FilterDateEditor,
                              placeholder: "انتخاب تاریخ",
                              valueText: ({ value }) => {
                                  const raw = Array.isArray(value)
                                      ? value[0]
                                      : value;
                                  return typeof raw === "string" && raw
                                      ? toPersianDate(new Date(raw))
                                      : "انتخاب تاریخ";
                              },
                          } as Partial<FilterField>)
                        : {}),
                    defaultOperator: operators[0].value,
                    operators,
                };
            }),
        [fields],
    );

    const [query, setQuery] = useState<FilterQuery>(() =>
        columnFiltersToQuery(columnFilters, fields),
    );

    const lastWrittenRef = useRef<ColumnFiltersState>(columnFilters);

    useEffect(() => {
        if (columnFiltersEqual(columnFilters, lastWrittenRef.current)) return;
        setQuery(columnFiltersToQuery(columnFilters, fields, query));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [columnFilters, fields]);

    return (
        <Filters
            fields={filterFields}
            query={query}
            onQueryChange={(next) => {
                setQuery(next);
                const filters = queryToColumnFilters(next, fields);
                lastWrittenRef.current = filters;
                onColumnFiltersChange(filters);
            }}
            onBeforeQueryChange={(_next, details) =>
                details.reason !== "duplicate"
            }
            variant="basic"
            labels={FA_FILTER_LABELS}
            operatorLabels={FA_FILTER_OPERATOR_LABELS}
            showClear
            size="default"
            className={className}
        />
    );
}
