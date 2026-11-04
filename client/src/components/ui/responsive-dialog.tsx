import * as React from "react";

import { Dialog, DialogContent, DialogTrigger } from "@/components/ui/dialog";
import {
    Drawer,
    DrawerContent,
    DrawerDescription,
    DrawerFooter,
    DrawerHeader,
    DrawerTitle,
    DrawerTrigger,
} from "@/components/ui/drawer";
import { cn } from "@/lib/utils";
import { useMediaQuery } from "@/hooks/use-media-query";

/** Desktop dialog width. The mobile drawer is always full-width. */
type ResponsiveDialogSize = "md" | "lg" | "2xl";

const DESKTOP_WIDTH: Record<ResponsiveDialogSize, string> = {
    md: "sm:max-w-md",
    lg: "sm:max-w-lg",
    "2xl": "sm:max-w-2xl",
};

type ResponsiveDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title?: React.ReactNode;
    description?: React.ReactNode;
    /** Desktop dialog width; defaults to `lg`. */
    size?: ResponsiveDialogSize;
    /** Header-level actions (menus, buttons) aligned with the title row. */
    actions?: React.ReactNode;
    /** Optional controlled trigger element rendered for both dialog and drawer. */
    trigger?: React.ReactElement;
    children?: React.ReactNode;
    footer?: React.ReactNode;
};

export function ResponsiveDialog({
    open,
    onOpenChange,
    title,
    description,
    size = "lg",
    actions,
    trigger,
    children,
    footer,
}: ResponsiveDialogProps) {
    const isDesktop = useMediaQuery("(min-width: 768px)");

    if (isDesktop) {
        return (
            <Dialog open={open} onOpenChange={onOpenChange}>
                {trigger && <DialogTrigger render={trigger} />}
                <DialogContent
                    showCloseButton
                    className={cn(
                        "flex max-h-[85dvh] flex-col gap-0 overflow-clip p-0",
                        DESKTOP_WIDTH[size],
                    )}
                >
                    {title && (
                        <div className="flex items-start gap-2 px-4 pt-4">
                            <div className="min-w-0 flex-1">
                                <h2 className="font-heading text-base font-medium">
                                    {title}
                                </h2>
                                {description && (
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        {description}
                                    </p>
                                )}
                            </div>
                            {actions && (
                                // pe clears the absolutely-positioned close button.
                                <div className="flex shrink-0 items-center gap-1 pe-10">
                                    {actions}
                                </div>
                            )}
                        </div>
                    )}
                    {children && (
                        <div className="min-h-0 flex-1 overflow-y-auto overflow-x-clip p-4">
                            {children}
                        </div>
                    )}
                    {footer && (
                        <div className="flex shrink-0 items-center gap-2 border-t p-4">
                            {footer}
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        );
    }

    return (
        <Drawer open={open} onOpenChange={onOpenChange}>
            {trigger && <DrawerTrigger render={trigger} />}
            <DrawerContent className="flex max-h-[85dvh] flex-col">
                <DrawerHeader className="flex items-start gap-2">
                    <div className="min-w-0 flex-1">
                        {title && <DrawerTitle>{title}</DrawerTitle>}
                        {description && (
                            <DrawerDescription className="mt-1">
                                {description}
                            </DrawerDescription>
                        )}
                    </div>
                    {actions && (
                        <div className="flex shrink-0 items-center gap-1">
                            {actions}
                        </div>
                    )}
                </DrawerHeader>
                {children && (
                    <div className="min-h-0 flex-1 overflow-y-auto overflow-x-clip px-4 pb-4">
                        {children}
                    </div>
                )}
                {footer && (
                    <DrawerFooter>{footer}</DrawerFooter>
                )}
            </DrawerContent>
        </Drawer>
    );
}
