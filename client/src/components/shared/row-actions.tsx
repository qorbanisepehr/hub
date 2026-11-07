import { type ReactNode, useState } from "react";
import { Link } from "@tanstack/react-router";
import { IconDotsVertical, IconLoader2, IconTrash } from "@tabler/icons-react";
import { Button } from "@/components/ui/button";
import { ResponsiveDialog } from "@/components/ui/responsive-dialog";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Switch } from "@/components/ui/switch";
import { PermissionGuard, usePermission } from "@/features/auth/components/permission-guard";

export type ButtonAction = {
    type?: "button";
    icon: ReactNode;
    label: string;
    onClick?: () => void;
    href?: string;
    disabled?: boolean;
    /** `href` is not an app route (an API download URL, another site), so it
     *  must not be handed to the router. */
    external?: boolean;
    permission?: string;
};

export type SwitchAction = {
    type: "switch";
    checked: boolean;
    onCheckedChange: (checked: boolean) => void;
    disabled?: boolean;
    permission?: string;
};

export type ConfirmDeleteAction = {
    type: "confirm-delete";
    label?: string;
    message?: string;
    onConfirm: () => void;
    isPending?: boolean;
    permission?: string;
};

export type RowAction = ButtonAction | SwitchAction | ConfirmDeleteAction;

type RowActionsProps = {
    actions: RowAction[];
    /** "auto" is the table cell: inline icons on desktop, «...» on mobile.
     *  "menu" is one «...» at every width — the card header's shape, where an
     *  inline row of icons would compete with the record it labels. */
    variant?: "auto" | "menu";
    /** An ancestor is itself a link (a card), so menu clicks must not also
     *  navigate to the record. */
    stopPropagation?: boolean;
};

function ActionButton({ action }: { action: ButtonAction }) {
    const buttonProps = action.href
        ? action.external
            ? {
                  render: (
                      <a
                          href={action.href}
                          target="_blank"
                          rel="noopener noreferrer"
                      />
                  ),
                  nativeButton: false as const,
              }
            : { render: <Link to={action.href} />, nativeButton: false as const }
        : { onClick: action.onClick, disabled: action.disabled };

    return (
        <Button variant="ghost" size="icon-sm" aria-label={action.label} {...buttonProps}>
            {action.icon}
        </Button>
    );
}

function ActionMenuItem({ action }: { action: ButtonAction }) {
    const itemProps = action.href
        ? action.external
            ? {
                  render: (
                      <a
                          href={action.href}
                          target="_blank"
                          rel="noopener noreferrer"
                      />
                  ),
              }
            : { render: <Link to={action.href} /> }
        : { onClick: action.onClick, disabled: action.disabled };

    return (
        <DropdownMenuItem {...itemProps}>
            {action.icon}
            {action.label}
        </DropdownMenuItem>
    );
}

function DeleteDialogContent({
    label,
    onConfirm,
    isPending,
    onOpenChange,
}: {
    label: string;
    onConfirm: () => void;
    isPending?: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    return (
        <>
            <div className="flex items-center justify-end gap-2 pt-2">
                <Button
                    type="button"
                    variant="outline"
                    onClick={() => onOpenChange(false)}
                >
                    انصراف
                </Button>
                <Button
                    variant="destructive"
                    disabled={isPending}
                    onClick={() => {
                        onConfirm();
                        onOpenChange(false);
                    }}
                >
                    {isPending ? (
                        <IconLoader2 className="size-4 animate-spin" />
                    ) : (
                        <IconTrash className="size-4" />
                    )}
                    {label}
                </Button>
            </div>
        </>
    );
}

function DesktopActions({ actions }: { actions: RowAction[] }) {
    return (
        <div className="hidden items-center gap-1 md:flex">
            {actions.map((action, i) => {
                // oxlint-disable-next-line react/no-array-index-key -- static per-row action config; list never reorders
                let el = <DesktopAction key={i} action={action} />;

                if (action.permission) {
                    el = (
                        <PermissionGuard
                            // oxlint-disable-next-line react/no-array-index-key -- static per-row action config; list never reorders
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
    );
}

function DesktopAction({ action }: { action: RowAction }) {
    if (action.type === "switch") {
        return (
            <Switch
                size="sm"
                checked={action.checked}
                onCheckedChange={action.onCheckedChange}
                disabled={action.disabled}
            />
        );
    }

    if (action.type === "confirm-delete") {
        return (
            <DeleteConfirmButton
                label={action.label}
                message={action.message}
                onConfirm={action.onConfirm}
                isPending={action.isPending}
            />
        );
    }

    return <ActionButton action={action} />;
}

function ActionMenu({
    actions,
    className,
    stopPropagation = false,
}: {
    actions: RowAction[];
    className?: string;
    stopPropagation?: boolean;
}) {
    return (
        <div className={className}>
            <DropdownMenu>
                <DropdownMenuTrigger
                    render={<Button variant="ghost" size="icon-sm" aria-label="عملیات" />}
                    onClick={
                        stopPropagation
                            ? (event) => {
                                  // The click is the menu's, not the link's.
                                  event.stopPropagation();
                                  event.preventDefault();
                              }
                            : undefined
                    }
                >
                    <IconDotsVertical className="size-4" />
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" side="bottom">
                    {actions.map((action, i) => {
                        // oxlint-disable-next-line react/no-array-index-key -- static per-row action config; list never reorders
                        let el = <MobileAction key={i} action={action} />;

                        if (action.permission) {
                            el = (
                                <PermissionGuard
                                    // oxlint-disable-next-line react/no-array-index-key -- static per-row action config; list never reorders
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
    );
}

function MobileAction({ action }: { action: RowAction }) {
    if (action.type === "switch") {
        return (
            <div className="flex items-center justify-between px-1.5 py-1">
                <span className="text-sm">
                    {action.checked ? "غیرفعال کردن" : "فعال کردن"}
                </span>
                <Switch
                    size="sm"
                    checked={action.checked}
                    onCheckedChange={action.onCheckedChange}
                    disabled={action.disabled}
                />
            </div>
        );
    }

    if (action.type === "confirm-delete") {
        return (
            <DeleteConfirmMenuItem
                label={action.label}
                message={action.message}
                onConfirm={action.onConfirm}
                isPending={action.isPending}
            />
        );
    }

    return <ActionMenuItem action={action} />;
}

function DeleteConfirmButton({
    label = "حذف",
    message = "آیا از حذف این مورد اطمینان دارید؟",
    onConfirm,
    isPending,
}: {
    label?: string;
    message?: string;
    onConfirm: () => void;
    isPending?: boolean;
}) {
    const [open, setOpen] = useState(false);

    return (
        <ResponsiveDialog
            open={open}
            onOpenChange={setOpen}
            title={label}
            description={message}
            trigger={
                <Button variant="ghost" size="icon-sm">
                    <IconTrash className="size-4" />
                </Button>
            }
        >
            <DeleteDialogContent
                label={label}
                onConfirm={onConfirm}
                isPending={isPending}
                onOpenChange={setOpen}
            />
        </ResponsiveDialog>
    );
}

function DeleteConfirmMenuItem({
    label = "حذف",
    message = "آیا از حذف این مورد اطمینان دارید؟",
    onConfirm,
    isPending,
}: {
    label?: string;
    message?: string;
    onConfirm: () => void;
    isPending?: boolean;
}) {
    const [open, setOpen] = useState(false);

    return (
        <ResponsiveDialog
            open={open}
            onOpenChange={setOpen}
            title={label}
            description={message}
            trigger={
                <DropdownMenuItem variant="destructive">
                    <IconTrash className="size-4" />
                    {label}
                </DropdownMenuItem>
            }
        >
            <DeleteDialogContent
                label={label}
                onConfirm={onConfirm}
                isPending={isPending}
                onOpenChange={setOpen}
            />
        </ResponsiveDialog>
    );
}

export function RowActions({
    actions,
    variant = "auto",
    stopPropagation = false,
}: RowActionsProps) {
    // A menu whose every item is permission-hidden must not render at all,
    // or the card offers a «...» that opens onto nothing. Ungated actions are
    // always visible, so one of them is enough to keep the trigger.
    const hasUngated = actions.some((action) => !action.permission);
    const gated = actions
        .map((action) => action.permission)
        .filter((permission): permission is string => Boolean(permission));
    const canAnyGated = usePermission(gated);

    if (actions.length === 0 || (!hasUngated && !canAnyGated)) {
        return null;
    }

    if (variant === "menu") {
        return (
            <ActionMenu actions={actions} stopPropagation={stopPropagation} />
        );
    }

    return (
        <div className="flex items-center gap-1">
            <DesktopActions actions={actions} />
            <ActionMenu actions={actions} className="md:hidden" />
        </div>
    );
}
