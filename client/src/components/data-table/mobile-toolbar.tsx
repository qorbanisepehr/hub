import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Button } from "@/components/ui/button";
import { IconDots } from "@tabler/icons-react";

type MobileTableToolbarProps = {
    /** Search/filter controls (beside the search input; hidden on mobile -> inside the menu). */
    filterBar?: React.ReactNode;
    toolbarActions?: React.ReactNode;
    viewOptions?: React.ReactNode;
};

/**
 * Mobile-only «بیشتر» menu: collapses everything except the inline search
 * input into one dropdown. Shared by every DataTablePage grid (audit-log,
 * form-options, …) so mobile never wraps controls onto a second row.
 */
export function MobileTableToolbar({
    filterBar,
    toolbarActions,
    viewOptions,
}: MobileTableToolbarProps) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger
                render={
                    <Button variant="outline" size="sm" className="h-8">
                        بیشتر
                    </Button>
                }
            />
            <DropdownMenuContent align="end" className="w-56">
                {filterBar && (
                    <>
                        <div className="px-2 py-1.5">{filterBar}</div>
                        <DropdownMenuSeparator />
                    </>
                )}
                {toolbarActions && (
                    <>
                        <div className="px-2 py-1.5">{toolbarActions}</div>
                        <DropdownMenuSeparator />
                    </>
                )}
                {viewOptions}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
