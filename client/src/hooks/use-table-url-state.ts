import { useCallback, useMemo, useRef, useState } from "react";
import type {
    ColumnFiltersState,
    OnChangeFn,
    PaginationState,
    SortingState,
} from "@tanstack/react-table";

type SearchRecord = Record<string, unknown>;

export type NavigateFn = (opts: {
    search:
        | true
        | SearchRecord
        | ((prev: SearchRecord) => Partial<SearchRecord> | SearchRecord);
    replace?: boolean;
}) => void;

type UseTableUrlStateParams = {
    search: SearchRecord;
    navigate: NavigateFn;
    pagination?: {
        pageKey?: string;
        pageSizeKey?: string;
        defaultPage?: number;
        defaultPageSize?: number;
    };
    sorting?: {
        sortKey?: string;
        orderKey?: string;
        defaultSort?: string;
        defaultOrder?: "asc" | "desc";
    };
    globalFilter?: {
        enabled?: boolean;
        key?: string;
        trim?: boolean;
    };
    columnFilters?: Array<
        | {
              columnId: string;
              searchKey: string;
              type?: "string";
              serialize?: (value: unknown) => unknown;
              deserialize?: (value: unknown) => unknown;
          }
        | {
              columnId: string;
              searchKey: string;
              type: "array";
              serialize?: (value: unknown) => unknown;
              deserialize?: (value: unknown) => unknown;
          }
    >;
};

type UseTableUrlStateReturn = {
    globalFilter?: string;
    onGlobalFilterChange?: OnChangeFn<string>;
    columnFilters: ColumnFiltersState;
    onColumnFiltersChange: OnChangeFn<ColumnFiltersState>;
    sorting: SortingState;
    onSortingChange: OnChangeFn<SortingState>;
    pagination: PaginationState;
    onPaginationChange: OnChangeFn<PaginationState>;
    ensurePageInRange: (
        pageCount: number,
        opts?: { resetTo?: "first" | "last" },
    ) => void;
};

export function useTableUrlState(
    params: UseTableUrlStateParams,
): UseTableUrlStateReturn {
    const {
        search,
        navigate,
        pagination: paginationCfg,
        sorting: sortingCfg,
        globalFilter: globalFilterCfg,
        columnFilters: columnFiltersCfg = [],
    } = params;

    const pageKey = paginationCfg?.pageKey ?? ("page" as string);
    const pageSizeKey = paginationCfg?.pageSizeKey ?? ("per_page" as string);
    const defaultPage = paginationCfg?.defaultPage ?? 1;
    const defaultPageSize = paginationCfg?.defaultPageSize ?? 10;

    const sortKey = sortingCfg?.sortKey ?? ("sort" as string);
    const orderKey = sortingCfg?.orderKey ?? ("order" as string);
    const defaultSort = sortingCfg?.defaultSort;
    const defaultOrder = sortingCfg?.defaultOrder ?? "asc";

    const globalFilterKey = globalFilterCfg?.key ?? ("filter" as string);
    const globalFilterEnabled = globalFilterCfg?.enabled ?? true;
    const trimGlobal = globalFilterCfg?.trim ?? true;

    const initialColumnFilters: ColumnFiltersState = useMemo(() => {
        const collected: ColumnFiltersState = [];
        for (const cfg of columnFiltersCfg) {
            const raw = (search as SearchRecord)[cfg.searchKey];
            const deserialize = cfg.deserialize ?? ((v: unknown) => v);
            if (cfg.type === "string") {
                const value = (deserialize(raw) as string) ?? "";
                if (typeof value === "string" && value.trim() !== "") {
                    collected.push({ id: cfg.columnId, value });
                }
            } else {
                const value = (deserialize(raw) as unknown[]) ?? [];
                if (Array.isArray(value) && value.length > 0) {
                    collected.push({ id: cfg.columnId, value });
                }
            }
        }
        return collected;
    }, [columnFiltersCfg, search]);

    const [columnFilters, setColumnFilters] =
        useState<ColumnFiltersState>(initialColumnFilters);

    const sorting: SortingState = useMemo(() => {
        const rawSort = (search as SearchRecord)[sortKey];
        const rawOrder = (search as SearchRecord)[orderKey];
        if (typeof rawSort === "string" && rawSort) {
            const desc = rawOrder === "desc";
            return [{ id: rawSort, desc }];
        }
        if (defaultSort) {
            return [{ id: defaultSort, desc: defaultOrder === "desc" }];
        }
        return [];
    }, [search, sortKey, orderKey, defaultSort, defaultOrder]);

    const pagination: PaginationState = useMemo(() => {
        const rawPage = (search as SearchRecord)[pageKey];
        const rawPageSize = (search as SearchRecord)[pageSizeKey];
        const pageNum = typeof rawPage === "number" ? rawPage : defaultPage;
        const pageSizeNum =
            typeof rawPageSize === "number" ? rawPageSize : defaultPageSize;
        return { pageIndex: Math.max(0, pageNum - 1), pageSize: pageSizeNum };
    }, [search, pageKey, pageSizeKey, defaultPage, defaultPageSize]);

    const [globalFilter, setGlobalFilter] = useState<string | undefined>(() => {
        if (!globalFilterEnabled) return undefined;
        const raw = (search as SearchRecord)[globalFilterKey];
        return typeof raw === "string" ? raw : "";
    });

    const navigateRef = useRef(navigate);
    navigateRef.current = navigate;

    const paginationRef = useRef(pagination);
    paginationRef.current = pagination;

    const sortingRef = useRef(sorting);
    sortingRef.current = sorting;

    const globalFilterRef = useRef(globalFilter);
    globalFilterRef.current = globalFilter;

    const columnFiltersRef = useRef(columnFilters);
    columnFiltersRef.current = columnFilters;

    const keysRef = useRef({
        pageKey,
        pageSizeKey,
        defaultPage,
        defaultPageSize,
        sortKey,
        orderKey,
        globalFilterKey,
        trimGlobal,
    });
    keysRef.current = {
        pageKey,
        pageSizeKey,
        defaultPage,
        defaultPageSize,
        sortKey,
        orderKey,
        globalFilterKey,
        trimGlobal,
    };

    const columnFiltersCfgRef = useRef(columnFiltersCfg);
    columnFiltersCfgRef.current = columnFiltersCfg;

    const onPaginationChange: OnChangeFn<PaginationState> = useCallback(
        (updater) => {
            const current = paginationRef.current;
            const next =
                typeof updater === "function" ? updater(current) : updater;
            const keys = keysRef.current;
            const nextPage = next.pageIndex + 1;
            const nextPageSize = next.pageSize;
            navigateRef.current({
                search: (prev) => ({
                    ...(prev as SearchRecord),
                    [keys.pageKey]:
                        nextPage <= keys.defaultPage ? undefined : nextPage,
                    [keys.pageSizeKey]:
                        nextPageSize === keys.defaultPageSize
                            ? undefined
                            : nextPageSize,
                }),
            });
        },
        [],
    );

    const onSortingChange: OnChangeFn<SortingState> = useCallback(
        (updater) => {
            const current = sortingRef.current;
            const next =
                typeof updater === "function" ? updater(current) : updater;
            const keys = keysRef.current;
            const currentSort = next[0];
            if (!currentSort) {
                navigateRef.current({
                    search: (prev) => ({
                        ...(prev as SearchRecord),
                        [keys.pageKey]: undefined,
                        [keys.sortKey]: undefined,
                        [keys.orderKey]: undefined,
                    }),
                });
                return;
            }
            navigateRef.current({
                search: (prev) => ({
                    ...(prev as SearchRecord),
                    [keys.pageKey]: undefined,
                    [keys.sortKey]: currentSort.id,
                    [keys.orderKey]: currentSort.desc ? "desc" : "asc",
                }),
            });
        },
        [],
    );

    const onGlobalFilterChange: OnChangeFn<string> | undefined =
        globalFilterEnabled
            ? useCallback(
                  (updater) => {
                      const current = globalFilterRef.current ?? "";
                      const next =
                          typeof updater === "function"
                              ? updater(current)
                              : updater;
                      const keys = keysRef.current;
                      const value = keys.trimGlobal ? next.trim() : next;
                      setGlobalFilter(value);
                      navigateRef.current({
                          search: (prev) => ({
                              ...(prev as SearchRecord),
                              [keys.pageKey]: undefined,
                              [keys.globalFilterKey]: value
                                  ? value
                                  : undefined,
                          }),
                      });
                  },
                  [globalFilterEnabled],
              )
            : undefined;

    const onColumnFiltersChange: OnChangeFn<ColumnFiltersState> = useCallback(
        (updater) => {
            const current = columnFiltersRef.current;
            const next =
                typeof updater === "function" ? updater(current) : updater;
            setColumnFilters(next);

            const patch: Record<string, unknown> = {};
            const cfgs = columnFiltersCfgRef.current;

            for (const cfg of cfgs) {
                const found = next.find((f) => f.id === cfg.columnId);
                const serialize = cfg.serialize ?? ((v: unknown) => v);
                if (cfg.type === "string") {
                    const value =
                        typeof found?.value === "string"
                            ? (found.value as string)
                            : "";
                    patch[cfg.searchKey] =
                        value.trim() !== "" ? serialize(value) : undefined;
                } else {
                    const value = Array.isArray(found?.value)
                        ? (found!.value as unknown[])
                        : [];
                    patch[cfg.searchKey] =
                        value.length > 0 ? serialize(value) : undefined;
                }
            }

            const keys = keysRef.current;
            navigateRef.current({
                search: (prev) => ({
                    ...(prev as SearchRecord),
                    [keys.pageKey]: undefined,
                    ...patch,
                }),
            });
        },
        [],
    );

    const ensurePageInRange = useCallback(
        (
            pageCount: number,
            opts: { resetTo?: "first" | "last" } = { resetTo: "first" },
        ) => {
            const keys = keysRef.current;
            const currentPage = (search as SearchRecord)[keys.pageKey];
            const pageNum =
                typeof currentPage === "number"
                    ? currentPage
                    : keys.defaultPage;
            if (pageCount > 0 && pageNum > pageCount) {
                navigateRef.current({
                    replace: true,
                    search: (prev) => ({
                        ...(prev as SearchRecord),
                        [keys.pageKey]:
                            opts.resetTo === "last" ? pageCount : undefined,
                    }),
                });
            }
        },
        [search],
    );

    return {
        globalFilter: globalFilterEnabled ? (globalFilter ?? "") : undefined,
        onGlobalFilterChange,
        columnFilters,
        onColumnFiltersChange,
        sorting,
        onSortingChange,
        pagination,
        onPaginationChange,
        ensurePageInRange,
    };
}
