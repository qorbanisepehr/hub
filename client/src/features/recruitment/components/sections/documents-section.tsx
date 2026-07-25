import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { FileUploadField } from "@/components/shared/file-upload-field";

type SectionProps = {
    uuid?: string;
};

const DOCUMENT_CATEGORIES = [
    { id: 7, label: "کارت ملی — رو", accept: "image/jpeg,image/png,image/webp,.pdf" },
    { id: 8, label: "کارت ملی — پشت", accept: "image/jpeg,image/png,image/webp,.pdf" },
    { id: 45, label: "رزومه", accept: ".pdf,image/jpeg,image/png,image/webp", multiple: true, maxFiles: 5 },
    { id: 46, label: "نامه پوششی", accept: ".pdf,image/jpeg,image/png,image/webp" },
    { id: 48, label: "سایر مدارک", accept: ".pdf,image/jpeg,image/png,image/webp", multiple: true, maxFiles: 10 },
];

export function DocumentsSection({ uuid }: SectionProps) {
    if (!uuid) return null;

    return (
        <Card>
            <CardHeader>
                <CardTitle>بارگذاری مدارک</CardTitle>
            </CardHeader>
            <CardContent className="space-y-6">
                <p className="text-sm text-muted-foreground">
                    مدارک مورد نیاز را بارگذاری کنید. فرمت‌های مجاز: PDF، JPEG، PNG، WebP.
                </p>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {DOCUMENT_CATEGORIES.map((cat) => (
                        <FileUploadField
                            key={cat.id}
                            uuid={uuid}
                            categoryId={cat.id}
                            label={cat.label}
                            accept={cat.accept}
                            multiple={cat.multiple}
                            maxFiles={cat.maxFiles}
                        />
                    ))}
                </div>
            </CardContent>
        </Card>
    );
}
