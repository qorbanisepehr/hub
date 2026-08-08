import { useState, useEffect } from "react";
import {
    type RowData,
    type StockFeatures,
    type Table,
} from "@tanstack/react-table";
import { IconSearch, IconX } from "@tabler/icons-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { DataTableFacetedFilter } from "./faceted-filter";
import { DataTableViewOptions } from "./view-options";

type DataTableToolbarProps<TData extends RowData> = {
    table: Table<StockFeatures, TData>;
    searchPlaceholder?: string;
    searchKey?: string;
    globalFilter?: string;
    onGlobalFilterChange?: (value: string) => void;
    filters?: {
        columnId: string;
        title: string;
        options: {
            label: string;
            value: string;
            icon?: React.ComponentType<{ className?: string }>;
        }[];
    }[];
};

export function DataTableToolbar<TData extends RowData>({
    table,
    searchPlaceholder = "جستجو...",
    searchKey,
    globalFilter,
    onGlobalFilterChange,
    filters = [],
}: DataTableToolbarProps<TData>) {
    const isFiltered =
        table.store.state.columnFilters.length > 0 ||
        !!table.store.state.globalFilter;

    const committedValue = searchKey
        ? ((table.getColumn(searchKey)?.getFilterValue() as string) ?? "")
        : (globalFilter ?? "");

    const [localValue, setLocalValue] = useState(committedValue);

    useEffect(() => {
        setLocalValue(committedValue);
    }, [committedValue]);

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
        <div className="flex items-center justify-between">
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
                                    className="absolute end-7 top-1/2 -translate-y-1/2"
                                >
                                    <IconX className="size-3.5" />
                                </Button>
                            )}
                            <Button
                                variant="ghost"
                                size="icon-xs"
                                onClick={commit}
                                className="absolute end-1 top-1/2 -translate-y-1/2"
                            >
                                <IconSearch className="size-3.5" />
                            </Button>
                        </div>
                    )}
                </div>
                <div className="flex gap-x-2">
                    {filters.map((filter) => {
                        const column = table.getColumn(filter.columnId);
                        if (!column) return null;
                        return (
                            <DataTableFacetedFilter
                                key={filter.columnId}
                                column={column}
                                title={filter.title}
                                options={filter.options}
                            />
                        );
                    })}
                </div>
                {isFiltered && (
                    <Button
                        variant="ghost"
                        onClick={() => {
                            setLocalValue("");
                            table.resetColumnFilters();
                            onGlobalFilterChange?.("");
                        }}
                        className="h-8 px-2 lg:px-3"
                    >
                        پاک کردن
                        <IconX className="ms-2 size-4" />
                    </Button>
                )}
            </div>
            <DataTableViewOptions table={table} />
        </div>
    );
}
