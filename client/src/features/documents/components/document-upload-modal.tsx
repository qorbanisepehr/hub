import { useState } from "react";
import { IconAlertTriangle } from "@tabler/icons-react";
import { ResponsiveDialog } from "@/components/ui/responsive-dialog";
import { Button } from "@/components/ui/button";
import { DocumentUploadForm } from "./document-upload-form";

type DocumentUploadModalProps = {
    documentableType: string;
    documentableId: number;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export function DocumentUploadModal({
    documentableType,
    documentableId,
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
                    documentableType={documentableType}
                    documentableId={documentableId}
                    onSuccess={() => onOpenChange(false)}
                    onDirtyChange={setIsDirty}
                />
            </ResponsiveDialog>

            <ResponsiveDialog
                open={confirmClose}
                onOpenChange={setConfirmClose}
                title={
                    <span className="flex items-center gap-2">
                        <IconAlertTriangle className="size-5 text-orange-500" />
                        تغییرات ذخیره نشده
                    </span>
                }
                description="تغییراتی اعمال کرده‌اید که ذخیره نشده است. آیا مطمئن هستید که می‌خواهید این پنجره را ببندید؟"
                footer={
                    <>
                        <Button
                            variant="outline"
                            onClick={() => setConfirmClose(false)}
                        >
                            بازگشت
                        </Button>
                        <Button variant="default" onClick={handleConfirmClose}>
                            بستن
                        </Button>
                    </>
                }
            />
        </>
    );
}
