import { Fragment, type ReactNode } from "react";
import { Link } from "@tanstack/react-router";
import { IconChevronDown, IconDotsVertical } from "@tabler/icons-react";

import { Button } from "@/components/ui/button";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { PermissionGuard } from "@/features/auth/components/permission-guard";

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
     * Needed because a bundle can require two permissions at once, and
     * "either" would then offer a download the server refuses.
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
     * first answer arrives, and a second click on a PDF export would queue a
     * second copy of a file the user already has downloading.
     */
    disabled?: boolean;
    /**
     * Render as a menu of choices instead of one button.
     *
     * For the actions a page offers in the same family — four ways to take a
     * copy of the same thing, say. Each is a real button somebody will want,
     * but four of them side by side turns the header into a wall of chrome and
     * leaves «ویرایش» the only thing that still looks like the page's verb.
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
 * Header action buttons that render inline on desktop (md+) and condense into
 * a "…" DropdownMenu on mobile — the same Desktop/Mobile split RowActions
 * uses, but for page headers instead of table rows. Items may carry an
 * optional permission; icon-only actions only show a tooltip label (desktop)
 * and a labelled menu item (mobile).
 *
 * A grouped action flattens into the mobile menu under its own label rather
 * than opening a submenu: a nested menu inside a menu that is already one level
 * deep costs a second tap for no gain, and the mobile menu is where the user is
 * least disposed to tap twice.
 */
export function PageHeaderActions({ actions }: PageHeaderActionsProps) {
    if (actions.length === 0) return null;

    return (
        <div className="flex items-center gap-2">
            <div className="hidden shrink-0 flex-wrap items-center gap-2 md:flex">
                {actions.map((action, i) => {
                    // oxlint-disable-next-line react/no-array-index-key -- static header action config; never reorders
                    const el = <ActionNode key={i} action={action} />;

                    return action.permission
                        ? guard(action.permission, el, String(i))
                        : el;
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
                        {actions.flatMap((action, i) => {
                            const el = action.items ? (
                                <GroupLabel action={action} />
                            ) : (
                                <MenuItem action={action} />
                            );

                            if (action.permission) {
                                return [guard(action.permission, el, String(i))];
                            }

                            // A Fragment takes the key without adding a node:
                            // wrapping a menu item in an element of its own
                            // would put a box between it and the menu's layout.
                            // oxlint-disable-next-line react/no-array-index-key -- static header action config; never reorders
                            return [<Fragment key={i}>{el}</Fragment>];
                        })}
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </div>
    );
}

/** Desktop: a group opens a menu; anything else is one button. */
function ActionNode({ action }: { action: PageHeaderAction }) {
    if (!action.items?.length) {
        return <DesktopAction action={action} />;
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger render={<Button variant="outline" />}>
                {action.icon}
                {action.label}
                <IconChevronDown className="size-3.5 opacity-60" />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" side="bottom">
                <ChoiceList items={action.items} />
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

function GroupLabel({ action }: { action: PageHeaderAction }) {
    return (
        <DropdownMenuLabel>
            {action.icon}
            {action.label}
        </DropdownMenuLabel>
    );
}

/**
 * A group's choices, shared by the desktop menu and the flattened mobile one so
 * the two can never show a different set.
 */
function ChoiceList({ items }: { items: PageHeaderSubAction[] }) {
    return items.map((item, i) => {
        // oxlint-disable-next-line react/no-array-index-key -- static menu config; never reorders
        const el = (
            <>
                {item.separatorBefore && <DropdownMenuSeparator />}
                <DropdownMenuItem
                    disabled={item.disabled}
                    onClick={item.onClick}
                >
                    {item.icon}
                    {item.label}
                </DropdownMenuItem>
            </>
        );

        return item.permission
            ? // oxlint-disable-next-line react/no-array-index-key -- static menu config; never reorders
              guard(item.permission, el, String(i), item.permissionMode)
            : // oxlint-disable-next-line react/no-array-index-key -- static menu config; never reorders
              <Fragment key={i}>{el}</Fragment>;
    });
}

/**
 * Wrap a node in a permission guard.
 *
 * The wrapper is a keyed `span` rather than the fragment itself because a
 * fragment cannot take a key, and a guard that could not be listed among its
 * siblings would make React warn about every item in a menu.
 */
function guard(
    permission: string | string[],
    node: ReactNode,
    key: string,
    mode: "any" | "all" = "any",
) {
    return (
        <span key={key}>
            <PermissionGuard permission={permission} mode={mode}>
                {node}
            </PermissionGuard>
        </span>
    );
}