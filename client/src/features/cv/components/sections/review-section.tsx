import { useMemo } from "react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
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
import { toPersianDate } from "@/lib/date-format";
import { GENDER_MALE } from "@/features/questionnaire/schemas/personal-info.schema";
import { QuestionnaireDocumentPreview } from "@/components/shared/questionnaire-document-preview";
import { DocumentViewer } from "@/components/shared/document-viewer";

type SectionProps = {
    form: CvFormApi;
    cv?: Cv | null;
    onNavigateToStep?: (step: number) => void;
};

function DataRow({ label, value }: { label: string; value: React.ReactNode }) {
    return (
        <div className="flex flex-col gap-0.5">
            <span className="text-xs text-muted-foreground">{label}</span>
            <span className="text-sm">
                {value || <span className="text-muted-foreground">—</span>}
            </span>
        </div>
    );
}

function YesNo({ value }: { value: boolean | undefined }) {
    return <span>{value ? "بلی" : "خیر"}</span>;
}

function SectionHeader({
    title,
    onEdit,
}: {
    title: string;
    onEdit?: () => void;
}) {
    return (
        <CardHeader className="flex flex-row items-center justify-between">
            <CardTitle>{title}</CardTitle>
            {onEdit && (
                <button
                    type="button"
                    className="text-sm text-primary hover:underline"
                    onClick={onEdit}
                >
                    ویرایش
                </button>
            )}
        </CardHeader>
    );
}

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

            {/* ── مشخصات فردی ── */}
            <Card>
                <SectionHeader
                    title="مشخصات فردی"
                    onEdit={() => onNavigateToStep?.(0)}
                />
                <CardContent>
                    <div className="flex flex-col gap-4 md:flex-row">
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4 flex-1">
                            <DataRow label="نام" value={v.first_name} />
                            <DataRow label="نام خانوادگی" value={v.last_name} />
                            <DataRow label="جنسیت" value={pi.gender} />
                            <DataRow
                                label="تاریخ تولد"
                                value={toPersianDate(pi.birth_date)}
                            />
                            <DataRow label="محل تولد" value={pi.birth_place} />
                            <DataRow
                                label="شماره شناسنامه"
                                value={pi.birth_certificate_number}
                            />
                            <DataRow label="کد ملی" value={pi.id_number} />
                            <DataRow
                                label="وضعیت تأهل"
                                value={pi.marital_status}
                            />

                            {pi.military_status &&
                                pi.gender === GENDER_MALE && (
                                    <DataRow
                                        label="وضعیت نظام وظیفه"
                                        value={pi.military_status.status}
                                    />
                                )}
                        </div>

                        {personnelPhoto && (
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
                        )}
                    </div>
                </CardContent>
            </Card>

            {/* ── اطلاعات تماس ── */}
            <Card>
                <SectionHeader
                    title="اطلاعات تماس"
                    onEdit={() => onNavigateToStep?.(1)}
                />
                <CardContent>
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <DataRow label="ایمیل" value={v.email} />
                        <DataRow label="موبایل" value={v.mobile} />
                        <DataRow label="تلفن ثابت" value={ci.phone} />
                        {ci.address && (
                            <>
                                <DataRow
                                    label="استان"
                                    value={ci.address.province}
                                />
                                <DataRow label="شهر" value={ci.address.city} />
                                <DataRow
                                    label="محله"
                                    value={ci.address.neighborhood}
                                />
                            </>
                        )}
                    </div>
                </CardContent>
            </Card>

            {/* ── سوابق تحصیلی ── */}
            <Card>
                <SectionHeader
                    title="سوابق تحصیلی"
                    onEdit={() => onNavigateToStep?.(2)}
                />
                <CardContent className="space-y-4">
                    {edu.education_records?.length > 0 ? (
                        edu.education_records.map((rec: any, i: number) => (
                            <div
                                key={i}
                                className="grid grid-cols-1 md:grid-cols-3 gap-4 p-3 rounded-lg bg-muted/50"
                            >
                                <DataRow label="مدرک" value={rec.degree} />
                                <DataRow label="رشته" value={rec.field} />
                                <DataRow
                                    label="دانشگاه"
                                    value={rec.institution}
                                />
                                <DataRow label="محل" value={rec.location} />
                                <DataRow
                                    label="از تاریخ"
                                    value={toPersianDate(rec.from)}
                                />
                                <DataRow
                                    label="تا تاریخ"
                                    value={toPersianDate(rec.to)}
                                />
                                <DataRow label="معدل" value={rec.gpa} />
                                <DataRow
                                    label="تاریخ فارغ‌التحصیلی"
                                    value={toPersianDate(rec.graduation_date)}
                                />
                                <DataRow
                                    label="پایان‌نامه"
                                    value={rec.thesis_title}
                                />
                            </div>
                        ))
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            سابقه تحصیلی ثبت نشده
                        </p>
                    )}
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4 pt-2 border-t">
                        <DataRow
                            label="دانشجو هستم"
                            value={<YesNo value={edu.is_student} />}
                        />
                        {edu.is_student && (
                            <>
                                <DataRow
                                    label="مقطع"
                                    value={edu.student_degree}
                                />
                                <DataRow
                                    label="رشته"
                                    value={edu.student_field}
                                />
                                <DataRow
                                    label="دانشگاه"
                                    value={edu.student_university}
                                />
                                <DataRow label="معدل" value={edu.student_gpa} />
                            </>
                        )}
                    </div>
                    <QuestionnaireDocumentPreview
                        documents={docsFor([
                            CV_DOC_CATEGORY_SLUGS.ACADEMIC_DEGREE,
                        ])}
                        variant="compact"
                        className="pt-2"
                    />
                </CardContent>
            </Card>

            {/* ── سوابق شغلی ── */}
            <Card>
                <SectionHeader
                    title="سوابق شغلی"
                    onEdit={() => onNavigateToStep?.(3)}
                />
                <CardContent className="space-y-4">
                    {work.work_experiences?.length > 0 ? (
                        work.work_experiences.map((exp: any, i: number) => (
                            <div
                                key={i}
                                className="grid grid-cols-1 md:grid-cols-3 gap-4 p-3 rounded-lg bg-muted/50"
                            >
                                <DataRow label="شرکت" value={exp.company} />
                                <DataRow label="سمت" value={exp.position} />
                                <DataRow label="صنعت" value={exp.industry} />
                                <DataRow label="محل کار" value={exp.location} />
                                <DataRow
                                    label="از تاریخ"
                                    value={toPersianDate(exp.from)}
                                />
                                <DataRow
                                    label="تا تاریخ"
                                    value={toPersianDate(exp.to)}
                                />
                                <DataRow
                                    label="نوع قرارداد"
                                    value={exp.contract_type}
                                />
                                <DataRow
                                    label="آخرین حقوق"
                                    value={exp.last_salary}
                                />
                                <DataRow
                                    label="دلیل ترک"
                                    value={exp.leave_reason}
                                />
                            </div>
                        ))
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            سابقه شغلی ثبت نشده
                        </p>
                    )}
                    <div className="pt-2 border-t">
                        <DataRow label="دستاوردها" value={work.achievements} />
                        <DataRow
                            label="اجازه تماس با مدیران قبلی"
                            value={
                                <YesNo
                                    value={work.allow_contact_previous_managers}
                                />
                            }
                        />
                    </div>
                </CardContent>
            </Card>

            {/* ── مهارت‌ها ── */}
            <Card>
                <SectionHeader
                    title="مهارت‌ها"
                    onEdit={() => onNavigateToStep?.(4)}
                />
                <CardContent className="space-y-4">
                    {skills.languages?.length > 0 && (
                        <div>
                            <p className="text-sm font-medium mb-2">زبان‌ها</p>
                            <div className="space-y-2">
                                {skills.languages.map(
                                    (lang: any, i: number) => (
                                        <div
                                            key={i}
                                            className="text-sm p-2 rounded bg-muted/50"
                                        >
                                            {lang.language}: خواندن{" "}
                                            {lang.reading}، نوشتن {lang.writing}
                                            ، صحبت {lang.speaking}، درک مطلب{" "}
                                            {lang.comprehension}
                                        </div>
                                    ),
                                )}
                            </div>
                        </div>
                    )}
                    {skills.software_skills?.specialized?.length > 0 && (
                        <div>
                            <p className="text-sm font-medium mb-2">
                                نرم‌افزارهای تخصصی
                            </p>
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-2">
                                {skills.software_skills.specialized.map(
                                    (s: any, i: number) => (
                                        <div
                                            key={i}
                                            className="text-sm p-2 rounded bg-muted/50"
                                        >
                                            {s.name} — سطح {s.level}
                                        </div>
                                    ),
                                )}
                            </div>
                        </div>
                    )}
                    {skills.software_skills?.general?.length > 0 && (
                        <div>
                            <p className="text-sm font-medium mb-2">
                                نرم‌افزارهای عمومی
                            </p>
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-2">
                                {skills.software_skills.general.map(
                                    (s: any, i: number) => (
                                        <div
                                            key={i}
                                            className="text-sm p-2 rounded bg-muted/50"
                                        >
                                            {s.name} — سطح {s.level}
                                        </div>
                                    ),
                                )}
                            </div>
                        </div>
                    )}
                    {skills.special_skills?.length > 0 && (
                        <div>
                            <p className="text-sm font-medium mb-2">
                                مهارت‌های خاص
                            </p>
                            <div className="flex flex-wrap gap-2">
                                {skills.special_skills.map(
                                    (s: string, i: number) => (
                                        <span
                                            key={i}
                                            className="text-sm px-2 py-1 rounded bg-muted/50"
                                        >
                                            {s}
                                        </span>
                                    ),
                                )}
                            </div>
                        </div>
                    )}
                </CardContent>
            </Card>

            {/* ── آموزشی و تحقیقاتی ── */}
            <Card>
                <SectionHeader
                    title="آموزشی و تحقیقاتی"
                    onEdit={() => onNavigateToStep?.(5)}
                />
                <CardContent className="space-y-4">
                    {training.training_courses?.length > 0 ? (
                        <div className="space-y-2">
                            {training.training_courses.map(
                                (c: any, i: number) => (
                                    <div
                                        key={i}
                                        className="text-sm p-2 rounded bg-muted/50"
                                    >
                                        {c.course_name} — {c.institution} (
                                        {c.duration})
                                    </div>
                                ),
                            )}
                        </div>
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            دوره آموزشی ثبت نشده
                        </p>
                    )}
                    <DataRow
                        label="عضویت‌های حرفه‌ای"
                        value={training.professional_memberships}
                    />
                    {training.researches?.length > 0 && (
                        <div>
                            <p className="text-sm font-medium mb-1">
                                تحقیقات و پژوهش‌ها
                            </p>
                            {training.researches.map((r: any, i: number) => (
                                <p key={i} className="text-sm">
                                    {i + 1}. {r.title}
                                </p>
                            ))}
                        </div>
                    )}
                </CardContent>
            </Card>

            {/* ── اطلاعات تکمیلی ── */}
            <Card>
                <SectionHeader
                    title="اطلاعات تکمیلی"
                    onEdit={() => onNavigateToStep?.(6)}
                />
                <CardContent>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <DataRow
                            label="علاقه‌مندی‌ها"
                            value={additional.hobbies}
                        />
                        <DataRow
                            label="نقاط قوت و زمینه‌های قابل بهبود"
                            value={additional.strengths_and_improvements}
                        />
                        <DataRow
                            label="وضعیت جسمانی"
                            value={additional.physical_condition}
                        />
                        <DataRow
                            label="نوع معلولیت"
                            value={additional.disability_type}
                        />
                    </div>
                    {additional.references?.length > 0 && (
                        <div className="mt-4 pt-4 border-t">
                            <p className="text-sm font-medium mb-2">ارجاعات</p>
                            <div className="space-y-2">
                                {additional.references.map(
                                    (ref: any, i: number) => (
                                        <div
                                            key={i}
                                            className="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm p-2 rounded bg-muted/50"
                                        >
                                            <DataRow
                                                label="نام"
                                                value={ref.full_name}
                                            />
                                            <DataRow
                                                label="رابطه"
                                                value={ref.relationship}
                                            />
                                            <DataRow
                                                label="تلفن"
                                                value={ref.workplace_phone}
                                            />
                                        </div>
                                    ),
                                )}
                            </div>
                        </div>
                    )}
                </CardContent>
            </Card>

            {/* ── همه مدارک بارگذاری شده ── */}
            {hasAnyDoc && (
                <Card>
                    <SectionHeader title="همه مدارک بارگذاری شده" />
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
                        <DataRow
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
                        <DataRow
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
