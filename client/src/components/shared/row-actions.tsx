import { type ReactNode, useState } from "react";
import { Link } from "@tanstack/react-router";
import { IconDotsVertical, IconLoader2, IconTrash } from "@tabler/icons-react";
import { Button } from "@/components/ui/button";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from "@/components/ui/dialog";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Switch } from "@/components/ui/switch";
import { PermissionGuard } from "@/features/auth/components/permission-guard";

type ButtonAction = {
    type?: "button";
    icon: ReactNode;
    label: string;
    onClick?: () => void;
    href?: string;
    permission?: string;
};

type SwitchAction = {
    type: "switch";
    checked: boolean;
    onCheckedChange: (checked: boolean) => void;
    disabled?: boolean;
    permission?: string;
};

type ConfirmDeleteAction = {
    type: "confirm-delete";
    label?: string;
    message?: string;
    onConfirm: () => void;
    isPending?: boolean;
    permission?: string;
};

type RowAction = ButtonAction | SwitchAction | ConfirmDeleteAction;

type RowActionsProps = {
    actions: RowAction[];
};

function ActionButton({ action }: { action: ButtonAction }) {
    const buttonProps = action.href
        ? { render: <Link to={action.href} />, nativeButton: false as const }
        : { onClick: action.onClick };

    return (
        <Button variant="ghost" size="icon-sm" {...buttonProps}>
            {action.icon}
        </Button>
    );
}

function ActionMenuItem({ action }: { action: ButtonAction }) {
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

function DeleteDialogContent({
    label,
    message,
    onConfirm,
    isPending,
    onOpenChange,
}: {
    label: string;
    message: string;
    onConfirm: () => void;
    isPending?: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    return (
        <>
            <DialogHeader>
                <DialogTitle>{label}</DialogTitle>
                <DialogDescription>{message}</DialogDescription>
            </DialogHeader>
            <DialogFooter>
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
            </DialogFooter>
        </>
    );
}

function DesktopActions({ actions }: { actions: RowAction[] }) {
    return (
        <div className="hidden items-center gap-1 md:flex">
            {actions.map((action, i) => {
                let el = <DesktopAction key={i} action={action} />;

                if (action.permission) {
                    el = (
                        <PermissionGuard key={i} permission={action.permission}>
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

function MobileActions({ actions }: { actions: RowAction[] }) {
    return (
        <div className="md:hidden">
            <DropdownMenu>
                <DropdownMenuTrigger
                    render={<Button variant="ghost" size="icon-sm" />}
                >
                    <IconDotsVertical className="size-4" />
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" side="bottom">
                    {actions.map((action, i) => {
                        let el = <MobileAction key={i} action={action} />;

                        if (action.permission) {
                            el = (
                                <PermissionGuard
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
                    {action.checked ? "فعال" : "غیرفعال"}
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
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger render={<Button variant="ghost" size="icon-sm" />}>
                <IconTrash className="size-4" />
            </DialogTrigger>
            <DialogContent>
                <DeleteDialogContent
                    label={label}
                    message={message}
                    onConfirm={onConfirm}
                    isPending={isPending}
                    onOpenChange={setOpen}
                />
            </DialogContent>
        </Dialog>
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
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger
                render={
                    <DropdownMenuItem
                        variant="destructive"
                        onSelect={(e) => e.preventDefault()}
                    />
                }
            >
                <IconTrash className="size-4" />
                {label}
            </DialogTrigger>
            <DialogContent>
                <DeleteDialogContent
                    label={label}
                    message={message}
                    onConfirm={onConfirm}
                    isPending={isPending}
                    onOpenChange={setOpen}
                />
            </DialogContent>
        </Dialog>
    );
}

export function RowActions({ actions }: RowActionsProps) {
    if (actions.length === 0) {
        return null;
    }

    return (
        <div className="flex items-center gap-1">
            <DesktopActions actions={actions} />
            <MobileActions actions={actions} />
        </div>
    );
}
