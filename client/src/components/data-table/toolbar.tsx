import {
    Fragment,
    useCallback,
    useEffect,
    useRef,
    useState,
} from "react";
import {
    type RowData,
    type StockFeatures,
    type Table,
} from "@tanstack/react-table";
import {
    IconDotsVertical,
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
} from "@/components/ui/sheet";
import { Tooltip, TooltipContent, TooltipTrigger } from "@/components/ui/tooltip";
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
} from "@/components/ui/dropdown-menu";
import {
    ColumnVisibilityMenuItems,
    DataTableViewOptions,
} from "./view-options";
import { DEBOUNCE } from "@/lib/constants";
import { cn } from "@/lib/utils";
import type { DataTableToolbarAction } from "./toolbar-types";

// Debounced auto-commit only kicks in once the query is long enough;
// shorter inputs always wait for Enter so a single keystroke never
// triggers a server round-trip.
const MIN_AUTO_SEARCH_CHARS = 2;

/**
 * One source of truth for every list toolbar, responsive by CSS only (no
 * `useMediaQuery` split, no duplicated mobile/desktop trees). Mobile is split
 * by intent:
 *
 * - **Desktop (lg+):** inline row — search · filter bar · icon-only action
 *   buttons (label on tooltip) · column view-options.
 * - **Mobile:** the search input stretches full width and a single «...»
 *   dropdown holds everything as labelled menu items: «فیلترها» (opens a
 *   bottom sheet — a real dialog, so the reui Filters popovers nest
 *   correctly), the commands (export/refresh/...), and a «نمایش ستونها»
 *   submenu. Same idiom as `PageHeaderActions` — one tap per command.
 *
 * Actions are descriptors (`DataTableToolbarAction`) so a page declares them
 * ONCE and they render as icon buttons on desktop and labelled items on
 * mobile — pages never wire mobile themselves.
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
     *  mobile «...» menu items alike. DRY per-table contract. */
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
    const hideableColumnCount = table
        .getAllColumns()
        .filter((c) => typeof c.accessorFn !== "undefined" && c.getCanHide())
        .length;
    // «فیلترها» is a menu item on mobile; clicking it opens the bottom sheet
    // (a real dialog, so the reui Filters popovers nest inside it).
    const [filtersOpen, setFiltersOpen] = useState(false);
    const hasSheet = Boolean(filterBar);

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

            {/* Desktop: everything inline; actions are icon-only + tooltip. */}
            <div className="hidden items-center gap-2 lg:flex lg:flex-1 lg:flex-wrap">
                {filterBar}
                {toolbarActions}
                {actionList.map((action) => {
                    const button = (
                        <Button
                            variant="outline"
                            size="icon"
                            onClick={action.onClick}
                            disabled={action.disabled}
                            aria-label={action.label}
                            className={cn(
                                action.destructive && "text-destructive",
                            )}
                        >
                            {action.icon && (
                                <action.icon className="size-4" />
                            )}
                        </Button>
                    );
                    return (
                        <Fragment key={action.id}>
                            {action.icon ? (
                                <Tooltip>
                                    <TooltipTrigger render={button} />
                                    <TooltipContent side="bottom">
                                        {action.label}
                                    </TooltipContent>
                                </Tooltip>
                            ) : (
                                button
                            )}
                        </Fragment>
                    );
                })}
            </div>
            <DataTableViewOptions table={table} />

            {/* Mobile: everything collapses into one «...» menu with icon +
                text items — «فیلترها» opens the bottom sheet, commands fire
                directly, «نمایش ستونها» is a submenu. One tap per command,
                same idiom as PageHeaderActions. */}
            {(hasSheet ||
                actionList.length > 0 ||
                toolbarActions ||
                hideableColumnCount > 0) && (
                <DropdownMenu>
                    <DropdownMenuTrigger
                        render={
                            <Button
                                variant="outline"
                                className="h-8 shrink-0 lg:hidden"
                                size="icon-sm"
                                aria-label="عملیات"
                            >
                                <IconDotsVertical className="size-4" />
                            </Button>
                        }
                    />
                    <DropdownMenuContent align="end" className="w-48">
                        {hasSheet && (
                            <DropdownMenuItem onClick={() => setFiltersOpen(true)}>
                                <IconFilter className="size-4" />
                                فیلترها
                            </DropdownMenuItem>
                        )}
                        {actionList.map((action) => (
                            <DropdownMenuItem
                                key={action.id}
                                onClick={action.onClick}
                                disabled={action.disabled}
                                className={cn(
                                    action.destructive && "text-destructive",
                                )}
                            >
                                {action.icon && (
                                    <action.icon className="size-4" />
                                )}
                                {action.label}
                            </DropdownMenuItem>
                        ))}
                        {toolbarActions && (
                            <>
                                <DropdownMenuSeparator />
                                <DropdownMenuGroup className="gap-1">
                                    {toolbarActions}
                                </DropdownMenuGroup>
                            </>
                        )}
                        {hideableColumnCount > 0 && (
                            <>
                                <DropdownMenuSeparator />
                                <DropdownMenuSub>
                                    <DropdownMenuSubTrigger>
                                        <IconTableOptions className="size-4" />
                                        نمایش ستونها
                                    </DropdownMenuSubTrigger>
                                    <DropdownMenuPortal>
                                        <DropdownMenuSubContent className="w-48">
                                            <ColumnVisibilityMenuItems
                                                table={table}
                                            />
                                        </DropdownMenuSubContent>
                                    </DropdownMenuPortal>
                                </DropdownMenuSub>
                            </>
                        )}
                    </DropdownMenuContent>
                </DropdownMenu>
            )}

            {hasSheet && (
                <Sheet open={filtersOpen} onOpenChange={setFiltersOpen}>
                    <SheetContent
                        side="bottom"
                        className="max-h-[80dvh] gap-0 overflow-y-auto rounded-t-2xl p-0"
                    >
                        <SheetHeader className="border-b px-4 py-3">
                            <SheetTitle>فیلترها</SheetTitle>
                        </SheetHeader>
                        <div className="flex flex-col gap-2 p-4">
                            {filterBar}
                        </div>
                    </SheetContent>
                </Sheet>
            )}
        </div>
    );
}
