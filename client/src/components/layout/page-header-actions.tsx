import { Fragment, type ReactNode } from "react";
import { Link } from "@tanstack/react-router";
import { IconChevronDown, IconDotsVertical } from "@tabler/icons-react";

import { Button } from "@/components/ui/button";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuSub,
    DropdownMenuSubContent,
    DropdownMenuSubTrigger,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { useAuthorization } from "@/features/auth";

/**
 * One choice inside a grouped action. Same vocabulary as an action, because a
 * menu item that cannot carry a permission, a disabled state or a divider is a
 * menu item that will eventually want one and have nowhere to put it.
 */
export type PageHeaderSubAction = {
    icon?: ReactNode;
    label: string;
    onClick?: () => void;
    disabled?: boolean;
    permission?: string | string[];
    /**
     * "all" demands every listed permission; "any" (the default) demands one.
     * Needed because one choice can require two permissions at once, and
     * "either" would then offer an action the server refuses.
     */
    permissionMode?: "any" | "all";
    /** A divider above this item, to set one kind of choice apart from another. */
    separatorBefore?: boolean;
};

export type PageHeaderAction = {
    icon?: ReactNode;
    label: string;
    onClick?: () => void;
    /** TanStack Router `to` (route path template) — e.g. `/employees/$id/edit`. */
    href?: string;
    params?: Record<string, string>;
    /** Deep-link hash preserved when navigating (e.g. the active detail tab). Use `true` to keep the current hash. */
    hash?: string | true;
    permission?: string | string[];
    /**
     * Greys the action out while it is working. Exists for the async ones: a
     * header button that fires a request must not offer itself twice before the
     * first answer arrives, and a second click on an export would queue a
     * second copy of a file the user already has downloading.
     */
    disabled?: boolean;
    /**
     * Render as a menu of choices instead of one button.
     *
     * For the actions a page offers in the same family — four ways to take a
     * copy of the same thing, say. Each is a real button somebody will want,
     * but four of them side by side turns the header into a wall of chrome and
     * leaves the page's actual verb looking like just another file format.
     *
     * The permission belongs on the ITEMS, not on the group: the trigger would
     * otherwise be hidden by the one permission its rarest item needs, taking
     * the common items with it. Give the group the permission most of its items
     * share, so the trigger disappears exactly when nothing inside can run.
     */
    items?: PageHeaderSubAction[];
};

type PageHeaderActionsProps = {
    actions: PageHeaderAction[];
};

/**
 * Header actions that render inline on desktop (md+) and condense into a "…"
 * menu on mobile — the same Desktop/Mobile split RowActions uses, but for page
 * headers instead of table rows.
 *
 * A grouped action keeps its shape on both: a button that opens a dropdown on
 * desktop, a submenu inside the "…" menu on mobile. The mobile one nests rather
 * than flattening, because "one action with several choices" has to look like
 * the same thing wherever it is — a list of formats appearing out of nowhere,
 * with nothing labelling which action opened them, reads as a different page.
 *
 * Permissions are resolved once, up here, instead of by wrapping each entry in
 * a guard. That matters for the menus specifically: a guard leaves a wrapper
 * element between the popup and its items, and a menu's items are supposed to
 * be its direct children — arrow-key navigation and focus handling both assume
 * it. Filtering is also how a submenu trigger can vanish without taking its
 * parent menu's layout with it.
 */
export function PageHeaderActions({ actions }: PageHeaderActionsProps) {
    const { canAny, canAll } = useAuthorization();

    const allowed = (permission?: string | string[], mode: "any" | "all" = "any"): boolean => {
        if (!permission) return true;

        const names = Array.isArray(permission) ? permission : [permission];

        return mode === "all" ? canAll(names) : canAny(names);
    };

    const permitted = actions.filter((action) => allowed(action.permission));

    if (permitted.length === 0) return null;

    return (
        <div className="flex items-center gap-2">
            <div className="hidden shrink-0 flex-wrap items-center gap-2 md:flex">
                {permitted.map((action) =>
                    action.items?.length ? (
                        <DesktopGroup key={action.label} action={action} items={action.items.filter((item) => allowed(item.permission, item.permissionMode))} />
                    ) : (
                        <DesktopAction key={action.label} action={action} />
                    ),
                )}
            </div>
            <div className="md:hidden">
                <DropdownMenu>
                    <DropdownMenuTrigger
                        render={<Button variant="outline" size="icon-sm" />}
                    >
                        <IconDotsVertical className="size-4" />
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" side="bottom">
                        {permitted.map((action) =>
                            action.items?.length ? (
                                <MobileGroup key={action.label} action={action} items={action.items.filter((item) => allowed(item.permission, item.permissionMode))} />
                            ) : (
                                <MenuItem key={action.label} action={action} />
                            ),
                        )}
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </div>
    );
}

function DesktopGroup({ action, items }: { action: PageHeaderAction; items: PageHeaderSubAction[] }) {
    if (items.length === 0) return null;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger render={<Button variant="outline" />}>
                {action.icon}
                {action.label}
                <IconChevronDown className="size-3.5 opacity-60" />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" side="bottom">
                <ChoiceList items={items} />
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

function DesktopAction({ action }: { action: PageHeaderAction }) {
    const buttonProps = action.href
        ? {
              render: (
                  <Link
                      to={action.href}
                      params={action.params}
                      hash={action.hash}
                  />
              ),
              nativeButton: false as const,
          }
        : { onClick: action.onClick };

    return (
        <Button variant="outline" disabled={action.disabled} {...buttonProps}>
            {action.icon}
            {action.label}
        </Button>
    );
}

function MenuItem({ action }: { action: PageHeaderAction }) {
    const itemProps = action.href
        ? {
              render: (
                  <Link
                      to={action.href}
                      params={action.params}
                      hash={action.hash}
                  />
              ),
          }
        : { onClick: action.onClick };

    return (
        <DropdownMenuItem disabled={action.disabled} {...itemProps}>
            {action.icon}
            {action.label}
        </DropdownMenuItem>
    );
}

/**
 * The same grouped action, one level down: a submenu inside the mobile "…"
 * menu. The trigger is a direct child of the popup, which is what lets the
 * menu's own keyboard navigation reach it.
 */
function MobileGroup({ action, items }: { action: PageHeaderAction; items: PageHeaderSubAction[] }) {
    if (items.length === 0) return null;

    return (
        <DropdownMenuSub>
            <DropdownMenuSubTrigger>
                {action.icon}
                {action.label}
            </DropdownMenuSubTrigger>
            <DropdownMenuSubContent>
                <ChoiceList items={items} />
            </DropdownMenuSubContent>
        </DropdownMenuSub>
    );
}

/**
 * A group's choices, shared by the desktop dropdown and the mobile submenu so
 * the two can never show a different set.
 */
function ChoiceList({ items }: { items: PageHeaderSubAction[] }) {
    return items.map((item) => (
        // A Fragment, so the divider is a SIBLING of the item rather than a
        // child of it: nesting a separator inside a menu item puts a
        // non-interactive element inside the thing keyboard navigation moves
        // between, and the item's own hit area stops matching what it draws.
        <Fragment key={item.label}>
            {item.separatorBefore && <DropdownMenuSeparator />}
            <DropdownMenuItem
                disabled={item.disabled}
                onClick={item.onClick}
            >
                {item.icon}
                {item.label}
            </DropdownMenuItem>
        </Fragment>
    ));
}