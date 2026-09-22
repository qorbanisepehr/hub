import { useCallback, useEffect, useRef, useState } from "react";
import {
    type RowData,
    type StockFeatures,
    type Table,
} from "@tanstack/react-table";
import { IconFilter, IconMenu2, IconSearch, IconX } from "@tabler/icons-react";
import { useMediaQuery } from "@/hooks/use-media-query";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { DataTableViewOptions } from "./view-options";
import { DEBOUNCE } from "@/lib/constants";
import type { DataTableToolbarAction } from "./toolbar-types";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuPortal,
    DropdownMenuSeparator,
    DropdownMenuSub,
    DropdownMenuSubContent,
    DropdownMenuSubTrigger,
    DropdownMenuTrigger,
} from "../ui/dropdown-menu";

// Debounced auto-commit only kicks in once the query is long enough;
// shorter inputs always wait for Enter so a single keystroke never
// triggers a server round-trip.
const MIN_AUTO_SEARCH_CHARS = 2;

// A single source of truth controls both the desktop and mobile toolbar:
// the page supplies actions/filters ONCE as descriptors and this component
// renders them as inline Buttons on lg+ and as DropdownMenuItems inside the
// mobile «بیشتر» menu on smaller screens. No per-page responsive wiring.
const DESKTOP_QUERY = "(min-width: 1024px)";

type DataTableToolbarProps<TData extends RowData> = {
    table: Table<StockFeatures, TData>;
    searchPlaceholder?: string;
    searchKey?: string;
    globalFilter?: string;
    onGlobalFilterChange?: (value: string) => void;
    filterBar?: React.ReactNode;
    /** Extra controls rendered inline beside the search row. */
    toolbarActions?: React.ReactNode;
    /** Descriptor-based actions: one source, rendered as desktop Buttons and
     *  mobile menu items alike. DRY per-table contract. */
    actions?: DataTableToolbarAction[];
};

export function DataTableToolbar<TData extends RowData>({
    table,
    searchPlaceholder = "جستجو...",
    searchKey,
    globalFilter,
    onGlobalFilterChange,
    filterBar,
    toolbarActions,
    actions,
}: DataTableToolbarProps<TData>) {
    const isDesktop = useMediaQuery(DESKTOP_QUERY);

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
        // Auto-commit shortly after the user stops typing, but only for
        // queries of MIN_AUTO_SEARCH_CHARS or more; a URL re-sync (or
        // Enter/clear below) never re-arms it because the value matches.
        if (value.trim().length < MIN_AUTO_SEARCH_CHARS) return;
        if (value === committedValue) return;
        debounceRef.current = setTimeout(() => {
            debounceRef.current = null;
            commitWith(value);
        }, DEBOUNCE.SEARCH);
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

    // const isFiltered = table.store.state.columnFilters.length > 0;

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
                                className="absolute inset-e-1 top-1/2 -translate-y-1/2! active:-translate-y-1/2! active:mt-px! py-4 persist!"
                            >
                                <IconSearch className="size-3.5" />
                            </Button>
                        </div>
                    )}
                    {isDesktop && (
                        <>
                            {filterBar && (
                                <div className="flex shrink-0 items-center gap-x-2">
                                    {filterBar}
                                </div>
                            )}
                            {actions?.map((action) => (
                                <Button
                                    key={action.id}
                                    variant="outline"
                                    onClick={action.onClick}
                                    disabled={action.disabled}
                                >
                                    {action.icon && (
                                        <action.icon className="size-4" />
                                    )}
                                    {action.label}
                                </Button>
                            ))}
                        </>
                    )}
                </div>
                <div className="flex flex-1 items-center justify-end gap-x-2">
                    {isDesktop && toolbarActions && (
                        <div className="flex shrink-0 items-center gap-x-2">
                            {toolbarActions}
                        </div>
                    )}
                    {isDesktop && <DataTableViewOptions table={table} />}
                </div>
            </div>
            {actions && (
                <div className="lg:hidden">
                    <DropdownMenu>
                        <DropdownMenuTrigger
                            render={
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="h-8"
                                >
                                    <IconMenu2 className="size-4" />
                                </Button>
                            }
                        />
                        <DropdownMenuContent align="end" className="w-48">
                            {filterBar && (
                                <DropdownMenuGroup className="p-1.5">
                                    {filterBar}
                                </DropdownMenuGroup>
                            )}
                            <DropdownMenuSeparator />
                            <DropdownMenuSub>
                                <DropdownMenuSubTrigger>
                                    اکشنها
                                </DropdownMenuSubTrigger>
                                <DropdownMenuPortal>
                                    <DropdownMenuSubContent>
                                        {actions.map((action) => (
                                            <div key={action.id} />
                                        ))}
                                    </DropdownMenuSubContent>
                                </DropdownMenuPortal>
                            </DropdownMenuSub>
                            <DropdownMenuSeparator />
                            <DropdownMenuGroup className="p-1.5">
                                <DataTableViewOptions table={table} />
                            </DropdownMenuGroup>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            )}
            {!actions && (
                <div className="lg:hidden">
                    <DropdownMenu>
                        <DropdownMenuTrigger
                            render={
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="h-8"
                                >
                                    <IconMenu2 className="size-4" />
                                </Button>
                            }
                        />
                        <DropdownMenuContent align="end" className="w-48">
                            {filterBar && (
                                <DropdownMenuGroup className="p-1.5">
                                    {filterBar}
                                </DropdownMenuGroup>
                            )}
                            <DropdownMenuSeparator />
                            <DropdownMenuGroup className="p-1.5">
                                {toolbarActions}
                            </DropdownMenuGroup>
                            <DropdownMenuSeparator />
                            <DropdownMenuGroup className="p-1.5">
                                <DataTableViewOptions table={table} />
                            </DropdownMenuGroup>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            )}
        </div>
    );
}

/**
 * Mobile «بیشتر» more-menu: on small screens every control except the inline
 * search input collapses into one dropdown. Search stays put above it; the
 * filter bar, page actions (export/refresh) and column view-options all live
 * inside the menu. Shared across every grid — no per-page mobile wiring.
 */
export function MobileMoreMenu({
    filterBar,
    toolbarActions,
    viewOptions,
    actions,
}: {
    filterBar?: React.ReactNode;
    toolbarActions?: React.ReactNode;
    viewOptions?: React.ReactNode;
    /** Descriptor-based actions: one source, rendered as desktop Buttons and
     *  mobile menu items alike. DRY per-table contract. */
    actions?: DataTableToolbarAction[];
}) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger
                render={
                    <Button variant="outline" size="sm" className="h-8">
                        <IconMenu2 className="size-4" />
                    </Button>
                }
            />
            <DropdownMenuContent align="end" className="w-56">
                {filterBar && (
                    <DropdownMenuSub>
                        <DropdownMenuSubTrigger className="gap-x-2">
                            <IconFilter className="size-4" />
                            فیلترها
                        </DropdownMenuSubTrigger>
                        <DropdownMenuPortal>
                            <DropdownMenuSubContent className="w-48">
                                <DropdownMenuGroup className="p-1.5">
                                    {filterBar}
                                </DropdownMenuGroup>
                            </DropdownMenuSubContent>
                        </DropdownMenuPortal>
                    </DropdownMenuSub>
                )}
                {(actions?.length ?? 0) > 0 && (
                    <DropdownMenuGroup className="p-1.5">
                        {actions!.map((action) => (
                            <DropdownMenuItem
                                key={action.id}
                                onClick={action.onClick}
                                disabled={action.disabled}
                            >
                                {action.icon && (
                                    <action.icon className="size-4" />
                                )}
                                {action.label}
                            </DropdownMenuItem>
                        ))}
                    </DropdownMenuGroup>
                )}
                {toolbarActions && (
                    <DropdownMenuSub>
                        <DropdownMenuSubTrigger className="gap-x-2">
                            <IconMenu2 className="size-4" />
                            بیشتر
                        </DropdownMenuSubTrigger>
                        <DropdownMenuPortal>
                            <DropdownMenuSubContent className="w-56">
                                <DropdownMenuGroup className="p-1.5">
                                    {toolbarActions}
                                </DropdownMenuGroup>
                            </DropdownMenuSubContent>
                        </DropdownMenuPortal>
                    </DropdownMenuSub>
                )}
                <DropdownMenuSeparator />
                <div className="px-2 py-1.5">{viewOptions}</div>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
