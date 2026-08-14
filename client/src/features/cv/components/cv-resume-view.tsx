import type { ReactNode } from "react";

import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { SectionRow } from "@/components/shared/section-row";
import {
    QuestionnaireDocumentPreview,
} from "@/components/shared/questionnaire-document-preview";
import { DocumentViewer } from "@/components/shared/document-viewer";
import type { EntityDocument } from "@/hooks/use-entity-documents";
import {
    CV_DOC_CATEGORY_SLUGS,
    CV_STATUS_BADGE_VARIANTS,
    CV_STATUS_LABELS,
} from "@/features/cv/constants";
import type { Cv, CvStatus } from "@/features/cv/types";
import { PersonalInfoView } from "@/components/shared/section-views/personal-info-view";
import { ContactInfoView } from "@/components/shared/section-views/contact-info-view";
import { EducationView } from "@/components/shared/section-views/education-view";
import { WorkExperienceView } from "@/components/shared/section-views/work-experience-view";
import { SkillsView } from "@/components/shared/section-views/skills-view";
import { TrainingView } from "@/components/shared/section-views/training-view";
import { AdditionalInfoView } from "@/components/shared/section-views/additional-info-view";

export function CvResumeView({
    cv,
    documents = [],
}: {
    cv: Cv;
    documents?: EntityDocument[];
}) {
    const personal: Record<string, unknown> = { ...(cv.personal_info ?? {}) };
    const contact: Record<string, unknown> = { ...(cv.contact_info ?? {}) };
    const additional: Record<string, unknown> = { ...(cv.additional_info ?? {}) };

    const docsBySlug = (slug: string) =>
        documents.filter((doc) => doc.category?.slug === slug);

    const academicDocs = docsBySlug(CV_DOC_CATEGORY_SLUGS.ACADEMIC_DEGREE);

    const academicExtra: ReactNode =
        academicDocs.length > 0 ? (
            <div className="mt-4 pt-4 border-t">
                <p className="mb-2 text-sm font-medium">رزومه بارگذاری شده</p>
                <QuestionnaireDocumentPreview
                    documents={academicDocs}
                    variant="compact"
                />
            </div>
        ) : undefined;

    const hasAnyDocument = documents.length > 0;

    return (
        <div className="space-y-6">
            <Card>
                <CardContent className="pt-6">
                    <div className="flex flex-wrap items-center justify-between gap-4">
                        <div className="space-y-2">
                            <h2 className="text-xl font-bold tracking-tight">
                                {cv.first_name} {cv.last_name}
                            </h2>
                            <div className="flex flex-wrap gap-x-6 gap-y-1">
                                <SectionRow
                                    variant="column"
                                    label="موبایل"
                                    value={
                                        <span dir="ltr" className="text-sm">
                                            {cv.mobile}
                                        </span>
                                    }
                                />
                                <SectionRow
                                    variant="column"
                                    label="ایمیل"
                                    value={
                                        <span dir="ltr" className="text-sm">
                                            {cv.email ?? "—"}
                                        </span>
                                    }
                                />
                            </div>
                        </div>
                        <div className="flex flex-col items-end gap-1">
                            <Badge
                                variant={
                                    CV_STATUS_BADGE_VARIANTS[
                                        cv.status as CvStatus
                                    ] ?? "secondary"
                                }
                            >
                                {CV_STATUS_LABELS[cv.status as CvStatus] ??
                                    cv.status}
                            </Badge>
                            <span className="text-xs text-muted-foreground">
                                نسخه {cv.version}
                            </span>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <PersonalInfoView
                data={{
                    ...personal,
                    first_name: cv.first_name,
                    last_name: cv.last_name,
                }}
                title="مشخصات فردی"
            />

            <ContactInfoView
                data={{
                    ...contact,
                    email: cv.email,
                    mobile: cv.mobile,
                }}
                title="اطلاعات تماس"
            />

            {cv.education && (
                <EducationView
                    data={{ ...cv.education }}
                    title="سوابق تحصیلی"
                    extra={academicExtra}
                />
            )}
            {cv.work_experience && (
                <WorkExperienceView
                    data={{ ...cv.work_experience }}
                    title="سوابق شغلی"
                />
            )}
            {cv.skills && (
                <SkillsView data={{ ...cv.skills }} title="مهارت‌ها" />
            )}
            {cv.training && (
                <TrainingView
                    data={{ ...cv.training }}
                    title="آموزشی و تحقیقاتی"
                />
            )}

            <AdditionalInfoView
                data={additional}
                title="اطلاعات تکمیلی"
            />

            {hasAnyDocument && (
                <Card>
                    <CardHeader>
                        <CardTitle>همه مدارک بارگذاری شده</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <DocumentViewer documents={documents} />
                    </CardContent>
                </Card>
            )}
        </div>
    );
}
