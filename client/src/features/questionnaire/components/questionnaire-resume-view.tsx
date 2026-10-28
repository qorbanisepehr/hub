import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { SectionRow } from "@/components/shared/section-row";
import { SectionCard } from "@/components/section-views/section-card";
import { PersonalInfoView } from "@/components/section-views/personal-info-view";
import { ContactInfoView } from "@/components/section-views/contact-info-view";
import { EducationView } from "@/components/section-views/education-view";
import { WorkExperienceView } from "@/components/section-views/work-experience-view";
import { SkillsView } from "@/components/section-views/skills-view";
import { TrainingView } from "@/components/section-views/training-view";
import { AdditionalInfoView } from "@/components/section-views/additional-info-view";
import { useOptionLabel, useOptionLabels } from "@/components/section-views/use-option-label";
import type { Questionnaire } from "@/features/questionnaire/types";
import { toPersianDate } from "@/lib/date-format";

function YesNo({ value }: { value: boolean | undefined }) {
    return <span>{value ? "بله" : "خیر"}</span>;
}

/**
 * Read-only rendering of a submitted questionnaire for the management
 * detail page — mirrors the candidate's review summary, but with no edit
 * buttons and no candidate-grant document previews (the printable
 * document endpoint renders those server-side).
 */
export function QuestionnaireResumeView({ questionnaire }: { questionnaire: Questionnaire }) {
    const personal: Record<string, unknown> = {
        ...questionnaire.personal_info,
        first_name: questionnaire.first_name,
        last_name: questionnaire.last_name,
    };
    const contact: Record<string, unknown> = {
        ...questionnaire.contact_info,
        email: questionnaire.email,
        mobile: questionnaire.mobile,
    };
    const job: Record<string, unknown> = { ...questionnaire.job_request };
    const education: Record<string, unknown> = { ...questionnaire.education };
    const work: Record<string, unknown> = { ...questionnaire.work_experience };
    const skills: Record<string, unknown> = { ...questionnaire.skills };
    const training: Record<string, unknown> = { ...questionnaire.training };
    const additional: Record<string, unknown> = { ...questionnaire.additional_info };

    const employmentTypeLabel = useOptionLabel("employment_type", job.employment_type as string);
    const preferredWorkplaceLabels = useOptionLabels("preferred_workplace", job.preferred_workplace as string[] | undefined);

    return (
        <div className="space-y-4">
            <PersonalInfoView
                data={personal}
                title="مشخصات فردی"
            />

            <ContactInfoView
                data={contact}
                title="اطلاعات تماس"
            />

            <EducationView data={education} title="سوابق تحصیلی" />
            <WorkExperienceView data={work} title="سوابق شغلی" />
            <SkillsView data={skills} title="مهارت‌ها" />
            <TrainingView data={training} title="آموزشی و تحقیقاتی" />
            <AdditionalInfoView data={additional} title="اطلاعات تکمیلی" />

            {/* ── نوع درخواست همکاری ── */}
            <SectionCard title="نوع درخواست همکاری">
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <SectionRow variant="column" label="نوع اشتغال" value={employmentTypeLabel} />
                    <SectionRow variant="column" label="حقوق ماهانه مورد انتظار" value={job.expected_monthly_salary} />
                    <SectionRow variant="column" label="حقوق ساعتی مورد انتظار" value={job.expected_hourly_salary} />
                    <SectionRow variant="column" label="حداقل ساعات کاری در ماه" value={job.minimum_hours_per_month} />
                    <SectionRow variant="column" label="ارسال رزومه قبلی" value={<YesNo value={job.submitted_resume_before as boolean | undefined} />} />
                    <SectionRow variant="column" label="مصاحبه قبلی" value={<YesNo value={job.interviewed_before as boolean | undefined} />} />
                    <SectionRow variant="column" label="شاغل در حال حاضر" value={<YesNo value={job.currently_employed as boolean | undefined} />} />
                    <SectionRow variant="column" label="تاریخ شروع به کار" value={toPersianDate(job.available_start_date as string | undefined)} />
                    <SectionRow variant="column" label="محل کار مورد نظر" value={preferredWorkplaceLabels} />
                    <SectionRow variant="column" label="اولویت شغلی ۱" value={job.job_priority_1} />
                    <SectionRow variant="column" label="اولویت شغلی ۲" value={job.job_priority_2} />
                </div>
                <div className="mt-4 pt-4 border-t">
                    <SectionRow variant="column" label="سایر اطلاعات" value={job.other_information} />
                </div>
            </SectionCard>

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
                                questionnaire.email_verified ? (
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
                                questionnaire.mobile_verified ? (
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