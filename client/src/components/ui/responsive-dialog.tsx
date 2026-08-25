import * as React from "react";

import { Dialog, DialogContent } from "@/components/ui/dialog";
import {
    Drawer,
    DrawerContent,
    DrawerDescription,
    DrawerFooter,
    DrawerHeader,
    DrawerTitle,
} from "@/components/ui/drawer";
import { useMediaQuery } from "@/hooks/use-media-query";

type ResponsiveDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title?: React.ReactNode;
    description?: React.ReactNode;
    /** Header-level actions (menus, buttons) aligned with the title row. */
    actions?: React.ReactNode;
    children: React.ReactNode;
    footer?: React.ReactNode;
};

export function ResponsiveDialog({
    open,
    onOpenChange,
    title,
    description,
    actions,
    children,
    footer,
}: ResponsiveDialogProps) {
    const isDesktop = useMediaQuery("(min-width: 768px)");

    if (isDesktop) {
        return (
            <Dialog open={open} onOpenChange={onOpenChange}>
                <DialogContent
                    showCloseButton
                    className="flex max-h-[85dvh] flex-col gap-0 overflow-clip p-0 sm:max-w-lg"
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
                    <div className="min-h-0 flex-1 overflow-y-auto overflow-x-clip p-4">
                        {children}
                    </div>
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
                <div className="min-h-0 flex-1 overflow-y-auto overflow-x-clip px-4 pb-4">
                    {children}
                </div>
                {footer && (
                    <DrawerFooter>{footer}</DrawerFooter>
                )}
            </DrawerContent>
        </Drawer>
    );
}
