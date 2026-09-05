import { useBlocker } from "@tanstack/react-router";
import { IconAlertTriangle } from "@tabler/icons-react";

import { Button } from "@/components/ui/button";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";

type UnsavedChangesDialogProps = {
    isDirty: boolean;
    isSubmitting?: boolean;
};

/**
 * Confirms leaving the form with unsaved edits.
 *
 * Only navigations that actually leave the form's route are blocked:
 * in-form step/tab switches (employee sections, wizard steps) change the
 * URL hash on the SAME route — the form stays mounted, its state and the
 * per-section auto-save keep running, so nothing is lost and blocking
 * there would fight the auto-save itself (the alert fired on every tab
 * change while a section save was in flight). While a save or submit is
 * pending the blocker stays disabled for the same reason.
 */
export function UnsavedChangesDialog({ isDirty, isSubmitting }: UnsavedChangesDialogProps) {
    const shouldBlock = isDirty && !isSubmitting;

    const blocker = useBlocker({
        shouldBlockFn: ({ current, next }) =>
            shouldBlock && current.pathname !== next.pathname,
        enableBeforeUnload: () => shouldBlock,
        withResolver: true,
    });

    return (
        <Dialog
            open={blocker.status === "blocked"}
            onOpenChange={(open) => {
                if (!open) blocker.reset?.();
            }}
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <IconAlertTriangle className="size-5 text-orange-500" />
                        تغییرات ذخیره نشده
                    </DialogTitle>
                    <DialogDescription className="py-4 leading-6">
                        تغییرات شما ذخیره نشده است. اگر خارج شوید، تغییرات
                        از دست می‌رود.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" onClick={() => blocker.reset?.()}>
                        بازگشت
                    </Button>
                    <Button
                        variant="default"
                        onClick={() => blocker.proceed?.()}
                    >
                        خروج بدون ذخیره
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
