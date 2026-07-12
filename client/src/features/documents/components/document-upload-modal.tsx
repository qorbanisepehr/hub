import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { ResponsiveDialog } from "@/components/ui/responsive-dialog";
import { DocumentUpload } from "./document-upload";
import { BulkUpload } from "./bulk-upload";
import { ZipUpload } from "./zip-upload";

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
            <Tabs defaultValue="single">
                <TabsList variant="line" className="w-full">
                    <TabsTrigger value="single">تکی</TabsTrigger>
                    <TabsTrigger value="bulk">چندگانه</TabsTrigger>
                    <TabsTrigger value="zip">فایل فشرده</TabsTrigger>
                </TabsList>
                <TabsContent value="single">
                    <DocumentUpload
                        employeeId={employeeId}
                        onSuccess={() => onOpenChange(false)}
                    />
                </TabsContent>
                <TabsContent value="bulk">
                    <BulkUpload
                        employeeId={employeeId}
                        onSuccess={() => onOpenChange(false)}
                    />
                </TabsContent>
                <TabsContent value="zip">
                    <ZipUpload
                        employeeId={employeeId}
                        onSuccess={() => onOpenChange(false)}
                    />
                </TabsContent>
            </Tabs>
        </ResponsiveDialog>
    );
}
