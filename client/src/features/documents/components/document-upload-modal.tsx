import { ResponsiveDialog } from "@/components/ui/responsive-dialog";
import { DocumentUpload } from "./document-upload";

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
    return (
        <ResponsiveDialog
            open={open}
            onOpenChange={onOpenChange}
            title="آپلود مدرک"
            description="فایل را انتخاب و اطلاعات را تکمیل کنید"
        >
            <DocumentUpload
                employeeId={employeeId}
                onSuccess={() => onOpenChange(false)}
            />
        </ResponsiveDialog>
    );
}
