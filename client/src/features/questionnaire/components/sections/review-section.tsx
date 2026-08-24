import { useMemo } from "react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { SectionRow } from "@/components/shared/section-row";
import { useQuestionnaireDocuments } from "@/features/questionnaire/hooks/use-questionnaire-documents";
import type { QuestionnaireDocument } from "@/features/questionnaire/hooks/use-questionnaire-documents";
import type { Questionnaire, QuestionnaireFormApi } from "@/features/questionnaire/types";
import {
    DOC_CATEGORY_SLUGS,
    QUESTIONNAIRE_VALIDATION_SECTIONS,
    QUESTIONNAIRE_DOC_REQUIREMENTS,
    WIZARD_STEPS,
} from "@/features/questionnaire/constants";
import { buildValidateSubmitData } from "@/features/questionnaire/validation";
import { useQuestionnaireSubmitOptions } from "@/features/questionnaire/hooks/use-questionnaire-submit-options";
import { FormValidationSummary } from "@/components/forms";
import {
    groupFieldErrorsBySection,
    validateDocumentRequirements,
} from "@/lib/validation-helpers";
import { toPersianDate } from "@/lib/date-format";
import { QuestionnaireDocumentPreview } from "@/components/documents";
import { DocumentViewer } from "@/components/documents";
import { PersonalInfoView } from "@/components/section-views/personal-info-view";
import { ContactInfoView } from "@/components/section-views/contact-info-view";
import { EducationView } from "@/components/section-views/education-view";
import { WorkExperienceView } from "@/components/section-views/work-experience-view";
import { SkillsView } from "@/components/section-views/skills-view";
import { TrainingView } from "@/components/section-views/training-view";
import { AdditionalInfoView } from "@/components/section-views/additional-info-view";
import {
    SectionEditButton,
} from "@/components/section-views/section-card";
import { useOptionLabel, useOptionLabels } from "@/components/section-views/use-option-label";

type SectionProps = {
    form: QuestionnaireFormApi;
    questionnaire?: Questionnaire | null;
    onNavigateToStep?: (step: number) => void;
};

function YesNo({ value }: { value: boolean | undefined }) {
    return <span>{value ? "بله" : "خیر"}</span>;
}

const SECTION_DOCS: { step: number; label: string; slugs: string[] }[] = [
    { step: 0, label: "مدارک هویتی", slugs: [DOC_CATEGORY_SLUGS.PERSONNEL_PHOTO, DOC_CATEGORY_SLUGS.NATIONAL_CARD, DOC_CATEGORY_SLUGS.BIRTH_CERTIFICATE] },
    { step: 2, label: "مدارک تحصیلی", slugs: [DOC_CATEGORY_SLUGS.ACADEMIC_DEGREE] },
    { step: 3, label: "مدارک سوابق شغلی", slugs: [DOC_CATEGORY_SLUGS.EMPLOYMENT_CERTIFICATE] },
    { step: 4, label: "مدارک مهارتی", slugs: [DOC_CATEGORY_SLUGS.LANGUAGE_CERTIFICATE, DOC_CATEGORY_SLUGS.COURSE_CERTIFICATES, DOC_CATEGORY_SLUGS.SKILL_CERTIFICATE] },
    { step: 5, label: "مدارک آموزشی و پژوهشی", slugs: [DOC_CATEGORY_SLUGS.COURSE_CERTIFICATES, DOC_CATEGORY_SLUGS.RESEARCH_DOCUMENTS] },
    { step: 7, label: "مدارک شغلی", slugs: [DOC_CATEGORY_SLUGS.RESUME, DOC_CATEGORY_SLUGS.COVER_LETTER] },
];

export function ReviewSection({ form, questionnaire, onNavigateToStep }: SectionProps) {
    const v = form.state.values;
    const pi = v.personal_info ?? {};
    const ci = v.contact_info ?? {};
    const edu = v.education ?? {};
    const work = v.work_experience ?? {};
    const skills = v.skills ?? {};
    const training = v.training ?? {};
    const additional = v.additional_info ?? {};
    const job = v.job_request ?? {};

    const employmentTypeLabel = useOptionLabel("employment_type", job.employment_type as string);
    const preferredWorkplaceLabels = useOptionLabels(
        "preferred_workplace",
        job.preferred_workplace as string[] | undefined,
    );

    const { documents, isLoading: documentsLoading, getDocumentsBySlug } =
        useQuestionnaireDocuments(questionnaire?.uuid);

    const { submitOptions } = useQuestionnaireSubmitOptions();

    const validateSubmit = useMemo(
        () => buildValidateSubmitData(submitOptions),
        [submitOptions],
    );

    const validation = validateSubmit(form.state.values);
    const docMessages = documentsLoading
        ? []
        : validateDocumentRequirements(documents, QUESTIONNAIRE_DOC_REQUIREMENTS);
    const validationGroups = groupFieldErrorsBySection(
        validation.fieldErrors,
        QUESTIONNAIRE_VALIDATION_SECTIONS,
    );

    function docsFor(slugs: string[]): QuestionnaireDocument[] {
        return slugs.flatMap((slug) => getDocumentsBySlug(slug));
    }

    function docsForStep(step: number): QuestionnaireDocument[] {
        return docsFor(SECTION_DOCS.find((s) => s.step === step)?.slugs ?? []);
    }

    const hasAnyDoc = documents.length > 0;

    const edit = (step: number) => () => onNavigateToStep?.(step);

    return (
        <div className="space-y-4">
            <FormValidationSummary
                groups={validationGroups}
                docMessages={docMessages}
                onNavigateToStep={onNavigateToStep}
                steps={WIZARD_STEPS}
            />

            <Card>
                <CardHeader>
                    <CardTitle>خلاصه و تأیید اطلاعات</CardTitle>
                </CardHeader>
                <CardContent>
                    <p className="text-sm text-muted-foreground">
                        لطفاً اطلاعات وارد شده را بررسی کنید و در صورت صحت، کدهای تأیید را دریافت و وارد کرده و فرم را ارسال کنید.
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
                extra={
                    <QuestionnaireDocumentPreview
                        documents={docsForStep(0)}
                        variant="compact"
                        className="mt-4 pt-4 border-t"
                    />
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
                        documents={docsForStep(2)}
                        variant="compact"
                        className="mt-4 pt-4 border-t"
                    />
                }
            />

            <WorkExperienceView
                data={work}
                title="سوابق شغلی"
                action={<SectionEditButton onClick={edit(3)} />}
                extra={
                    <QuestionnaireDocumentPreview
                        documents={docsForStep(3)}
                        variant="compact"
                        className="mt-4 pt-4 border-t"
                    />
                }
            />

            <SkillsView
                data={skills}
                title="مهارت‌ها"
                action={<SectionEditButton onClick={edit(4)} />}
                extra={
                    <QuestionnaireDocumentPreview
                        documents={docsForStep(4)}
                        variant="compact"
                        className="mt-4 pt-4 border-t"
                    />
                }
            />

            <TrainingView
                data={training}
                title="آموزشی و تحقیقاتی"
                action={<SectionEditButton onClick={edit(5)} />}
                extra={
                    <QuestionnaireDocumentPreview
                        documents={docsForStep(5)}
                        variant="compact"
                        className="mt-4 pt-4 border-t"
                    />
                }
            />

            <AdditionalInfoView
                data={additional}
                title="اطلاعات تکمیلی"
                action={<SectionEditButton onClick={edit(6)} />}
            />

            {/* ── نوع درخواست همکاری ── */}
            <Card>
                <CardHeader className="flex flex-row items-center justify-between">
                    <CardTitle>نوع درخواست همکاری</CardTitle>
                    <SectionEditButton onClick={edit(7)} />
                </CardHeader>
                <CardContent>
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <SectionRow variant="column" label="نوع اشتغال" value={employmentTypeLabel} />
                        <SectionRow variant="column" label="حقوق ماهانه مورد انتظار" value={job.expected_monthly_salary} />
                        <SectionRow variant="column" label="حقوق ساعتی مورد انتظار" value={job.expected_hourly_salary} />
                        <SectionRow variant="column" label="حداقل ساعات کاری در ماه" value={job.minimum_hours_per_month} />
                        <SectionRow variant="column" label="ارسال رزومه قبلی" value={<YesNo value={job.submitted_resume_before} />} />
                        <SectionRow variant="column" label="مصاحبه قبلی" value={<YesNo value={job.interviewed_before} />} />
                        <SectionRow variant="column" label="شاغل در حال حاضر" value={<YesNo value={job.currently_employed} />} />
                        <SectionRow variant="column" label="تاریخ شروع به کار" value={toPersianDate(job.available_start_date)} />
                        <SectionRow variant="column" label="محل کار مورد نظر" value={preferredWorkplaceLabels} />
                        <SectionRow variant="column" label="اولویت شغلی ۱" value={job.job_priority_1} />
                        <SectionRow variant="column" label="اولویت شغلی ۲" value={job.job_priority_2} />
                    </div>
                    <div className="mt-4 pt-4 border-t">
                        <SectionRow variant="column" label="سایر اطلاعات" value={job.other_information} />
                    </div>
                    <QuestionnaireDocumentPreview
                        documents={docsForStep(7)}
                        variant="compact"
                        className="mt-4 pt-4 border-t"
                    />
                </CardContent>
            </Card>

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
                                questionnaire?.email_verified ? (
                                    <span className="text-green-600 font-medium">تأیید شده</span>
                                ) : (
                                    <span className="text-muted-foreground">تأیید نشده</span>
                                )
                            }
                        />
                        <SectionRow
                            variant="column"
                            label="موبایل"
                            value={
                                questionnaire?.mobile_verified ? (
                                    <span className="text-green-600 font-medium">تأیید شده</span>
                                ) : (
                                    <span className="text-muted-foreground">تأیید نشده</span>
                                )
                            }
                        />
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}
