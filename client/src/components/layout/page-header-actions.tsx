import type { ReactNode } from "react";
import { Link } from "@tanstack/react-router";
import { IconDotsVertical } from "@tabler/icons-react";

import { Button } from "@/components/ui/button";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { PermissionGuard } from "@/features/auth/components/permission-guard";

export type PageHeaderAction = {
    icon?: ReactNode;
    label: string;
    onClick?: () => void;
    href?: string;
    permission?: string | string[];
};

type PageHeaderActionsProps = {
    actions: PageHeaderAction[];
};

/**
 * Header action buttons that render inline on desktop (md+) and condense into
 * a "…" DropdownMenu on mobile — the same Desktop/Mobile split RowActions
 * uses, but for page headers instead of table rows. Items may carry an
 * optional permission; icon-only actions only show a tooltip label (desktop)
 * and a labelled menu item (mobile).
 */
export function PageHeaderActions({ actions }: PageHeaderActionsProps) {
    if (actions.length === 0) return null;

    return (
        <div className="flex items-center gap-2">
            <div className="hidden shrink-0 flex-wrap items-center gap-2 md:flex">
                {actions.map((action, i) => {
                    // oxlint-disable-next-line react/no-array-index-key -- static header action config; never reorders
                    let el = <DesktopAction key={i} action={action} />;
                    if (action.permission) {
                        el = (
                            <PermissionGuard
                                // oxlint-disable-next-line react/no-array-index-key -- static header action config; never reorders
                                key={i}
                                permission={action.permission}
                            >
                                {el}
                            </PermissionGuard>
                        );
                    }
                    return el;
                })}
            </div>
            <div className="md:hidden">
                <DropdownMenu>
                    <DropdownMenuTrigger
                        render={<Button variant="outline" size="icon-sm" />}
                    >
                        <IconDotsVertical className="size-4" />
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" side="bottom">
                        {actions.map((action, i) => {
                            // oxlint-disable-next-line react/no-array-index-key -- static header action config; never reorders
                            let el = <MenuItem key={i} action={action} />;
                            if (action.permission) {
                                el = (
                                    <PermissionGuard
                                        // oxlint-disable-next-line react/no-array-index-key -- static header action config; never reorders
                                        key={i}
                                        permission={action.permission}
                                    >
                                        {el}
                                    </PermissionGuard>
                                );
                            }
                            return el;
                        })}
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </div>
    );
}

function DesktopAction({ action }: { action: PageHeaderAction }) {
    const buttonProps = action.href
        ? { render: <Link to={action.href} />, nativeButton: false as const }
        : { onClick: action.onClick };

    return (
        <Button variant="outline" {...buttonProps}>
            {action.icon}
            {action.label}
        </Button>
    );
}

function MenuItem({ action }: { action: PageHeaderAction }) {
    const itemProps = action.href
        ? { render: <Link to={action.href} /> }
        : { onClick: action.onClick };

    return (
        <DropdownMenuItem {...itemProps}>
            {action.icon}
            {action.label}
        </DropdownMenuItem>
    );
}
