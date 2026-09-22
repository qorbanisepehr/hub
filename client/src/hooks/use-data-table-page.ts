import { useMemo, useState } from "react";
import {
    useTable,
    stockFeatures,
    type ColumnDef,
    type ColumnVisibilityState,
    type RowData,
    type StockFeatures,
    type Table as TanStackTable,
} from "@tanstack/react-table";

import { useTableUrlState, type NavigateFn } from "./use-table-url-state";
import { PAGINATION } from "@/lib/constants";

type SearchRecord = Record<string, unknown>;
type Meta = {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
};

type UrlStateConfig = {
    pagination?: Parameters<typeof useTableUrlState>[0]["pagination"];
    sorting?: Parameters<typeof useTableUrlState>[0]["sorting"];
    globalFilter?: Parameters<typeof useTableUrlState>[0]["globalFilter"];
    columnFilters?: Parameters<typeof useTableUrlState>[0]["columnFilters"];
};

type UseDataTableUrlStateParams = {
    /** Parsed search params object from the route (route.useSearch()). */
    search: SearchRecord;
    /** Router navigate function (route.useNavigate()). */
    navigate: (opts: {
        search:
            | SearchRecord
            | ((prev: SearchRecord) => Partial<SearchRecord> | SearchRecord);
        replace?: boolean;
    }) => void;
    urlState?: UrlStateConfig;
};

type UseDataTableUrlStateReturn = {
    pagination: ReturnType<typeof useTableUrlState>["pagination"];
    onPaginationChange: ReturnType<typeof useTableUrlState>["onPaginationChange"];
    sorting: ReturnType<typeof useTableUrlState>["sorting"];
    onSortingChange: ReturnType<typeof useTableUrlState>["onSortingChange"];
    globalFilter: ReturnType<typeof useTableUrlState>["globalFilter"];
    onGlobalFilterChange: ReturnType<typeof useTableUrlState>["onGlobalFilterChange"];
    columnFilters: ReturnType<typeof useTableUrlState>["columnFilters"];
    onColumnFiltersChange: ReturnType<typeof useTableUrlState>["onColumnFiltersChange"];
    ensurePageInRange: ReturnType<typeof useTableUrlState>["ensurePageInRange"];
    /** First (active) sorting column, or undefined when unsorted. */
    activeSort: { id: string; desc: boolean } | undefined;
    /** Read the single active value of a column filter (array filters return [0]). */
    activeValue: (columnId: string) => string | undefined;
};

/**
 * Part 1 of the shared table wiring: the URL-bound table state. Call this
 * FIRST, then run your page's `useQuery` against the returned state, then
 * call `useDataTable` (part 2) with the query rows + meta. Splitting the two
 * keeps hook ordering valid (query runs between the two table hooks).
 */
export function useDataTableUrlState({
    search,
    navigate,
    urlState,
}: UseDataTableUrlStateParams): UseDataTableUrlStateReturn {
    const url = useTableUrlState({
        search,
        navigate: navigate as unknown as NavigateFn,
        pagination: {
            defaultPage: 1,
            defaultPageSize: PAGINATION.DEFAULT_PAGE_SIZE,
            ...urlState?.pagination,
        },
        sorting: { sortKey: "sort", orderKey: "order", ...urlState?.sorting },
        globalFilter: { enabled: true, key: "filter", ...urlState?.globalFilter },
        columnFilters: urlState?.columnFilters,
    });

    const activeSort = useMemo(() => url.sorting[0], [url.sorting]);

    const activeValue = useMemo(
        () => (columnId: string) => {
            const filter = url.columnFilters.find((f) => f.id === columnId);
            const value = filter?.value as string[] | string | undefined;
            if (Array.isArray(value)) return value[0];
            return typeof value === "string" ? value : undefined;
        },
        [url.columnFilters],
    );

    return {
        pagination: url.pagination,
        onPaginationChange: url.onPaginationChange,
        sorting: url.sorting,
        onSortingChange: url.onSortingChange,
        globalFilter: url.globalFilter,
        onGlobalFilterChange: url.onGlobalFilterChange,
        columnFilters: url.columnFilters,
        onColumnFiltersChange: url.onColumnFiltersChange,
        ensurePageInRange: url.ensurePageInRange,
        activeSort,
        activeValue,
    };
}

type UseDataTableParams<TData extends RowData> = {
    columns: ColumnDef<StockFeatures, TData>[];
    /** Table rows from the page's server query (data?.data ?? []). */
    data: TData[];
    /** Pagination envelope from the server response. */
    meta?: Meta;
    /** State produced by useDataTableUrlState (part 1). */
    url: UseDataTableUrlStateReturn;
};

/**
 * Part 2 of the shared table wiring: the TanStack table instance. Consumes
 * the URL state from `useDataTableUrlState` and builds the table with the
 * stock feature set + column pinning, manual pagination/sorting, and the
 * server's page count.
 */
export function useDataTable<TData extends RowData>({
    columns,
    data,
    meta,
    url,
}: UseDataTableParams<TData>): TanStackTable<StockFeatures, TData> {
    const [columnVisibility, setColumnVisibility] =
        useState<ColumnVisibilityState>({});

    return useTable({
        features: stockFeatures,
        enableColumnPinning: true,
        data,
        columns,
        state: {
            sorting: url.sorting,
            pagination: url.pagination,
            columnVisibility,
            columnFilters: url.columnFilters,
        },
        onSortingChange: url.onSortingChange,
        onPaginationChange: url.onPaginationChange,
        onColumnVisibilityChange: setColumnVisibility,
        onColumnFiltersChange: url.onColumnFiltersChange,
        manualPagination: true,
        manualSorting: true,
        pageCount: meta?.last_page ?? 1,
    });
}
