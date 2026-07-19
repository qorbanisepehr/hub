import { useState } from "react";
import { IconAlertTriangle } from "@tabler/icons-react";
import { ResponsiveDialog } from "@/components/ui/responsive-dialog";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { DocumentUploadForm } from "./document-upload-form";

type DocumentUploadModalProps = {
    employeeId: number;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export function DocumentUploadModal({
    employeeId,
    open,
    onOpenChange,
}: DocumentUploadModalProps) {
    const [isDirty, setIsDirty] = useState(false);
    const [confirmClose, setConfirmClose] = useState(false);

    function handleOpenChange(nextOpen: boolean) {
        if (!nextOpen && isDirty) {
            setConfirmClose(true);
            return;
        }
        onOpenChange(false);
    }

    function handleConfirmClose() {
        setConfirmClose(false);
        onOpenChange(false);
    }

    return (
        <>
            <ResponsiveDialog
                open={open}
                onOpenChange={handleOpenChange}
                title="آپلود مدرک"
                description="فایل را انتخاب و اطلاعات را تکمیل کنید"
            >
                <DocumentUploadForm
                    employeeId={employeeId}
                    onSuccess={() => onOpenChange(false)}
                    onDirtyChange={setIsDirty}
                />
            </ResponsiveDialog>

            <Dialog open={confirmClose} onOpenChange={setConfirmClose}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <IconAlertTriangle className="size-5 text-orange-500" />
                            تغییرات ذخیره نشده
                        </DialogTitle>
                        <DialogDescription className="py-4 leading-6">
                            تغییراتی اعمال کرده‌اید که ذخیره نشده است. آیا مطمئن
                            هستید که می‌خواهید این پنجره را ببندید؟
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setConfirmClose(false)}>
                            بازگشت
                        </Button>
                        <Button variant="default" onClick={handleConfirmClose}>
                            بستن
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
