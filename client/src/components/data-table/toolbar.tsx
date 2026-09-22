import { useCallback, useEffect, useRef, useState } from "react";
import {
    type RowData,
    type StockFeatures,
    type Table,
} from "@tanstack/react-table";
import {
    IconFilter,
    IconSearch,
    IconTableOptions,
    IconX,
} from "@tabler/icons-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from "@/components/ui/sheet";
import { Label } from "@/components/ui/label";
import { Separator } from "@/components/ui/separator";
import { ColumnVisibilityList, DataTableViewOptions } from "./view-options";
import { DEBOUNCE } from "@/lib/constants";
import { cn } from "@/lib/utils";
import type { DataTableToolbarAction } from "./toolbar-types";

// Debounced auto-commit only kicks in once the query is long enough;
// shorter inputs always wait for Enter so a single keystroke never
// triggers a server round-trip.
const MIN_AUTO_SEARCH_CHARS = 2;

/**
 * One source of truth for every list toolbar, responsive by CSS only (no
 * `useMediaQuery` split, no duplicated mobile/desktop trees):
 *
 * - **Desktop (lg+):** inline row — search · filter bar · action buttons ·
 *   column view-options.
 * - **Mobile:** the search input stretches full width and a single «فیلترها»
 *   button opens a bottom **sheet** (a real dialog, so the reui Filters
 *   popovers nest correctly) holding the filter bar, the action buttons and
 *   the column-visibility list.
 *
 * Actions are descriptors (`DataTableToolbarAction`) so a page declares them
 * ONCE and they render as inline buttons on desktop and full-width buttons in
 * the mobile sheet — pages never wire mobile themselves.
 */
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
     *  mobile sheet buttons alike. DRY per-table contract. */
    actions?: DataTableToolbarAction[];
};

function SearchField({
    placeholder,
    value,
    onValueChange,
    onCommit,
    onClear,
    className,
}: {
    placeholder: string;
    value: string;
    onValueChange: (value: string) => void;
    onCommit: () => void;
    onClear: () => void;
    className?: string;
}) {
    return (
        <div className={className ? `relative ${className}` : "relative"}>
            <span className="pointer-events-none absolute inset-y-0 start-2.5 flex items-center text-muted-foreground">
                <IconSearch className="size-4" />
            </span>
            <Input
                placeholder={placeholder}
                value={value}
                onChange={(e) => onValueChange(e.target.value)}
                onKeyDown={(e) => {
                    if (e.key === "Enter") {
                        e.preventDefault();
                        onCommit();
                    }
                }}
                className="h-8 ps-8 pe-8"
            />
            {value ? (
                <Button
                    type="button"
                    variant="ghost"
                    size="icon-xs"
                    onClick={onClear}
                    aria-label="پاککردن جستجو"
                    className="absolute inset-y-0 end-1 my-auto text-muted-foreground"
                >
                    <IconX className="size-3.5" />
                </Button>
            ) : null}
        </div>
    );
}

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

    const hasSearch = Boolean(searchKey || onGlobalFilterChange);
    const actionList = actions ?? [];
    const [sheetOpen, setSheetOpen] = useState(false);
    // Anything besides search that has to collapse into the mobile sheet.
    const hasOverflow =
        Boolean(filterBar) ||
        actionList.length > 0 ||
        Boolean(toolbarActions) ||
        table
            .getAllColumns()
            .some((c) => typeof c.accessorFn !== "undefined" && c.getCanHide());

    return (
        <div className="flex w-full items-center gap-2">
            {hasSearch ? (
                <SearchField
                    placeholder={searchPlaceholder}
                    value={localValue}
                    onValueChange={handleInputChange}
                    onCommit={commit}
                    onClear={clear}
                    className="w-full lg:w-64 lg:shrink-0"
                />
            ) : null}

            {/* Desktop: everything inline. */}
            <div className="hidden items-center gap-2 lg:flex lg:flex-1 lg:flex-wrap">
                {filterBar}
                {toolbarActions}
                {actionList.map((action) => (
                    <Button
                        key={action.id}
                        variant="outline"
                        onClick={action.onClick}
                        disabled={action.disabled}
                        className={cn(
                            "h-8",
                            action.destructive && "text-destructive",
                        )}
                    >
                        {action.icon && <action.icon className="size-4" />}
                        {action.label}
                    </Button>
                ))}
            </div>
            <DataTableViewOptions table={table} />

            {/* Mobile: one trigger + a bottom sheet (dialog) with everything. */}
            {hasOverflow ? (
                <Sheet open={sheetOpen} onOpenChange={setSheetOpen}>
                    <SheetTrigger
                        render={
                            <Button
                                variant="outline"
                                className="h-8 shrink-0 gap-1.5 px-2.5 lg:hidden"
                            >
                                <IconFilter className="size-4" />
                                <span>فیلترها</span>
                            </Button>
                        }
                    />
                    <SheetContent
                        side="bottom"
                        className="max-h-[80dvh] gap-0 overflow-y-auto rounded-t-2xl p-0"
                    >
                        <SheetHeader className="border-b px-4 py-3">
                            <SheetTitle>فیلترها و عملیات</SheetTitle>
                        </SheetHeader>
                        <div className="flex flex-col gap-4 p-4">
                            {filterBar && (
                                <div className="flex flex-col gap-2">
                                    <Label className="text-xs text-muted-foreground">
                                        فیلترها
                                    </Label>
                                    {filterBar}
                                </div>
                            )}
                            {toolbarActions && (
                                <>
                                    <Separator />
                                    {toolbarActions}
                                </>
                            )}
                            {actionList.length > 0 && (
                                <div className="flex flex-col gap-2">
                                    <Label className="text-xs text-muted-foreground">
                                        عملیات
                                    </Label>
                                    {actionList.map((action) => (
                                        <Button
                                            key={action.id}
                                            variant="outline"
                                            onClick={() => {
                                                // Close the sheet first so a
                                                // dialog the action opens
                                                // (e.g. export) never renders
                                                // behind it.
                                                setSheetOpen(false);
                                                action.onClick();
                                            }}
                                            disabled={action.disabled}
                                            className={
                                                action.destructive
                                                    ? "justify-start text-destructive"
                                                    : "justify-start"
                                            }
                                        >
                                            {action.icon && (
                                                <action.icon className="size-4" />
                                            )}
                                            {action.label}
                                        </Button>
                                    ))}
                                </div>
                            )}
                            <Separator />
                            <div className="flex flex-col gap-2">
                                <Label className="flex items-center gap-2 text-xs text-muted-foreground">
                                    <IconTableOptions className="size-4" />
                                    نمایش ستونها
                                </Label>
                                <ColumnVisibilityList table={table} />
                            </div>
                        </div>
                    </SheetContent>
                </Sheet>
            ) : null}
        </div>
    );
}
