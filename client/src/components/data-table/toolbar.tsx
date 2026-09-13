import { useCallback, useEffect, useRef, useState } from "react";
import {
    type RowData,
    type StockFeatures,
    type Table,
} from "@tanstack/react-table";
import { IconSearch, IconX } from "@tabler/icons-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { DataTableViewOptions } from "./view-options";

const SEARCH_DEBOUNCE_MS = 400;

type DataTableToolbarProps<TData extends RowData> = {
    table: Table<StockFeatures, TData>;
    searchPlaceholder?: string;
    searchKey?: string;
    globalFilter?: string;
    onGlobalFilterChange?: (value: string) => void;
    filterBar?: React.ReactNode;
};

export function DataTableToolbar<TData extends RowData>({
    table,
    searchPlaceholder = "جستجو...",
    searchKey,
    globalFilter,
    onGlobalFilterChange,
    filterBar,
}: DataTableToolbarProps<TData>) {
    const isFiltered =
        table.store.state.columnFilters.length > 0 ||
        !!table.store.state.globalFilter;

    const committedValue = searchKey
        ? ((table.getColumn(searchKey)?.getFilterValue() as string) ?? "")
        : (globalFilter ?? "");

    const [localValue, setLocalValue] = useState(committedValue);
    const [lastCommitted, setLastCommitted] = useState(committedValue);
    if (committedValue !== lastCommitted) {
        // External filter change (e.g. URL-persisted filters): re-sync the
        // local input during render instead of in an effect.
        setLastCommitted(committedValue);
        setLocalValue(committedValue);
    }

    const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    const cancelPending = useCallback(() => {
        if (debounceRef.current) {
            clearTimeout(debounceRef.current);
            debounceRef.current = null;
        }
    }, []);

    // Clear any queued auto-commit on unmount; [] is correct because the
    // handler below never re-arms the timer on its own.
    useEffect(() => cancelPending, [cancelPending]);

    const commitWith = (value: string) => {
        if (searchKey) {
            table.getColumn(searchKey)?.setFilterValue(value);
        } else {
            onGlobalFilterChange?.(value);
        }
    };

    const handleInputChange = (value: string) => {
        setLocalValue(value);
        cancelPending();
        // Auto-commit shortly after the user stops typing; a URL re-sync (or
        // Enter/clear below) never re-arms it because the value matches.
        if (value === committedValue) return;
        debounceRef.current = setTimeout(() => {
            debounceRef.current = null;
            commitWith(value);
        }, SEARCH_DEBOUNCE_MS);
    };

    const commit = () => {
        cancelPending();
        commitWith(localValue);
    };

    const clear = () => {
        cancelPending();
        setLocalValue("");
        commitWith("");
    };

    return (
        <div className="flex items-center justify-between w-full">
            <div className="flex flex-1 flex-col-reverse items-start gap-y-2 sm:flex-row sm:items-center sm:space-x-2">
                <div className="flex gap-x-2">
                    {(searchKey || onGlobalFilterChange) && (
                        <div className="relative flex items-center">
                            <Input
                                placeholder={searchPlaceholder}
                                value={localValue}
                                onChange={(e) =>
                                    handleInputChange(e.target.value)
                                }
                                onKeyDown={(e) => {
                                    if (e.key === "Enter") {
                                        e.preventDefault();
                                        commit();
                                    }
                                }}
                                className="h-8 w-38 pe-8 lg:w-64"
                            />
                            {localValue && (
                                <Button
                                    variant="ghost"
                                    size="icon-xs"
                                    onClick={clear}
                                    className="absolute inset-e-7 top-1/2 -translate-y-1/2"
                                >
                                    <IconX className="size-3.5" />
                                </Button>
                            )}
                            <Button
                                variant="ghost"
                                size="icon-xs"
                                onClick={commit}
                                disabled={!localValue}
                                className="absolute inset-e-1 top-1/2 -translate-y-1/2! active:-translate-y-1/2! active:mt-px!"
                            >
                                <IconSearch className="size-3.5" />
                            </Button>
                        </div>
                    )}
                </div>
                <div className="flex gap-x-2">{filterBar}</div>
                {isFiltered && !filterBar && (
                    <Button
                        variant="ghost"
                        onClick={() => {
                            setLocalValue("");
                            table.resetColumnFilters();
                            onGlobalFilterChange?.("");
                        }}
                        className="h-8 px-2 lg:px-3"
                    >
                        <IconX className="ms-2 size-4" />
                    </Button>
                )}
            </div>
            <DataTableViewOptions table={table} />
        </div>
    );
}
