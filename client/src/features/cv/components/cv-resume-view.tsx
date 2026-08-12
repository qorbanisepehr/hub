import type { ReactNode } from "react";

import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
    QuestionnaireDocumentGrouped,
    QuestionnaireDocumentPreview,
} from "@/components/shared/questionnaire-document-preview";
import { toPersianDate } from "@/lib/date-format";
import type {
    Education,
    Skills,
    Training,
    WorkExperience,
} from "@/features/questionnaire/types";
import type { EntityDocument } from "@/hooks/use-entity-documents";
import {
    CV_DOC_CATEGORY_SLUGS,
    CV_STATUS_BADGE_VARIANTS,
    CV_STATUS_LABELS,
} from "@/features/cv/constants";
import type {
    Cv,
    CvAdditionalInfo,
    CvContactInfo,
    CvPersonalInfo,
    CvStatus,
} from "@/features/cv/types";

function DataRow({ label, value }: { label: string; value: ReactNode }) {
    return (
        <div className="flex flex-col gap-0.5">
            <span className="text-xs text-muted-foreground">{label}</span>
            <span className="text-sm">
                {value || <span className="text-muted-foreground">—</span>}
            </span>
        </div>
    );
}

function SectionCard({
    title,
    children,
}: {
    title: string;
    children: ReactNode;
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>{title}</CardTitle>
            </CardHeader>
            <CardContent>{children}</CardContent>
        </Card>
    );
}

function EmptySection() {
    return <p className="text-sm text-muted-foreground">ثبت نشده است</p>;
}

function EducationSection({
    education,
    documents = [],
}: {
    education: Education;
    documents?: EntityDocument[];
}) {
    return (
        <SectionCard title="سوابق تحصیلی">
            {education.education_records?.length ? (
                <div className="space-y-4">
                    {education.education_records.map((record, index) => (
                        <div
                            key={index}
                            className="space-y-1 rounded-lg border bg-muted/30 p-3"
                        >
                            <p className="text-sm font-medium">
                                {record.degree}
                                {record.field ? ` - ${record.field}` : ""}
                            </p>
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                                <DataRow
                                    label="مؤسسه"
                                    value={record.institution}
                                />
                                <DataRow label="شهر" value={record.location} />
                                <DataRow label="معدل" value={record.gpa} />
                                <DataRow
                                    label="از"
                                    value={toPersianDate(record.from)}
                                />
                                <DataRow
                                    label="تا"
                                    value={toPersianDate(record.to)}
                                />
                                <DataRow
                                    label="تاریخ فارغ‌التحصیلی"
                                    value={toPersianDate(
                                        record.graduation_date,
                                    )}
                                />
                                <DataRow
                                    label="عنوان پایان‌نامه"
                                    value={record.thesis_title}
                                />
                            </div>
                        </div>
                    ))}
                </div>
            ) : (
                <EmptySection />
            )}

            {education.is_student && (
                <div className="mt-4 grid grid-cols-1 gap-4 border-t pt-4 md:grid-cols-3">
                    <DataRow label="دانشجو" value="بله" />
                    <DataRow label="مقطع" value={education.student_degree} />
                    <DataRow label="رشته" value={education.student_field} />
                    <DataRow
                        label="دانشگاه"
                        value={education.student_university}
                    />
                    <DataRow label="کشور" value={education.student_country} />
                    <DataRow label="شهر" value={education.student_city} />
                    <DataRow label="ترم" value={education.student_semester} />
                    <DataRow
                        label="واحد گذرانده"
                        value={education.passed_units}
                    />
                    <DataRow label="معدل" value={education.student_gpa} />
                    <DataRow
                        label="شروع تحصیل"
                        value={toPersianDate(education.study_start)}
                    />
                    <DataRow
                        label="فارغ‌التحصیلی مورد انتظار"
                        value={toPersianDate(education.expected_graduation)}
                    />
                </div>
            )}

            {documents.length > 0 && (
                <div className="mt-4 border-t pt-4">
                    <p className="mb-2 text-sm font-medium">
                        رزومه بارگذاری شده
                    </p>
                    <QuestionnaireDocumentPreview
                        documents={documents}
                        variant="compact"
                    />
                </div>
            )}
        </SectionCard>
    );
}

function WorkExperienceSection({ work }: { work: WorkExperience }) {
    return (
        <SectionCard title="سوابق شغلی">
            {work.work_experiences?.length ? (
                <div className="space-y-4">
                    {work.work_experiences.map((record, index) => (
                        <div
                            key={index}
                            className="space-y-1 rounded-lg border bg-muted/30 p-3"
                        >
                            <p className="text-sm font-medium">
                                {[record.position, record.company]
                                    .filter(Boolean)
                                    .join(" - ")}
                            </p>
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                                <DataRow label="صنعت" value={record.industry} />
                                <DataRow label="شهر" value={record.location} />
                                <DataRow
                                    label="از"
                                    value={toPersianDate(record.from)}
                                />
                                <DataRow
                                    label="تا"
                                    value={toPersianDate(record.to)}
                                />
                                <DataRow
                                    label="نوع قرارداد"
                                    value={record.contract_type}
                                />
                                <DataRow
                                    label="مدیر"
                                    value={record.manager_name}
                                />
                                <DataRow label="تلفن" value={record.phone} />
                                <DataRow
                                    label="دلیل ترک"
                                    value={record.leave_reason}
                                />
                            </div>
                        </div>
                    ))}
                </div>
            ) : (
                <EmptySection />
            )}

            {work.achievements && (
                <div className="mt-4 border-t pt-4">
                    <DataRow label="دستاوردها" value={work.achievements} />
                </div>
            )}
        </SectionCard>
    );
}

function SkillChip({ children }: { children: ReactNode }) {
    return (
        <span className="rounded-md bg-muted px-2 py-1 text-sm">
            {children}
        </span>
    );
}

function SkillsSection({ skills }: { skills: Skills }) {
    const specialized = skills.software_skills?.specialized ?? [];
    const general = skills.software_skills?.general ?? [];

    return (
        <SectionCard title="مهارت‌ها">
            {skills.languages?.length ? (
                <div className="mb-4">
                    <p className="mb-2 text-sm font-medium">زبان‌ها</p>
                    <div className="space-y-2">
                        {skills.languages.map((language, index) => (
                            <div
                                key={index}
                                className="grid grid-cols-1 gap-4 rounded-lg border bg-muted/30 p-3 md:grid-cols-4"
                            >
                                <DataRow
                                    label="زبان"
                                    value={language.language}
                                />
                                <DataRow
                                    label="خواندن"
                                    value={language.reading}
                                />
                                <DataRow
                                    label="نوشتن"
                                    value={language.writing}
                                />
                                <DataRow
                                    label="صحبت"
                                    value={language.speaking}
                                />
                            </div>
                        ))}
                    </div>
                </div>
            ) : null}

            {specialized.length ? (
                <div className="mb-4">
                    <p className="mb-2 text-sm font-medium">
                        مهارت‌های نرم‌افزاری تخصصی
                    </p>
                    <div className="flex flex-wrap gap-2">
                        {specialized.map((skill, index) => (
                            <SkillChip key={index}>
                                {skill.name} ({skill.level})
                            </SkillChip>
                        ))}
                    </div>
                </div>
            ) : null}

            {general.length ? (
                <div className="mb-4">
                    <p className="mb-2 text-sm font-medium">
                        مهارت‌های نرم‌افزاری عمومی
                    </p>
                    <div className="flex flex-wrap gap-2">
                        {general.map((skill, index) => (
                            <SkillChip key={index}>
                                {skill.name} ({skill.level})
                            </SkillChip>
                        ))}
                    </div>
                </div>
            ) : null}

            {skills.certificates?.length ? (
                <div className="mb-4">
                    <p className="mb-2 text-sm font-medium">گواهینامه‌ها</p>
                    <div className="space-y-2">
                        {skills.certificates.map((certificate, index) => (
                            <div
                                key={index}
                                className="grid grid-cols-2 gap-4 rounded-lg border bg-muted/30 p-3"
                            >
                                <DataRow
                                    label="عنوان"
                                    value={certificate.title}
                                />
                                <DataRow
                                    label="اعتبار تا"
                                    value={toPersianDate(certificate.expire_at)}
                                />
                            </div>
                        ))}
                    </div>
                </div>
            ) : null}

            {skills.special_skills?.length ? (
                <div>
                    <p className="mb-2 text-sm font-medium">سایر مهارت‌ها</p>
                    <div className="flex flex-wrap gap-2">
                        {skills.special_skills.map((skill, index) => (
                            <SkillChip key={index}>{skill}</SkillChip>
                        ))}
                    </div>
                </div>
            ) : null}
        </SectionCard>
    );
}

function TrainingSection({ training }: { training: Training }) {
    return (
        <SectionCard title="آموزشی و تحقیقاتی">
            {training.training_courses?.length ? (
                <div className="mb-4">
                    <p className="mb-2 text-sm font-medium">دوره‌های آموزشی</p>
                    <div className="space-y-2">
                        {training.training_courses.map((course, index) => (
                            <div
                                key={index}
                                className="grid grid-cols-1 gap-4 rounded-lg border bg-muted/30 p-3 md:grid-cols-3"
                            >
                                <DataRow
                                    label="دوره"
                                    value={course.course_name}
                                />
                                <DataRow label="مدت" value={course.duration} />
                                <DataRow
                                    label="مؤسسه"
                                    value={course.institution}
                                />
                            </div>
                        ))}
                    </div>
                </div>
            ) : null}

            {training.researches?.length ? (
                <div className="mb-4">
                    <p className="mb-2 text-sm font-medium">پژوهش‌ها</p>
                    <div className="flex flex-wrap gap-2">
                        {training.researches.map((research, index) => (
                            <SkillChip key={index}>{research.title}</SkillChip>
                        ))}
                    </div>
                </div>
            ) : null}

            {training.professional_memberships && (
                <div className="border-t pt-4">
                    <DataRow
                        label="عضویت‌های حرفه‌ای"
                        value={training.professional_memberships}
                    />
                </div>
            )}
        </SectionCard>
    );
}

export function CvResumeView({
    cv,
    documents = [],
}: {
    cv: Cv;
    documents?: EntityDocument[];
}) {
    const personal: Partial<CvPersonalInfo> = cv.personal_info ?? {};
    const contact: Partial<CvContactInfo> = cv.contact_info ?? {};
    const additional: Partial<CvAdditionalInfo> = cv.additional_info ?? {};

    const docsBySlug = (slug: string) =>
        documents.filter((doc) => doc.category_slug === slug);

    const documentGroups = [
        { label: "رزومه", docs: docsBySlug(CV_DOC_CATEGORY_SLUGS.RESUME) },
        {
            label: "نامه معرفی",
            docs: docsBySlug(CV_DOC_CATEGORY_SLUGS.COVER_LETTER),
        },
        {
            label: "عکس پرسنلی",
            docs: docsBySlug(CV_DOC_CATEGORY_SLUGS.PERSONNEL_PHOTO),
        },
        {
            label: "سایر مدارک",
            docs: docsBySlug(CV_DOC_CATEGORY_SLUGS.OTHER_DOCUMENTS),
        },
    ];
    const hasAnyDocument = documentGroups.some(
        (group) => group.docs.length > 0,
    );

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
                                <DataRow
                                    label="موبایل"
                                    value={
                                        <span dir="ltr" className="text-sm">
                                            {cv.mobile}
                                        </span>
                                    }
                                />
                                <DataRow
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

            <SectionCard title="مشخصات فردی">
                <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <DataRow label="جنسیت" value={personal.gender} />
                    <DataRow
                        label="تاریخ تولد"
                        value={toPersianDate(personal.birth_date)}
                    />
                    <DataRow label="محل تولد" value={personal.birth_place} />
                    <DataRow label="کد ملی" value={personal.id_number} />
                    <DataRow
                        label="شماره شناسنامه"
                        value={personal.birth_certificate_number}
                    />
                    <DataRow
                        label="وضعیت تأهل"
                        value={personal.marital_status}
                    />
                    {personal.military_status && (
                        <DataRow
                            label="وضعیت خدمت"
                            value={personal.military_status.status}
                        />
                    )}
                </div>
            </SectionCard>

            <SectionCard title="اطلاعات تماس">
                <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <DataRow label="تلفن ثابت" value={contact.phone} />
                    <DataRow
                        label="تلفن اضطراری"
                        value={contact.emergency_phone}
                    />
                    {contact.address && (
                        <>
                            <DataRow
                                label="استان"
                                value={contact.address.province}
                            />
                            <DataRow label="شهر" value={contact.address.city} />
                            <DataRow
                                label="کد پستی"
                                value={contact.address.postal_code}
                            />
                            <DataRow
                                label="پلاک"
                                value={contact.address.plaque}
                            />
                            <DataRow
                                label="طبقه"
                                value={contact.address.floor}
                            />
                            <DataRow
                                label="واحد"
                                value={contact.address.unit}
                            />
                            <DataRow
                                label="آدرس"
                                value={contact.address.address}
                            />
                        </>
                    )}
                </div>
            </SectionCard>

            {cv.education && (
                <EducationSection
                    education={cv.education}
                    documents={docsBySlug(CV_DOC_CATEGORY_SLUGS.RESUME)}
                />
            )}
            {cv.work_experience && (
                <WorkExperienceSection work={cv.work_experience} />
            )}
            {cv.skills && <SkillsSection skills={cv.skills} />}
            {cv.training && <TrainingSection training={cv.training} />}

            <SectionCard title="اطلاعات تکمیلی">
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <DataRow label="علاقه‌مندی‌ها" value={additional.hobbies} />
                    <DataRow
                        label="نقاط قوت و بهبود"
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
                {additional.references?.length ? (
                    <div className="mt-4 space-y-2 border-t pt-4">
                        {additional.references.map((ref, index) => (
                            <div
                                key={index}
                                className="grid grid-cols-1 gap-4 rounded bg-muted/50 p-2 text-sm md:grid-cols-3"
                            >
                                <DataRow label="نام" value={ref.full_name} />
                                <DataRow
                                    label="رابطه"
                                    value={ref.relationship}
                                />
                                <DataRow
                                    label="تلفن محل کار"
                                    value={ref.workplace_phone}
                                />
                            </div>
                        ))}
                    </div>
                ) : null}
            </SectionCard>

            {hasAnyDocument && (
                <SectionCard title="همه مدارک بارگذاری شده">
                    <p className="mb-4 text-sm text-muted-foreground">
                        برای مشاهده جزئیات و پیش‌نمایش، روی هر مدرک کلیک کنید
                    </p>
                    <QuestionnaireDocumentGrouped groups={documentGroups} />
                </SectionCard>
            )}
        </div>
    );
}
