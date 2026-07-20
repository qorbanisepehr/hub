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

export function UnsavedChangesDialog({ isDirty, isSubmitting }: UnsavedChangesDialogProps) {
    const shouldBlock = isDirty && !isSubmitting;

    const blocker = useBlocker({
        shouldBlockFn: () => shouldBlock,
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
                        تغییراتی اعمال کرده‌اید که ذخیره نشده است. آیا مطمئن
                        هستید که می‌خواهید این صفحه را ترک کنید؟
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
                        ترک صفحه
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
