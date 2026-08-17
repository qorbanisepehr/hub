import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { FileUploadField } from "@/components/documents";
import { CV_DOC_CATEGORY_SLUGS } from "@/features/cv/constants";

type SectionProps = {
    uuid?: string;
};

export function DocumentsSection({ uuid }: SectionProps) {
    if (!uuid) return null;

    return (
        <Card>
            <CardHeader>
                <CardTitle>بارگذاری مدارک</CardTitle>
            </CardHeader>
            <CardContent className="space-y-6">
                <p className="text-sm text-muted-foreground">
                    رزومه الزامی است؛ نامه معرفی و سایر مدارک اختیاری‌اند.
                    فرمت‌های مجاز: PDF، JPEG، PNG، WebP.
                </p>

                <FileUploadField
                    uuid={uuid}
                    entity="cv"
                    categorySlug={CV_DOC_CATEGORY_SLUGS.RESUME}
                    label="رزومه (الزامی)"
                    accept=".pdf,image/jpeg,image/png,image/webp"
                    maxFiles={1}
                />

                <FileUploadField
                    uuid={uuid}
                    entity="cv"
                    categorySlug={CV_DOC_CATEGORY_SLUGS.COVER_LETTER}
                    label="نامه معرفی"
                    accept=".pdf,image/jpeg,image/png,image/webp"
                    maxFiles={1}
                />

                <FileUploadField
                    uuid={uuid}
                    entity="cv"
                    categorySlug={CV_DOC_CATEGORY_SLUGS.PERSONNEL_PHOTO}
                    label="عکس پرسنلی (اختیاری)"
                    accept="image/jpeg,image/png,image/webp"
                    maxFiles={1}
                />

                <FileUploadField
                    uuid={uuid}
                    entity="cv"
                    categorySlug={CV_DOC_CATEGORY_SLUGS.OTHER_DOCUMENTS}
                    label="سایر مدارک"
                    accept=".pdf,image/jpeg,image/png,image/webp"
                    multiple
                    maxFiles={3}
                />
            </CardContent>
        </Card>
    );
}
