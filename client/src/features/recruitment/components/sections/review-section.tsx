import type { ReactFormExtendedApi } from "@tanstack/react-form";

import { IconPencil } from "@tabler/icons-react";

import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import type { Questionnaire } from "@/features/recruitment/types";

type SectionProps = {
    form: ReactFormExtendedApi<any, any, any, any, any, any, any, any, any, any, any, any>;
    questionnaire?: Questionnaire | null;
    onNavigateToStep?: (step: number) => void;
};

function DataRow({ label, value }: { label: string; value: React.ReactNode }) {
    return (
        <div className="flex flex-col gap-0.5">
            <span className="text-xs text-muted-foreground">{label}</span>
            <span className="text-sm">{value || <span className="text-muted-foreground">—</span>}</span>
        </div>
    );
}

function YesNo({ value }: { value: boolean | undefined }) {
    return <span>{value ? "بلی" : "خیر"}</span>;
}

function SectionHeader({ title, onEdit }: { title: string; onEdit?: () => void }) {
    return (
        <CardHeader className="flex flex-row items-center justify-between">
            <CardTitle>{title}</CardTitle>
            {onEdit && (
                <Button variant="ghost" size="sm" onClick={onEdit}>
                    <IconPencil className="size-3.5 ms-1" />
                    ویرایش
                </Button>
            )}
        </CardHeader>
    );
}

export function ReviewSection({ form, questionnaire, onNavigateToStep }: SectionProps) {
    const v = form.state.values;
    const pi = v.personal_info ?? {};
    const edu = v.education ?? {};
    const work = v.work_experience ?? {};
    const skills = v.skills ?? {};
    const training = v.training ?? {};
    const additional = v.additional_info ?? {};
    const job = v.job_request ?? {};

    return (
        <div className="space-y-4">
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

            {/* ── مشخصات فردی ── */}
            <Card>
                <SectionHeader title="مشخصات فردی" onEdit={() => onNavigateToStep?.(0)} />
                <CardContent>
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <DataRow label="نام" value={v.first_name} />
                        <DataRow label="نام خانوادگی" value={v.last_name} />
                        <DataRow label="نام انگلیسی" value={`${pi.first_name_en ?? ""} ${pi.last_name_en ?? ""}`.trim()} />
                        <DataRow label="جنسیت" value={pi.gender === "male" ? "مرد" : pi.gender === "female" ? "زن" : pi.gender} />
                        <DataRow label="گروه خونی" value={pi.blood_group} />
                        <DataRow label="تاریخ تولد" value={pi.birth_date} />
                        <DataRow label="محل تولد" value={pi.birth_place} />
                        <DataRow label="شماره شناسنامه" value={pi.birth_certificate_number} />
                        <DataRow label="نام پدر" value={pi.father_name} />
                        <DataRow label="مذهب" value={pi.religion} />
                        <DataRow label="وضعیت تأهل" value={pi.marital_status === "single" ? "مجرد" : pi.marital_status === "married" ? "متاهل" : pi.marital_status} />
                        <DataRow label="تعداد افراد تحت تکفل" value={pi.dependents_count} />
                        <DataRow label="تعداد فرزندان" value={pi.children_count} />
                        <DataRow label="وضعیت اشتغال همسر" value={pi.spouse_employment_status === "employed" ? "شاغل" : pi.spouse_employment_status === "housewife" ? "خانه دار" : pi.spouse_employment_status} />
                        <DataRow label="کد ملی" value={pi.national_id} />
                        <DataRow label="تلفن ثابت" value={pi.phone} />
                        <DataRow label="تلفن اضطراری" value={pi.emergency_phone} />
                        <DataRow label="آدرس" value={pi.address} />
                    </div>
                    {pi.military_status && (
                        <div className="mt-4 pt-4 border-t">
                            <p className="text-sm font-medium mb-2">وضعیت نظام وظیفه</p>
                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <DataRow label="وضعیت" value={pi.military_status.status} />
                                <DataRow label="سازمان" value={pi.military_status.organization} />
                                <DataRow label="از تاریخ" value={pi.military_status.from} />
                                <DataRow label="تا تاریخ" value={pi.military_status.to} />
                                <DataRow label="دلیل" value={pi.military_status.reason} />
                            </div>
                        </div>
                    )}
                </CardContent>
            </Card>

            {/* ── سوابق تحصیلی ── */}
            <Card>
                <SectionHeader title="سوابق تحصیلی" onEdit={() => onNavigateToStep?.(1)} />
                <CardContent className="space-y-4">
                    {edu.education_records?.length > 0 ? (
                        edu.education_records.map((rec: any, i: number) => (
                            <div key={i} className="grid grid-cols-1 md:grid-cols-3 gap-4 p-3 rounded-lg bg-muted/50">
                                <DataRow label="مدرک" value={rec.degree} />
                                <DataRow label="رشته" value={rec.field} />
                                <DataRow label="دانشگاه" value={rec.institution} />
                                <DataRow label="محل" value={rec.location} />
                                <DataRow label="از تاریخ" value={rec.from} />
                                <DataRow label="تا تاریخ" value={rec.to} />
                                <DataRow label="معدل" value={rec.gpa} />
                                <DataRow label="تاریخ فارغ‌التحصیلی" value={rec.graduation_date} />
                                <DataRow label="پایان‌نامه" value={rec.thesis_title} />
                            </div>
                        ))
                    ) : (
                        <p className="text-sm text-muted-foreground">سابقه تحصیلی ثبت نشده</p>
                    )}
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4 pt-2 border-t">
                        <DataRow label="دانشجو هستم" value={<YesNo value={edu.is_student} />} />
                        {edu.is_student && (
                            <>
                                <DataRow label="مقطع" value={edu.student_degree} />
                                <DataRow label="رشته" value={edu.student_field} />
                                <DataRow label="دانشگاه" value={edu.student_university} />
                                <DataRow label="معدل" value={edu.student_gpa} />
                            </>
                        )}
                    </div>
                </CardContent>
            </Card>

            {/* ── سوابق شغلی ── */}
            <Card>
                <SectionHeader title="سوابق شغلی" onEdit={() => onNavigateToStep?.(2)} />
                <CardContent className="space-y-4">
                    {work.work_experiences?.length > 0 ? (
                        work.work_experiences.map((exp: any, i: number) => (
                            <div key={i} className="grid grid-cols-1 md:grid-cols-3 gap-4 p-3 rounded-lg bg-muted/50">
                                <DataRow label="شرکت" value={exp.company} />
                                <DataRow label="سمت" value={exp.position} />
                                <DataRow label="صنعت" value={exp.industry} />
                                <DataRow label="محل کار" value={exp.location} />
                                <DataRow label="از تاریخ" value={exp.from} />
                                <DataRow label="تا تاریخ" value={exp.to} />
                                <DataRow label="نوع قرارداد" value={exp.contract_type} />
                                <DataRow label="آخرین حقوق" value={exp.last_salary} />
                                <DataRow label="دلیل ترک" value={exp.leave_reason} />
                            </div>
                        ))
                    ) : (
                        <p className="text-sm text-muted-foreground">سابقه شغلی ثبت نشده</p>
                    )}
                    <div className="pt-2 border-t">
                        <DataRow label="دستاوردها" value={work.achievements} />
                        <DataRow label="اجازه تماس با مدیران قبلی" value={<YesNo value={work.allow_contact_previous_managers} />} />
                    </div>
                </CardContent>
            </Card>

            {/* ── مهارت‌ها ── */}
            <Card>
                <SectionHeader title="مهارت‌ها" onEdit={() => onNavigateToStep?.(3)} />
                <CardContent className="space-y-4">
                    {skills.languages?.length > 0 && (
                        <div>
                            <p className="text-sm font-medium mb-2">زبان‌ها</p>
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-2">
                                {skills.languages.map((lang: any, i: number) => (
                                    <div key={i} className="text-sm p-2 rounded bg-muted/50">
                                        {lang.language}: خواندن {lang.reading}، نوشتن {lang.writing}، صحبت {lang.speaking}، درک مطلب {lang.comprehension}
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                    {skills.software_skills?.specialized?.length > 0 && (
                        <div>
                            <p className="text-sm font-medium mb-2">نرم‌افزارهای تخصصی</p>
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-2">
                                {skills.software_skills.specialized.map((s: any, i: number) => (
                                    <div key={i} className="text-sm p-2 rounded bg-muted/50">{s.name} — سطح {s.level}</div>
                                ))}
                            </div>
                        </div>
                    )}
                    {skills.software_skills?.general?.length > 0 && (
                        <div>
                            <p className="text-sm font-medium mb-2">نرم‌افزارهای عمومی</p>
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-2">
                                {skills.software_skills.general.map((s: any, i: number) => (
                                    <div key={i} className="text-sm p-2 rounded bg-muted/50">{s.name} — سطح {s.level}</div>
                                ))}
                            </div>
                        </div>
                    )}
                    {skills.special_skills?.length > 0 && (
                        <div>
                            <p className="text-sm font-medium mb-2">مهارت‌های خاص</p>
                            <div className="flex flex-wrap gap-2">
                                {skills.special_skills.map((s: string, i: number) => (
                                    <span key={i} className="text-sm px-2 py-1 rounded bg-muted/50">{s}</span>
                                ))}
                            </div>
                        </div>
                    )}
                </CardContent>
            </Card>

            {/* ── آموزشی و تحقیقاتی ── */}
            <Card>
                <SectionHeader title="آموزشی و تحقیقاتی" onEdit={() => onNavigateToStep?.(4)} />
                <CardContent className="space-y-4">
                    {training.training_courses?.length > 0 ? (
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-2">
                            {training.training_courses.map((c: any, i: number) => (
                                <div key={i} className="text-sm p-2 rounded bg-muted/50">
                                    {c.course_name} — {c.institution} ({c.duration})
                                </div>
                            ))}
                        </div>
                    ) : (
                        <p className="text-sm text-muted-foreground">دوره آموزشی ثبت نشده</p>
                    )}
                    <DataRow label="عضویت‌های حرفه‌ای" value={training.professional_memberships} />
                    {training.researches?.length > 0 && (
                        <div>
                            <p className="text-sm font-medium mb-1">تحقیقات و پژوهش‌ها</p>
                            {training.researches.map((r: any, i: number) => (
                                <p key={i} className="text-sm">{i + 1}. {r.title}</p>
                            ))}
                        </div>
                    )}
                </CardContent>
            </Card>

            {/* ── اطلاعات تکمیلی ── */}
            <Card>
                <SectionHeader title="اطلاعات تکمیلی" onEdit={() => onNavigateToStep?.(5)} />
                <CardContent>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <DataRow label="بیماری مزمن" value={<YesNo value={additional.has_chronic_disease} />} />
                        <DataRow label="عمل جراحی سنگین" value={<YesNo value={additional.has_major_surgery} />} />
                        <DataRow label="معلولیت" value={<YesNo value={additional.has_disability} />} />
                        <DataRow label="امکان سفر" value={<YesNo value={additional.can_travel} />} />
                        <DataRow label="سوءسابقه کیفری" value={<YesNo value={additional.has_criminal_record} />} />
                    </div>
                    <div className="grid grid-cols-1 gap-4 mt-4 pt-4 border-t">
                        <DataRow label="توضیحات بیماری" value={additional.chronic_disease_description} />
                        <DataRow label="توضیحات جراحی" value={additional.major_surgery_description} />
                        <DataRow label="دلیل تمایل به همکاری" value={additional.reason_for_joining} />
                        <DataRow label="توضیحات معلولیت" value={additional.disability_description} />
                        <DataRow label="توضیحات سفر" value={additional.travel_description} />
                        <DataRow label="توضیحات سوءسابقه" value={additional.criminal_record_description} />
                        <DataRow label="نحوه آشنایی با شرکت" value={additional.company_introduction_method} />
                        <DataRow label="علاقه‌مندی‌ها" value={additional.hobbies} />
                        <DataRow label="نقاط قوت و زمینه‌های قابل بهبود" value={additional.strengths_and_improvements} />
                    </div>
                </CardContent>
            </Card>

            {/* ── نوع درخواست همکاری ── */}
            <Card>
                <SectionHeader title="نوع درخواست همکاری" onEdit={() => onNavigateToStep?.(6)} />
                <CardContent>
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <DataRow label="نوع اشتغال" value={job.employment_type === "full_time" ? "تمام وقت" : job.employment_type === "part_time" ? "پاره وقت" : job.employment_type} />
                        <DataRow label="حقوق ماهانه مورد انتظار" value={job.expected_monthly_salary} />
                        <DataRow label="حقوق ساعتی مورد انتظار" value={job.expected_hourly_salary} />
                        <DataRow label="حداقل ساعات کاری در ماه" value={job.minimum_hours_per_month} />
                        <DataRow label="ارسال رزومه قبلی" value={<YesNo value={job.submitted_resume_before} />} />
                        <DataRow label="مصاحبه قبلی" value={<YesNo value={job.interviewed_before} />} />
                        <DataRow label="شاغل در حال حاضر" value={<YesNo value={job.currently_employed} />} />
                        <DataRow label="تاریخ شروع به کار" value={job.available_start_date} />
                        <DataRow label="محل کار مورد نظر" value={job.preferred_workplace?.join("، ")} />
                        <DataRow label="اولویت شغلی ۱" value={job.job_priority_1} />
                        <DataRow label="اولویت شغلی ۲" value={job.job_priority_2} />
                    </div>
                    <div className="mt-4 pt-4 border-t">
                        <DataRow label="سایر اطلاعات" value={job.other_information} />
                    </div>
                </CardContent>
            </Card>

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
                                questionnaire?.email_verified ? (
                                    <span className="text-green-600 font-medium">تأیید شده</span>
                                ) : (
                                    <span className="text-muted-foreground">تأیید نشده</span>
                                )
                            }
                        />
                        <DataRow
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
