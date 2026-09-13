import { useState } from "react";
import {
    type RowData,
    type StockFeatures,
    type Table,
} from "@tanstack/react-table";
import { IconSearch, IconX } from "@tabler/icons-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { DataTableViewOptions } from "./view-options";

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

    const commit = () => {
        if (searchKey) {
            table.getColumn(searchKey)?.setFilterValue(localValue);
        } else {
            onGlobalFilterChange?.(localValue);
        }
    };

    const clear = () => {
        setLocalValue("");
        if (searchKey) {
            table.getColumn(searchKey)?.setFilterValue("");
        } else {
            onGlobalFilterChange?.("");
        }
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
                                onChange={(e) => setLocalValue(e.target.value)}
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
