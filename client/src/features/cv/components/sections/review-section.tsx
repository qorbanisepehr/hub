import { useMemo } from "react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { SectionRow } from "@/components/shared/section-row";
import { FileThumbnail } from "@/components/ui/file-thumbnail";
import { useCvDocuments } from "@/features/cv/hooks/use-cv-documents";
import type { CvDocument } from "@/features/cv/hooks/use-cv-documents";
import type { Cv, CvFormApi } from "@/features/cv/types";
import {
    CV_DOC_CATEGORY_SLUGS,
    CV_VALIDATION_SECTIONS,
    CV_DOC_REQUIREMENTS,
    CV_WIZARD_STEPS,
} from "@/features/cv/constants";
import { buildValidateSubmitData } from "@/features/cv/validation";
import { useCvSubmitOptions } from "@/features/cv/hooks/use-cv-submit-options";
import { FormValidationSummary } from "@/components/shared/form-validation-summary";
import {
    groupFieldErrorsBySection,
    validateDocumentRequirements,
} from "@/lib/validation-helpers";
import { QuestionnaireDocumentPreview } from "@/components/shared/questionnaire-document-preview";
import { DocumentViewer } from "@/components/shared/document-viewer";
import { PersonalInfoView } from "@/components/shared/section-views/personal-info-view";
import { ContactInfoView } from "@/components/shared/section-views/contact-info-view";
import { EducationView } from "@/components/shared/section-views/education-view";
import { WorkExperienceView } from "@/components/shared/section-views/work-experience-view";
import { SkillsView } from "@/components/shared/section-views/skills-view";
import { TrainingView } from "@/components/shared/section-views/training-view";
import { AdditionalInfoView } from "@/components/shared/section-views/additional-info-view";
import {
    SectionEditButton,
} from "@/components/shared/section-views/section-card";

type SectionProps = {
    form: CvFormApi;
    cv?: Cv | null;
    onNavigateToStep?: (step: number) => void;
};

export function ReviewSection({ form, cv, onNavigateToStep }: SectionProps) {
    const v = form.state.values;
    const pi = v.personal_info ?? {};
    const ci = v.contact_info ?? {};
    const edu = v.education ?? {};
    const work = v.work_experience ?? {};
    const skills = v.skills ?? {};
    const training = v.training ?? {};
    const additional = v.additional_info ?? {};

    const {
        documents,
        isLoading: documentsLoading,
        getDocumentsBySlug,
    } = useCvDocuments(cv?.uuid);

    const { submitOptions } = useCvSubmitOptions();

    const validateSubmit = useMemo(
        () => buildValidateSubmitData(submitOptions),
        [submitOptions],
    );

    const validation = validateSubmit(form.state.values);
    const docMessages = documentsLoading
        ? []
        : validateDocumentRequirements(documents, CV_DOC_REQUIREMENTS);
    const validationGroups = groupFieldErrorsBySection(
        validation.fieldErrors,
        CV_VALIDATION_SECTIONS,
    );

    function docsFor(slugs: string[]): CvDocument[] {
        return slugs.flatMap((slug) => getDocumentsBySlug(slug));
    }

    const hasAnyDoc = documents.length > 0;

    const personnelPhoto = getDocumentsBySlug(
        CV_DOC_CATEGORY_SLUGS.PERSONNEL_PHOTO,
    )[0];

    const edit = (step: number) => () => onNavigateToStep?.(step);

    return (
        <div className="space-y-4">
            <FormValidationSummary
                groups={validationGroups}
                docMessages={docMessages}
                onNavigateToStep={onNavigateToStep}
                steps={CV_WIZARD_STEPS}
            />

            <Card>
                <CardHeader>
                    <CardTitle>خلاصه و تأیید اطلاعات</CardTitle>
                </CardHeader>
                <CardContent>
                    <p className="text-sm text-muted-foreground">
                        لطفاً اطلاعات وارد شده را بررسی کنید و در صورت صحت،
                        کدهای تأیید را دریافت و وارد کرده و رزومه را ارسال کنید.
                    </p>
                </CardContent>
            </Card>

            <PersonalInfoView
                data={{
                    ...pi,
                    first_name: v.first_name,
                    last_name: v.last_name,
                }}
                title="مشخصات فردی"
                action={<SectionEditButton onClick={edit(0)} />}
                topRight={
                    personnelPhoto && (
                        <div className="shrink-0">
                            <FileThumbnail
                                file={{
                                    name: personnelPhoto.structure_name,
                                    type: personnelPhoto.mime_type,
                                }}
                                previewImageUrl={personnelPhoto.url}
                                className="w-28 rounded-xl overflow-hidden"
                                previewAspectRatio={3 / 4}
                            />
                        </div>
                    )
                }
            />

            <ContactInfoView
                data={{
                    ...ci,
                    email: v.email,
                    mobile: v.mobile,
                }}
                title="اطلاعات تماس"
                action={<SectionEditButton onClick={edit(1)} />}
            />

            <EducationView
                data={edu}
                title="سوابق تحصیلی"
                action={<SectionEditButton onClick={edit(2)} />}
                extra={
                    <QuestionnaireDocumentPreview
                        documents={docsFor([
                            CV_DOC_CATEGORY_SLUGS.ACADEMIC_DEGREE,
                        ])}
                        variant="compact"
                        className="mt-4 pt-4 border-t"
                    />
                }
            />

            <WorkExperienceView
                data={work}
                title="سوابق شغلی"
                action={<SectionEditButton onClick={edit(3)} />}
            />

            <SkillsView
                data={skills}
                title="مهارت‌ها"
                action={<SectionEditButton onClick={edit(4)} />}
            />

            <TrainingView
                data={training}
                title="آموزشی و تحقیقاتی"
                action={<SectionEditButton onClick={edit(5)} />}
            />

            <AdditionalInfoView
                data={additional}
                title="اطلاعات تکمیلی"
                action={<SectionEditButton onClick={edit(6)} />}
            />

            {/* ── همه مدارک بارگذاری شده ── */}
            {hasAnyDoc && (
                <Card>
                    <CardHeader>
                        <CardTitle>همه مدارک بارگذاری شده</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <DocumentViewer documents={documents} />
                    </CardContent>
                </Card>
            )}

            {/* ── وضعیت تأیید ── */}
            <Card>
                <CardHeader>
                    <CardTitle>وضعیت تأیید</CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <SectionRow
                            variant="column"
                            label="ایمیل"
                            value={
                                cv?.email_verified ? (
                                    <span className="text-green-600 font-medium">
                                        تأیید شده
                                    </span>
                                ) : (
                                    <span className="text-muted-foreground">
                                        تأیید نشده
                                    </span>
                                )
                            }
                        />
                        <SectionRow
                            variant="column"
                            label="موبایل"
                            value={
                                cv?.mobile_verified ? (
                                    <span className="text-green-600 font-medium">
                                        تأیید شده
                                    </span>
                                ) : (
                                    <span className="text-muted-foreground">
                                        تأیید نشده
                                    </span>
                                )
                            }
                        />
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}
