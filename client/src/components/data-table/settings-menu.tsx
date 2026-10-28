import {
    type RowData,
    type StockFeatures,
    type Table,
} from "@tanstack/react-table";
import { IconSettings } from "@tabler/icons-react";
import { Button } from "@/components/ui/button";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from "@/components/ui/tooltip";
import { cn } from "@/lib/utils";
import { useAuthorization } from "@/features/auth";
import { allowedActions, type DataTableToolbarAction } from "./toolbar-types";
import { ColumnVisibilityMenuItems, hideableColumns } from "./view-options";

type DataTableSettingsProps<TData extends RowData> = {
    table: Table<StockFeatures, TData>;
    /** Rarely-run, structural commands (sync, purge) — permission-gated. */
    settings?: DataTableToolbarAction[];
};

/**
 * The «⚙» menu: the low-traffic half of a table's chrome. A command that
 * is not part of the everyday read/refresh rhythm does not deserve a
 * top-level button — it lives here, next to the column layout it alters.
 *
 * Content and gating are one component, so the desktop dropdown and the
 * mobile «تنظیمات» submenu can never drift: same actions, same order, same
 * permission rule.
 */
export function DataTableSettingsItems<TData extends RowData>({
    table,
    settings,
}: DataTableSettingsProps<TData>) {
    const { can } = useAuthorization();
    const actions = allowedActions(settings, can);
    const hasColumns = hideableColumns(table).length > 0;

    if (actions.length === 0 && !hasColumns) {
        return null;
    }

    return (
        <>
            {actions.map((action) => (
                <DropdownMenuItem
                    key={action.id}
                    onClick={action.onClick}
                    disabled={action.disabled}
                    className={cn(
                        action.destructive && "text-destructive",
                    )}
                >
                    {action.icon && <action.icon className="size-4" />}
                    {action.label}
                </DropdownMenuItem>
            ))}
            {actions.length > 0 && hasColumns && <DropdownMenuSeparator />}
            {hasColumns && (
                <DropdownMenuGroup>
                    <DropdownMenuLabel>
                        نمایش/مخفی کردن ستونها
                    </DropdownMenuLabel>
                    <ColumnVisibilityMenuItems table={table} />
                </DropdownMenuGroup>
            )}
        </>
    );
}

/** Whether this table has anything to put behind the gear at all. */
export function useHasTableSettings<TData extends RowData>(
    table: Table<StockFeatures, TData>,
    settings?: DataTableToolbarAction[],
): boolean {
    const { can } = useAuthorization();

    return (
        allowedActions(settings, can).length > 0 ||
        hideableColumns(table).length > 0
    );
}

/** Desktop gear button. Renders nothing when the table has neither
 *  settings actions nor hideable columns. */
export function DataTableSettingsMenu<TData extends RowData>(
    props: DataTableSettingsProps<TData>,
) {
    if (!useHasTableSettings(props.table, props.settings)) {
        return null;
    }

    return (
        <DropdownMenu>
            <Tooltip>
                <TooltipTrigger
                    render={
                        <DropdownMenuTrigger
                            render={
                                <Button
                                    variant="outline"
                                    size="icon"
                                    className="hidden lg:inline-flex"
                                    aria-label="تنظیمات جدول"
                                >
                                    <IconSettings className="size-4" />
                                </Button>
                            }
                        />
                    }
                />
                <TooltipContent side="bottom">
                    تنظیمات جدول
                </TooltipContent>
            </Tooltip>
            <DropdownMenuContent align="end" className="w-56">
                <DataTableSettingsItems {...props} />
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
