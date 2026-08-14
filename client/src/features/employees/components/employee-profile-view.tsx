import { useState } from "react";
import { useQuery } from "@tanstack/react-query";

import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Skeleton } from "@/components/ui/skeleton";
import { SectionRow } from "@/components/shared/section-row";
import { SectionRepeaterTable } from "@/components/shared/section-repeater-table";
import { DocumentSection } from "@/features/documents/components/document-section";
import { LinkedUserSection } from "@/features/employees/components/sections/linked-user-section";
import { usePermission } from "@/features/auth/components/permission-guard";
import { fetchUserRoles } from "@/features/rbac/api";
import { userKeys } from "@/lib/query-keys";
import { PERMISSIONS } from "@/lib/permissions";
import {
    employmentLabels,
    statusLabels,
    statusVariants,
    EMPLOYEE_DOCUMENTS_TAB,
    EMPLOYEE_LINKED_USER_TAB,
    EMPLOYEE_SECTIONS,
} from "@/features/employees/constants";
import type { Employee } from "@/features/employees/types";
import { SocialInsuranceView } from "./views/social-insurance-view";

function asRecord(value: unknown): Record<string, unknown> {
    return value && typeof value === "object"
        ? (value as Record<string, unknown>)
        : {};
}

function stringValue(value: unknown): string | null {
    if (value === null || value === undefined || value === "") return null;
    return String(value);
}

function PersonalInfoView({ employee }: { employee: Employee }) {
    const personal = asRecord(employee.section_personal);
    const military = asRecord(personal.military_status);
    const spouseEmployed = stringValue(personal.spouse_employment_status);

    return (
        <div className="grid gap-6 md:grid-cols-2">
            <Card>
                <CardHeader>
                    <CardTitle>اطلاعات هویتی</CardTitle>
                </CardHeader>
                <CardContent className="divide-y">
                    <SectionRow hideEmpty label="کد ملی" value={employee.id_number} />
                    <SectionRow hideEmpty label="جنسیت" value={employee.gender} />
                    <SectionRow hideEmpty label="تاریخ تولد" value={employee.birth_date} />
                    <SectionRow hideEmpty label="وضعیت تأهل" value={employee.marital_status} />
                    <SectionRow hideEmpty label="نام (انگلیسی)" value={personal.first_name_en} />
                    <SectionRow hideEmpty
                        label="نام خانوادگی (انگلیسی)"
                        value={personal.last_name_en}
                    />
                    <SectionRow hideEmpty label="محل تولد" value={personal.birth_place} />
                    <SectionRow hideEmpty
                        label="شماره شناسنامه"
                        value={personal.birth_certificate_number}
                    />
                    <SectionRow hideEmpty label="نام پدر" value={personal.father_name} />
                    <SectionRow hideEmpty label="دین" value={personal.religion} />
                    <SectionRow hideEmpty label="مذهب" value={personal.religion_sect} />
                    <SectionRow hideEmpty label="گروه خونی" value={personal.blood_group} />
                    <SectionRow hideEmpty
                        label="تعداد افراد تحت تکفل"
                        value={personal.dependents_count}
                    />
                    <SectionRow hideEmpty
                        label="تعداد فرزندان"
                        value={personal.children_count}
                    />
                    {spouseEmployed && (
                        <SectionRow hideEmpty label="شغل همسر" value={personal.spouse_job} />
                    )}
                </CardContent>
            </Card>

            <div className="space-y-6">
                {stringValue(military.status) && (
                    <Card>
                        <CardHeader>
                            <CardTitle>وضعیت نظام وظیفه</CardTitle>
                        </CardHeader>
                        <CardContent className="divide-y">
                            <SectionRow hideEmpty label="وضعیت" value={military.status} />
                            <SectionRow hideEmpty
                                label="محل خدمت"
                                value={military.organization}
                            />
                            <SectionRow hideEmpty label="از تاریخ" value={military.from} />
                            <SectionRow hideEmpty label="تا تاریخ" value={military.to} />
                            <SectionRow hideEmpty label="توضیحات" value={military.reason} />
                        </CardContent>
                    </Card>
                )}
            </div>
        </div>
    );
}

function LinkedUserRolesView({
    user,
}: {
    user: NonNullable<Employee["user"]>;
}) {
    const canViewRoles = usePermission([PERMISSIONS.ROLE_VIEW]);

    const { data: userRoles, isLoading } = useQuery({
        queryKey: userKeys.roles(user.id),
        queryFn: async () => {
            const { data } = await fetchUserRoles(user.id);
            return data;
        },
        enabled: canViewRoles,
    });

    if (!canViewRoles) {
        return (
            <Card>
                <CardHeader>
                    <CardTitle>نقش‌های کاربر</CardTitle>
                </CardHeader>
                <CardContent>
                    <p className="text-sm text-muted-foreground">
                        برای مشاهده نقش‌های کاربر دسترسی کافی ندارید
                    </p>
                </CardContent>
            </Card>
        );
    }

    const roles = userRoles?.roles ?? [];
    const activeRoleId = userRoles?.active_role?.id;

    return (
        <Card>
            <CardHeader>
                <CardTitle>نقش‌های کاربر</CardTitle>
            </CardHeader>
            <CardContent className="space-y-3">
                {isLoading ? (
                    <div className="space-y-2">
                        <Skeleton className="h-8 w-40" />
                        <Skeleton className="h-8 w-32" />
                    </div>
                ) : roles.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        هیچ نقشی تخصیص داده نشده است
                    </p>
                ) : (
                    roles.map((role) => (
                        <div
                            key={role.id}
                            className="flex items-center justify-between gap-4"
                        >
                            <span className="text-sm font-medium">
                                {role.display_name}
                            </span>
                            {activeRoleId === role.id && (
                                <Badge variant="default">نقش فعال</Badge>
                            )}
                        </div>
                    ))
                )}
            </CardContent>
        </Card>
    );
}

function EmploymentInfoView({ employee }: { employee: Employee }) {
    return (
        <div className="grid gap-6 md:grid-cols-2">
            <Card>
                <CardHeader>
                    <CardTitle>اطلاعات شغلی</CardTitle>
                </CardHeader>
                <CardContent className="divide-y">
                    <SectionRow
                        label="کد پرسنلی"
                        value={employee.personnel_code}
                    />
                    <SectionRow
                        label="نوع استخدام"
                        value={
                            employee.employment_type
                                ? (employmentLabels[employee.employment_type] ??
                                  employee.employment_type)
                                : "—"
                        }
                    />
                    <SectionRow label="تاریخ استخدام" value={employee.hire_date} />
                    <SectionRow
                        label="وضعیت اشتغال"
                        value={
                            <Badge
                                variant={
                                    statusVariants[
                                        employee.employment_status ?? ""
                                    ] ?? "secondary"
                                }
                            >
                                {statusLabels[
                                    employee.employment_status ?? ""
                                ] ?? employee.employment_status}
                            </Badge>
                        }
                    />
                </CardContent>
            </Card>
            {employee.user ? (
                <LinkedUserRolesView user={employee.user} />
            ) : (
                <Card>
                    <CardHeader>
                        <CardTitle>نقش‌های کاربر</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p className="text-sm text-muted-foreground">
                            کاربری به این کارمند متصل نیست
                        </p>
                    </CardContent>
                </Card>
            )}
        </div>
    );
}

function ContactInfoView({ employee }: { employee: Employee }) {
    const contact = asRecord(employee.section_contact_address);
    const address = asRecord(contact.address);

    return (
        <Card>
            <CardHeader>
                <CardTitle>اطلاعات تماس</CardTitle>
            </CardHeader>
            <CardContent className="divide-y">
                <SectionRow hideEmpty label="ایمیل" value={employee.email} />
                <SectionRow hideEmpty label="شماره موبایل" value={employee.mobile} />
                <SectionRow hideEmpty label="تلفن ثابت" value={contact.phone} />
                <SectionRow hideEmpty label="تلفن اضطراری" value={contact.emergency_phone} />
                <SectionRow hideEmpty label="کد پستی" value={address.postal_code} />
                <SectionRow hideEmpty label="استان" value={address.province} />
                <SectionRow hideEmpty label="شهر" value={address.city} />
                <SectionRow hideEmpty label="محله" value={address.neighborhood} />
                <SectionRow hideEmpty label="آدرس" value={address.address} />
                <SectionRow hideEmpty label="پلاک" value={address.plaque} />
                <SectionRow hideEmpty label="طبقه" value={address.floor} />
                <SectionRow hideEmpty label="واحد" value={address.unit} />
            </CardContent>
        </Card>
    );
}

function EducationView({ employee }: { employee: Employee }) {
    const education = asRecord(employee.section_education);
    const isStudent = Boolean(education.is_student);

    return (
        <Card>
            <CardHeader>
                <CardTitle>سوابق تحصیلی</CardTitle>
            </CardHeader>
            <CardContent className="space-y-6">
                <SectionRepeaterTable
                    items={education.education_records}
                    emptyLabel="سابقه تحصیلی ثبت نشده است."
                    columns={[
                        { label: "مدرک", render: (i) => i.degree },
                        { label: "رشته", render: (i) => i.field },
                        { label: "دانشگاه", render: (i) => i.institution },
                        { label: "از تاریخ", render: (i) => i.from },
                        { label: "تا تاریخ", render: (i) => i.to },
                        {
                            label: "تاریخ فارغ‌التحصیلی",
                            render: (i) => i.graduation_date,
                        },
                        { label: "معدل", render: (i) => i.gpa },
                    ]}
                />
                {isStudent && (
                    <div className="rounded-lg border p-4">
                        <p className="text-sm font-medium mb-3">
                            وضعیت دانشجویی
                        </p>
                        <div className="grid gap-6 md:grid-cols-2">
                            <Card className="border-0 shadow-none">
                                <CardContent className="divide-y p-0">
                                    <SectionRow hideEmpty
                                        label="مقطع تحصیلی"
                                        value={education.student_degree}
                                    />
                                    <SectionRow hideEmpty
                                        label="رشته تحصیلی"
                                        value={education.student_field}
                                    />
                                    <SectionRow hideEmpty
                                        label="دانشگاه"
                                        value={education.student_university}
                                    />
                                    <SectionRow hideEmpty
                                        label="کشور"
                                        value={education.student_country}
                                    />
                                    <SectionRow hideEmpty
                                        label="شهر"
                                        value={education.student_city}
                                    />
                                </CardContent>
                            </Card>
                            <Card className="border-0 shadow-none">
                                <CardContent className="divide-y p-0">
                                    <SectionRow hideEmpty
                                        label="ترم فعلی"
                                        value={education.student_semester}
                                    />
                                    <SectionRow hideEmpty
                                        label="واحدهای گذرانده"
                                        value={education.passed_units}
                                    />
                                    <SectionRow hideEmpty
                                        label="واحدهای باقی‌مانده"
                                        value={education.remaining_units}
                                    />
                                    <SectionRow hideEmpty
                                        label="معدل"
                                        value={education.student_gpa}
                                    />
                                    <SectionRow hideEmpty
                                        label="تاریخ شروع"
                                        value={education.study_start}
                                    />
                                    <SectionRow hideEmpty
                                        label="تاریخ فارغ‌التحصیلی مورد انتظار"
                                        value={education.expected_graduation}
                                    />
                                </CardContent>
                            </Card>
                        </div>
                        <div className="mt-4">
                            <SectionRow hideEmpty
                                label="ارائه پایان‌نامه"
                                value={
                                    stringValue(education.thesis_submitted)
                                        ? education.thesis_submitted
                                            ? "بله"
                                            : "خیر"
                                        : null
                                }
                            />
                        </div>
                        <SectionRow hideEmpty
                            label="عنوان پایان‌نامه"
                            value={education.student_thesis_title}
                        />
                        <SectionRow hideEmpty
                            label="روزهای آزاد در هفته"
                            value={education.free_days_per_week}
                        />
                    </div>
                )}
                <SectionRow hideEmpty
                    label="توضیحات تحصیلی"
                    value={education.education_description}
                />
            </CardContent>
        </Card>
    );
}

function WorkExperienceView({ employee }: { employee: Employee }) {
    const work = asRecord(employee.section_work_experience);

    return (
        <Card>
            <CardHeader>
                <CardTitle>سوابق شغلی</CardTitle>
            </CardHeader>
            <CardContent className="space-y-6">
                <SectionRepeaterTable
                    items={work.work_experiences}
                    emptyLabel="سابقه شغلی ثبت نشده است."
                    columns={[
                        { label: "شرکت", render: (i) => i.company },
                        { label: "سمت", render: (i) => i.position },
                        { label: "از تاریخ", render: (i) => i.from },
                        { label: "تا تاریخ", render: (i) => i.to },
                        { label: "توضیحات", render: (i) => i.description },
                    ]}
                />
                <SectionRow hideEmpty label="دستاوردها" value={work.achievements} />
                <SectionRow hideEmpty
                    label="مجاز به تماس با مدیران قبلی"
                    value={
                        stringValue(work.allow_contact_previous_managers)
                            ? work.allow_contact_previous_managers
                                ? "بله"
                                : "خیر"
                            : null
                    }
                />
                <SectionRow hideEmpty
                    label="توضیحات محدودیت تماس"
                    value={work.contact_restriction_description}
                />
            </CardContent>
        </Card>
    );
}

function SkillsView({ employee }: { employee: Employee }) {
    const skills = asRecord(employee.section_skills);
    const software = asRecord(skills.software_skills);

    return (
        <Card>
            <CardHeader>
                <CardTitle>مهارت‌ها</CardTitle>
            </CardHeader>
            <CardContent className="space-y-6">
                <div>
                    <p className="text-sm font-medium mb-2">زبان‌ها</p>
                    <SectionRepeaterTable
                        items={skills.languages}
                        emptyLabel="زبانی ثبت نشده است."
                        columns={[
                            { label: "زبان", render: (i) => i.language },
                            { label: "خواندن", render: (i) => i.reading },
                            { label: "نوشتن", render: (i) => i.writing },
                            { label: "صحبت کردن", render: (i) => i.speaking },
                            {
                                label: "درک مطلب",
                                render: (i) => i.comprehension,
                            },
                        ]}
                    />
                </div>
                <div>
                    <p className="text-sm font-medium mb-2">گواهینامه‌ها</p>
                    <SectionRepeaterTable
                        items={skills.certificates}
                        emptyLabel="گواهینامه‌ای ثبت نشده است."
                        columns={[
                            { label: "نام", render: (i) => i.name },
                            { label: "مرجع", render: (i) => i.issuer },
                            { label: "تاریخ", render: (i) => i.date },
                        ]}
                    />
                </div>
                <div>
                    <p className="text-sm font-medium mb-2">مهارت‌های ویژه</p>
                    <SectionRepeaterTable
                        items={skills.special_skills}
                        emptyLabel="مهارت ویژه‌ای ثبت نشده است."
                        columns={[
                            { label: "مهارت", render: (i) => i.name },
                            { label: "سطح", render: (i) => i.level },
                        ]}
                    />
                </div>
                <div>
                    <p className="text-sm font-medium mb-2">
                        مهارت‌های تخصصی نرم‌افزاری
                    </p>
                    <SectionRepeaterTable
                        items={software.specialized}
                        emptyLabel="مهارت تخصصی ثبت نشده است."
                        columns={[
                            { label: "نام", render: (i) => i.name },
                            { label: "سطح", render: (i) => i.level },
                        ]}
                    />
                </div>
                <div>
                    <p className="text-sm font-medium mb-2">
                        مهارت‌های عمومی نرم‌افزاری
                    </p>
                    <SectionRepeaterTable
                        items={software.general}
                        emptyLabel="مهارت عمومی ثبت نشده است."
                        columns={[
                            { label: "نام", render: (i) => i.name },
                            { label: "سطح", render: (i) => i.level },
                        ]}
                    />
                </div>
            </CardContent>
        </Card>
    );
}

function TrainingView({ employee }: { employee: Employee }) {
    const training = asRecord(employee.section_training);

    return (
        <Card>
            <CardHeader>
                <CardTitle>دوره‌ها و پژوهش‌ها</CardTitle>
            </CardHeader>
            <CardContent className="space-y-6">
                <div>
                    <p className="text-sm font-medium mb-2">دوره‌های آموزشی</p>
                    <SectionRepeaterTable
                        items={training.training_courses}
                        emptyLabel="دوره آموزشی ثبت نشده است."
                        columns={[
                            { label: "نام دوره", render: (i) => i.course_name },
                            { label: "مدت", render: (i) => i.duration },
                            { label: "موسسه", render: (i) => i.institution },
                        ]}
                    />
                </div>
                <SectionRow hideEmpty
                    label="عضویت‌های حرفه‌ای"
                    value={training.professional_memberships}
                />
                <div>
                    <p className="text-sm font-medium mb-2">پژوهش‌ها</p>
                    <SectionRepeaterTable
                        items={training.researches}
                        emptyLabel="پژوهشی ثبت نشده است."
                        columns={[
                            { label: "عنوان", render: (i) => i.title },
                            { label: "نوع", render: (i) => i.type },
                            { label: "سال", render: (i) => i.year },
                        ]}
                    />
                </div>
            </CardContent>
        </Card>
    );
}

function AdditionalInfoView({ employee }: { employee: Employee }) {
    const info = asRecord(employee.section_additional_info);

    return (
        <Card>
            <CardHeader>
                <CardTitle>اطلاعات تکمیلی</CardTitle>
            </CardHeader>
            <CardContent className="space-y-6">
                <SectionRepeaterTable
                    items={info.references}
                    emptyLabel="ارجاعی ثبت نشده است."
                    columns={[
                        { label: "نام کامل", render: (i) => i.full_name },
                        { label: "رابطه", render: (i) => i.relationship },
                        {
                            label: "تلفن محل کار",
                            render: (i) => i.workplace_phone,
                        },
                    ]}
                />
                <div className="grid gap-6 md:grid-cols-2">
                    <Card className="border-0 shadow-none">
                        <CardContent className="divide-y p-0">
                            <SectionRow hideEmpty
                                label="بیماری مزمن"
                                value={info.has_chronic_disease ? "بله" : "خیر"}
                            />
                            <SectionRow hideEmpty
                                label="توضیحات بیماری مزمن"
                                value={info.chronic_disease_description}
                            />
                            <SectionRow hideEmpty
                                label="جراحی عمده"
                                value={info.has_major_surgery ? "بله" : "خیر"}
                            />
                            <SectionRow hideEmpty
                                label="توضیحات جراحی"
                                value={info.major_surgery_description}
                            />
                            <SectionRow hideEmpty
                                label="ناتوانی"
                                value={info.has_disability ? "بله" : "خیر"}
                            />
                            <SectionRow hideEmpty
                                label="نوع ناتوانی"
                                value={info.disability_type}
                            />
                        </CardContent>
                    </Card>
                    <Card className="border-0 shadow-none">
                        <CardContent className="divide-y p-0">
                            <SectionRow hideEmpty
                                label="سابقه کیفری"
                                value={info.has_criminal_record ? "بله" : "خیر"}
                            />
                            <SectionRow hideEmpty
                                label="توضیحات کیفری"
                                value={info.criminal_record_description}
                            />
                            <SectionRow hideEmpty
                                label="امکان سفر"
                                value={info.can_travel ? "بله" : "خیر"}
                            />
                            <SectionRow hideEmpty
                                label="توضیحات سفر"
                                value={info.travel_description}
                            />
                            <SectionRow hideEmpty label="علایق" value={info.hobbies} />
                            <SectionRow hideEmpty
                                label="نقاط قوت و بهبود"
                                value={info.strengths_and_improvements}
                            />
                        </CardContent>
                    </Card>
                </div>
            </CardContent>
        </Card>
    );
}

export function EmployeeProfileView({ employee }: EmployeeProfileViewProps) {
    const [activeTab, setActiveTab] = useState<string>(
        EMPLOYEE_SECTIONS[0].key,
    );
    const canManageDocuments = usePermission([
        PERMISSIONS.DOCUMENT_UPLOAD_OWN,
        PERMISSIONS.DOCUMENT_UPLOAD_ALL,
        PERMISSIONS.DOCUMENT_DELETE_OWN,
        PERMISSIONS.DOCUMENT_DELETE_ALL,
    ]);
    const tabs = [
        ...EMPLOYEE_SECTIONS,
        EMPLOYEE_DOCUMENTS_TAB,
        EMPLOYEE_LINKED_USER_TAB,
    ];

    const renderTab = (key: string) => {
        switch (key) {
            case "personal_info":
                return <PersonalInfoView employee={employee} />;
            case "contact_info":
                return <ContactInfoView employee={employee} />;
            case "employment":
                return <EmploymentInfoView employee={employee} />;
            case "education":
                return <EducationView employee={employee} />;
            case "work_experience":
                return <WorkExperienceView employee={employee} />;
            case "social_insurance":
                return <SocialInsuranceView employee={employee} />;
            case "skills":
                return <SkillsView employee={employee} />;
            case "training":
                return <TrainingView employee={employee} />;
            case "additional_info":
                return <AdditionalInfoView employee={employee} />;
            case "documents":
                return (
                    <DocumentSection
                        documentableType="employee"
                        documentableId={employee.id}
                        showActions={canManageDocuments}
                    />
                );
            case "linked_user":
                return <LinkedUserSection employee={employee} />;
            default:
                return null;
        }
    };

    return (
        <Tabs
            value={activeTab}
            onValueChange={(value) => {
                if (value) setActiveTab(String(value));
            }}
            className="space-y-6"
        >
            <TabsList className="flex-wrap bg-muted/50">
                {tabs.map((tab) => (
                    <TabsTrigger key={tab.key} value={tab.key}>
                        {tab.label}
                    </TabsTrigger>
                ))}
            </TabsList>
            {tabs.map((tab) => (
                <TabsContent key={tab.key} value={tab.key}>
                    {renderTab(tab.key)}
                </TabsContent>
            ))}
        </Tabs>
    );
}

type EmployeeProfileViewProps = {
    employee: Employee;
};
