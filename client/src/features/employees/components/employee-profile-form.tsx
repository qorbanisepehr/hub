import { useCallback, useEffect, useMemo, useState } from "react";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { isAxiosError } from "axios";
import { toast } from "sonner";
import {
    IconAlertTriangle,
    IconChecks,
    IconClipboardCheck,
    IconLoader2,
    IconSend,
} from "@tabler/icons-react";

import { Button } from "@/components/ui/button";
import { ErrorBanner } from "@/components/layout";
import { UnsavedChangesDialog } from "@/components/layout";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { PersonalInfoSection } from "@/features/questionnaire/components/sections/personal-info-section";
import { EducationSection } from "@/features/questionnaire/components/sections/education-section";
import { WorkExperienceSection } from "@/features/questionnaire/components/sections/work-experience-section";
import { SkillsSection } from "@/features/questionnaire/components/sections/skills-section";
import { TrainingSection } from "@/features/questionnaire/components/sections/training-section";
import { AdditionalInfoSection } from "@/features/questionnaire/components/sections/additional-info-section";
import { SocialInsuranceSection } from "@/features/employees/components/sections/social-insurance-section";
import { ContactInfoSection } from "./sections/contact-info-section";
import { LinkedUserSection } from "./sections/linked-user-section";
import { EmploymentSection } from "./sections/employment-section";
import { DocumentsSection } from "./sections/documents-section";
import { EmployeeReviewSection } from "./sections/employee-review-section";
import {
    defaultPersonalInfo,
    toPersonalInfoPayload,
} from "@/features/questionnaire/schemas/personal-info.schema";
import {
    defaultContactInfo,
    toContactInfoPayload,
} from "@/features/questionnaire/schemas/contact-info.schema";
import { defaultEducation } from "@/features/questionnaire/schemas/education.schema";
import { defaultWorkExperience } from "@/features/questionnaire/schemas/work-experience.schema";
import { defaultSkills } from "@/features/questionnaire/schemas/skills.schema";
import { defaultTraining } from "@/features/questionnaire/schemas/training.schema";
import { defaultAdditionalInfo } from "@/features/questionnaire/schemas/additional-info.schema";
import {
    defaultEmployeeEmployment,
    toEmploymentPayload,
} from "@/features/employees/schemas/employment.schema";
import {
    defaultSocialInsurance,
    toSocialInsurancePayload,
} from "@/features/employees/schemas/social-insurance.schema";
import { saveEmployeeSection, submitEmployee } from "@/features/employees/api";
import {
    EMPLOYEE_DOCUMENTS_TAB,
    EMPLOYEE_LINKED_USER_TAB,
    EMPLOYEE_REVIEW_TAB,
    EMPLOYEE_SECTIONS,
    EMPLOYEE_VALIDATION_SECTIONS,
} from "@/features/employees/constants";
import { useEmployeeSubmitOptions } from "@/features/employees/hooks/use-employee-submit-options";
import { buildValidateSubmitData } from "@/features/employees/validation";
import { useInjectedFieldErrors } from "@/hooks/use-injected-field-errors";
import { useSectionForm } from "@/hooks/use-section-form";
import {
    countSectionFieldErrors,
    scrollToFirstInvalidField,
} from "@/lib/validation-helpers";
import { getApiError } from "@/lib/error-utils";
import { employeeKeys } from "@/lib/query-keys";
import type {
    Employee,
    EmployeeProfileFormData,
} from "@/features/employees/types";

type EmployeeProfileFormProps = {
    employee: Employee;
};

const PROFILE_TAB_KEYS = new Set<string>([
    ...EMPLOYEE_SECTIONS.map((section) => section.key),
    EMPLOYEE_DOCUMENTS_TAB.key,
    EMPLOYEE_LINKED_USER_TAB.key,
    EMPLOYEE_REVIEW_TAB.key,
]);

/** Read the active tab key from the URL hash (e.g. `#review`). */
function getSectionFromHash(): string {
    const hash = window.location.hash.replace("#", "");
    if (PROFILE_TAB_KEYS.has(hash)) return hash;
    return EMPLOYEE_SECTIONS[0].key;
}

function setSectionHash(key: string) {
    window.location.hash = `#${key}`;
}

const SECTION_PAYLOAD_BUILDERS: Record<
    string,
    (values: EmployeeProfileFormData) => Record<string, unknown>
> = {
    personal_info: toPersonalInfoPayload,
    contact_info: toContactInfoPayload,
    employment: toEmploymentPayload,
    social_insurance: toSocialInsurancePayload,
};

/**
 * Build the profile's default values from an employee. The server is the source
 * of truth after every section save, so this is also used to reset the form
 * from the save response instead of re-reading possibly stale local state.
 *
 * Real-column fields (identity + contact) are merged back into their sections
 * because the JSONB remainder intentionally excludes them.
 */
function buildDefaultValues(employee: Employee): EmployeeProfileFormData {
    return {
        first_name: employee.first_name ?? "",
        last_name: employee.last_name ?? "",
        email: employee.email ?? "",
        mobile: employee.mobile ?? "",
        personal_info: {
            ...defaultPersonalInfo(),
            ...(employee.section_personal ?? {}),
            id_number: employee.id_number ?? "",
            gender: employee.gender ?? "",
            birth_date: employee.birth_date ?? "",
            marital_status: employee.marital_status ?? "",
        },
        contact_info: {
            ...defaultContactInfo(),
            ...(employee.section_contact_address ?? {}),
            email: employee.email ?? "",
            mobile: employee.mobile ?? "",
        },
        employment: {
            ...defaultEmployeeEmployment(),
            personnel_code: employee.personnel_code ?? "",
            employment_type: employee.employment_type ?? "",
            hire_date: employee.hire_date ?? "",
            employment_status: employee.employment_status ?? "",
        },
        education: {
            ...defaultEducation(),
            ...(employee.section_education ?? {}),
        },
        work_experience: {
            ...defaultWorkExperience(),
            ...(employee.section_work_experience ?? {}),
        },
        social_insurance: {
            ...defaultSocialInsurance(),
            ...(employee.section_social_insurance ?? {}),
            social_insurance_number: employee.social_insurance_number ?? "",
        },
        skills: { ...defaultSkills(), ...(employee.section_skills ?? {}) },
        training: { ...defaultTraining(), ...(employee.section_training ?? {}) },
        additional_info: {
            ...defaultAdditionalInfo(),
            ...(employee.section_additional_info ?? {}),
        },
    };
}

/**
 * Extract the payload for one section from the full form values. Top-level
 * identity/contact fields win: the JSONB copy is stale and must never overwrite
 * what the user just typed. Sections without a dedicated builder pass through.
 */
function extractSectionData(
    values: EmployeeProfileFormData,
    sectionKey: string,
): Record<string, unknown> {
    const builder = SECTION_PAYLOAD_BUILDERS[sectionKey];
    if (builder) {
        return builder(values);
    }
    return (
        (values[sectionKey as keyof EmployeeProfileFormData] as
            | Record<string, unknown>
            | undefined) ?? {}
    );
}

export function EmployeeProfileForm({ employee }: EmployeeProfileFormProps) {
    const queryClient = useQueryClient();
    const profileTabs = useMemo(
        () => [
            ...EMPLOYEE_SECTIONS,
            EMPLOYEE_DOCUMENTS_TAB,
            EMPLOYEE_LINKED_USER_TAB,
            EMPLOYEE_REVIEW_TAB,
        ],
        [],
    );
    const formSectionKeys = useMemo(
        () => new Set<string>(EMPLOYEE_SECTIONS.map((s) => s.key)),
        [],
    );
    const [activeSection, setActiveSection] = useState<string>(
        getSectionFromHash,
    );
    const [submitErrors, setSubmitErrors] = useState<string[]>([]);

    useEffect(() => {
        const onHashChange = () => {
            const key = getSectionFromHash();
            setActiveSection((prev) => (prev === key ? prev : key));
        };
        window.addEventListener("hashchange", onHashChange);
        return () => window.removeEventListener("hashchange", onHashChange);
    }, []);

    const { form, saveMutation, persistSection, isDirty, isSectionDirty } = useSectionForm<
        Employee,
        EmployeeProfileFormData
    >({
        entity: employee,
        buildDefaultValues,
        extractSectionData,
        saveSection: (section, data) => saveEmployeeSection(employee.id, section, data),
        detailQueryKey: () => employeeKeys.detail(employee.id),
        sectionTopLevelKeys: {
            personal_info: ["first_name", "last_name"],
            contact_info: ["email", "mobile"],
        },
        successMessage: "بخش ذخیره شد.",
    });

    const handlePersist = useCallback(() => {
        persistSection(activeSection);
    }, [activeSection, persistSection]);

    const { submitOptions, optionsReady } = useEmployeeSubmitOptions();

    const submitMutation = useMutation({
        mutationFn: () => submitEmployee(employee.id),
        onSuccess: () => {
            queryClient.invalidateQueries({
                queryKey: employeeKeys.detail(employee.id),
            });
            toast.success("پروفایل کارمند با موفقیت ثبت شد.");
        },
        onError: (error: Error) => {
            if (isAxiosError(error) && error.response?.data?.errors) {
                const serverErrors = Object.values(error.response.data.errors)
                    .filter(Array.isArray)
                    .flat()
                    .filter((m): m is string => typeof m === "string");
                setSubmitErrors(
                    serverErrors.length > 0
                        ? serverErrors
                        : [getApiError(error) ?? "خطای ناشناخته"],
                );
            } else {
                setSubmitErrors([getApiError(error) ?? "خطا در ثبت پروفایل"]);
            }
        },
    });

    const validateSubmit = useMemo(
        () => buildValidateSubmitData(submitOptions),
        [submitOptions],
    );

    const validation = validateSubmit(form.state.values);

    const { inject: injectFieldErrors, clear: clearInjectedErrors } =
        useInjectedFieldErrors(form);

    useEffect(() => {
        if (isDirty) {
            setSubmitErrors([]);
        }
    }, [isDirty]);

    const canSubmit = optionsReady && validation.success;

    const handleSubmit = () => {
        if (!optionsReady) return;
        if (!validation.success) {
            setSubmitErrors(validation.errors);
            toast.error("لطفاً خطاهای زیر را اصلاح کنید.");
            return;
        }
        setSubmitErrors([]);
        submitMutation.mutate();
    };

    const handleValidateClick = () => {
        if (!optionsReady) return;
        const result = validateSubmit(form.state.values);

        if (result.success) {
            clearInjectedErrors();
            toast.success("همه فیلدهای الزامی تکمیل شده‌اند.");
            return;
        }

        const section = EMPLOYEE_VALIDATION_SECTIONS.find(
            (s) => s.key === activeSection,
        );
        if (section) {
            injectFieldErrors(result.fieldErrors, section);
        }

        const currentCount = section
            ? countSectionFieldErrors(result.fieldErrors, section)
            : 0;
        const otherCount = result.errors.length - currentCount;

        if (currentCount > 0) {
            scrollToFirstInvalidField();
        }

        toast.error("فیلدهای الزامی ناقص هستند", {
            description:
                currentCount > 0
                    ? `${currentCount} خطا در این بخش${
                          otherCount > 0
                              ? ` و ${otherCount} خطا در سایر بخش‌ها`
                              : ""
                      }`
                    : `${otherCount} خطا در سایر بخش‌ها وجود دارد.`,
            duration: 5000,
        });
    };

    const handleTabChange = (value: string | number | null) => {
        if (!value) return;
        const next = String(value);
        if (
            next !== activeSection &&
            isSectionDirty(activeSection) &&
            formSectionKeys.has(activeSection)
        ) {
            persistSection(activeSection);
        }
        setActiveSection(next);
        setSectionHash(next);
    };

    const navigateToSection = (key: string) => {
        setActiveSection(key);
        setSectionHash(key);
    };

    const renderSection = (sectionKey: string) => {
        switch (sectionKey) {
            case "personal_info":
                return (
                    <PersonalInfoSection
                        form={form as never}
                        questionnaire={null}
                        uuid={String(employee.id)}
                        entity="employees"
                    />
                );
            case "contact_info":
                return <ContactInfoSection form={form as never} />;
            case "employment":
                return <EmploymentSection form={form as never} />;
            case "education":
                return (
                    <EducationSection
                        form={form as never}
                        entity="employees"
                        uuid={String(employee.id)}
                        onPersist={handlePersist}
                    />
                );
            case "work_experience":
                return (
                    <WorkExperienceSection
                        form={form as never}
                        entity="employees"
                        uuid={String(employee.id)}
                        onPersist={handlePersist}
                    />
                );
            case "social_insurance":
                return (
                    <SocialInsuranceSection
                        form={form as never}
                        uuid={String(employee.id)}
                    />
                );
            case "skills":
                return (
                    <SkillsSection
                        form={form as never}
                        entity="employees"
                        uuid={String(employee.id)}
                        onPersist={handlePersist}
                    />
                );
            case "training":
                return (
                    <TrainingSection
                        form={form as never}
                        entity="employees"
                        uuid={String(employee.id)}
                        onPersist={handlePersist}
                    />
                );
            case "additional_info":
                return (
                    <AdditionalInfoSection
                        form={form as never}
                        onPersist={handlePersist}
                    />
                );
            case "documents":
                return <DocumentsSection employeeId={employee.id} />;
            case "linked_user":
                return <LinkedUserSection employee={employee} />;
            case "review":
                return (
                    <EmployeeReviewSection
                        form={form as never}
                        employee={employee}
                        onNavigateToSection={navigateToSection}
                    />
                );
            default:
                return null;
        }
    };

    return (
        <div className="space-y-6">
            <UnsavedChangesDialog
                isDirty={isDirty}
                isSubmitting={
                    saveMutation.isPending || submitMutation.isPending
                }
            />

            <div className="flex items-center justify-between rounded-lg border p-4">
                <div>
                    <p className="text-sm text-muted-foreground">
                        هر بخش به‌صورت جداگانه ذخیره می‌شود؛ پس از تکمیل همه
                        بخش‌ها، پروفایل را ثبت نهایی کنید.
                    </p>
                </div>
                <div className="flex flex-col items-end gap-1">
                    <Button
                        type="button"
                        onClick={handleSubmit}
                        disabled={submitMutation.isPending || !canSubmit}
                    >
                        {submitMutation.isPending ? (
                            <IconLoader2 className="size-4 animate-spin" />
                        ) : (
                            <IconSend className="size-4" />
                        )}
                        ثبت نهایی پروفایل
                    </Button>
                    {!canSubmit && optionsReady && (
                        <p className="text-xs text-muted-foreground">
                            {!validation.success &&
                                "همه فیلدهای الزامی باید تکمیل شوند"}
                        </p>
                    )}
                </div>
            </div>

            {submitErrors.length > 0 && (
                <div className="flex items-start gap-3 rounded-lg bg-destructive/10 p-3 text-sm text-destructive">
                    <IconAlertTriangle className="mt-0.5 size-4 shrink-0" />
                    <div className="flex-1">
                        {submitErrors.length === 1 ? (
                            <p>{submitErrors[0]}</p>
                        ) : (
                            <ul className="space-y-1 list-disc ms-4">
                                {submitErrors.map((err, i) => (
                                    <li key={i}>{err}</li>
                                ))}
                            </ul>
                        )}
                    </div>
                </div>
            )}

            <Tabs
                orientation="vertical"
                value={activeSection}
                onValueChange={handleTabChange}
                className="gap-6 items-start"
            >
                <TabsList className="w-64 shrink-0 self-start items-stretch gap-1 bg-transparent">
                    {profileTabs.map((section) => (
                        <TabsTrigger
                            key={section.key}
                            value={section.key}
                            className="h-auto flex-col items-start gap-0.5 rounded-lg px-3 py-2.5"
                        >
                            <span className="text-sm font-medium">
                                {section.label}
                            </span>
                            <span className="text-xs text-muted-foreground">
                                {section.description}
                            </span>
                        </TabsTrigger>
                    ))}
                </TabsList>

                {profileTabs.map((section) => (
                    <TabsContent
                        key={section.key}
                        value={section.key}
                        className="min-w-0"
                    >
                        <div className="space-y-6">
                            {renderSection(section.key)}

                            {saveMutation.error && (
                                <ErrorBanner
                                    message={
                                        getApiError(saveMutation.error) ??
                                        "خطای ناشناخته"
                                    }
                                />
                            )}

                            {formSectionKeys.has(section.key) && (
                                <div className="flex items-center gap-3">
                                    <Button
                                        type="button"
                                        onClick={() =>
                                            persistSection(section.key)
                                        }
                                        disabled={
                                            saveMutation.isPending ||
                                            submitMutation.isPending ||
                                            !isDirty
                                        }
                                    >
                                        {saveMutation.isPending ? (
                                            <IconLoader2 className="size-4 animate-spin" />
                                        ) : (
                                            <IconChecks className="size-4" />
                                        )}
                                        ذخیره این بخش
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={handleValidateClick}
                                        disabled={
                                            saveMutation.isPending ||
                                            submitMutation.isPending
                                        }
                                    >
                                        <IconClipboardCheck className="size-4" />
                                        بررسی اعتبار
                                    </Button>
                                </div>
                            )}
                        </div>
                    </TabsContent>
                ))}
            </Tabs>
        </div>
    );
}
