import { useCallback, useMemo, type ReactNode } from "react";
import { toast } from "sonner";
import {
    IconChecks,
    IconClipboardCheck,
    IconExclamationCircle,
    IconLoader2,
    IconSend,
} from "@tabler/icons-react";

import { Button } from "@/components/ui/button";
import { ErrorBanner } from "@/components/layout";
import { UnsavedChangesDialog } from "@/components/layout";
import {
    useWizardState,
    useWizardSubmit,
    SubmitErrors,
} from "@/components/wizards";
import { SectionTabNav } from "@/components/wizards/section-tab-nav";
import type { WizardStep } from "@/components/wizards";
import { Tabs, TabsContent } from "@/components/ui/tabs";
import { PersonalInfoSection } from "@/features/questionnaire/components/sections/personal-info-section";
import { EducationSection } from "@/features/questionnaire/components/sections/education-section";
import { WorkExperienceSection } from "@/features/questionnaire/components/sections/work-experience-section";
import { SkillsSection } from "@/features/questionnaire/components/sections/skills-section";
import { TrainingSection } from "@/features/questionnaire/components/sections/training-section";
import { AdditionalInfoSection } from "@/features/questionnaire/components/sections/additional-info-section";
import { DependentsSection } from "@/features/employees/components/sections/dependents-section";
import { DocumentInquiriesSection } from "./sections/document-inquiries-section";
import { SocialInsuranceSection } from "@/features/employees/components/sections/social-insurance-section";
import { ContractsSection } from "@/features/employees/components/sections/contracts-section";
import { FinancialSection } from "@/features/employees/components/sections/financial-section";
import { SupplementaryInsuranceSection } from "@/features/employees/components/sections/supplementary-insurance-section";
import { useRowDocsFeedback } from "@/features/documents/hooks/use-row-docs-feedback";
import {
    educationRowLabel,
    EDUCATION_ROW_DOC_CATEGORIES,
} from "@/features/questionnaire/education-docs";
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
import {
    defaultContracts,
    toContractsPayload,
} from "@/features/employees/schemas/contracts.schema";
import {
    defaultFinancial,
    toFinancialPayload,
} from "@/features/employees/schemas/financial.schema";
import {
    defaultSupplementaryInsurance,
    toSupplementaryInsurancePayload,
} from "@/features/employees/schemas/supplementary-insurance.schema";
import {
    defaultDependents,
    toDependentsPayload,
} from "@/features/employees/schemas/dependents.schema";
import {
    defaultDocumentInquiries,
    toDocumentInquiriesPayload,
} from "@/features/employees/schemas/document-inquiries.schema";
import { saveEmployeeSection, submitEmployee } from "@/features/employees/api";
import {
    EMPLOYEE_DOCUMENTS_TAB,
    EMPLOYEE_LINKED_USER_TAB,
    EMPLOYEE_REVIEW_TAB,
    EMPLOYEE_SECTIONS,
    EMPLOYEE_VALIDATION_SECTIONS,
} from "@/features/employees/constants";
import { useEmployeeSubmitOptions } from "@/features/employees/hooks/use-employee-submit-options";
import { useDependentDocsFeedback } from "@/features/employees/hooks/use-dependent-docs-feedback";
import { buildSubmitValidator } from "@/features/employees/validation";
import { useSectionForm } from "@/hooks/use-section-form";
import { getApiError } from "@/lib/error-utils";
import { cleanServerSection } from "@/lib/form-utils";
import { employeeKeys } from "@/lib/query-keys";
import type {
    Employee,
    EmployeeFormApi,
    EmployeeProfileFormData,
} from "@/features/employees/types";

type EmployeeProfileFormProps = {
    employee: Employee;
    /**
     * Render-prop receiving the final-submit toolbar (ثبت نهایی + validation
     * state) so a page can place it in its header instead of the form body.
     * When omitted the toolbar renders as a bar above the tabs.
     */
    header?: (actions: ReactNode) => ReactNode;
};

const PROFILE_STEPS = [
    ...EMPLOYEE_SECTIONS,
    EMPLOYEE_DOCUMENTS_TAB,
    EMPLOYEE_LINKED_USER_TAB,
    EMPLOYEE_REVIEW_TAB,
] satisfies readonly WizardStep[];

const SECTION_PAYLOAD_BUILDERS: Record<
    string,
    (values: EmployeeProfileFormData) => Record<string, unknown>
> = {
    personal_info: toPersonalInfoPayload,
    contact_info: toContactInfoPayload,
    employment: toEmploymentPayload,
    social_insurance: toSocialInsurancePayload,
    contracts: toContractsPayload,
    financial: toFinancialPayload,
    supplementary_insurance: toSupplementaryInsurancePayload,
    dependents: toDependentsPayload,
    document_inquiries: toDocumentInquiriesPayload,
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
            ...cleanServerSection(employee.section_personal ?? {}),
            id_number: employee.id_number ?? "",
            gender: employee.gender ?? "",
            birth_date: employee.birth_date ?? "",
            marital_status: employee.marital_status ?? "",
        },
        contact_info: {
            ...defaultContactInfo(),
            ...cleanServerSection(employee.section_contact_address ?? {}),
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
            ...cleanServerSection(employee.section_education ?? {}),
        },
        work_experience: {
            ...defaultWorkExperience(),
            ...cleanServerSection(employee.section_work_experience ?? {}),
        },
        social_insurance: {
            ...defaultSocialInsurance(),
            ...cleanServerSection(employee.section_social_insurance ?? {}),
            social_insurance_number: employee.social_insurance_number ?? "",
        },
        contracts: {
            ...defaultContracts(),
            ...cleanServerSection(employee.section_contracts ?? {}),
        },
        financial: {
            ...defaultFinancial(),
            ...cleanServerSection(employee.section_financial ?? {}),
        },
        supplementary_insurance: {
            ...defaultSupplementaryInsurance(),
            ...cleanServerSection(
                employee.section_supplementary_insurance ?? {},
            ),
        },
        dependents: {
            ...defaultDependents(),
            ...cleanServerSection(employee.section_dependents ?? {}),
        },
        document_inquiries: {
            ...defaultDocumentInquiries(),
            ...cleanServerSection(employee.section_document_inquiries ?? {}),
        },
        skills: {
            ...defaultSkills(),
            ...cleanServerSection(employee.section_skills ?? {}),
        },
        training: {
            ...defaultTraining(),
            ...cleanServerSection(employee.section_training ?? {}),
        },
        additional_info: {
            ...defaultAdditionalInfo(),
            ...cleanServerSection(employee.section_additional_info ?? {}),
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

export function EmployeeProfileForm({ employee, header }: EmployeeProfileFormProps) {
    const formSectionKeys = useMemo(
        () => new Set<string>(EMPLOYEE_SECTIONS.map((s) => s.key)),
        [],
    );
    const { currentKey, goToKey } = useWizardState(PROFILE_STEPS);
    const activeSection = currentKey ?? EMPLOYEE_SECTIONS[0].key;

    const { form, saveMutation, persistSection, isDirty, syncDefaults } =
        useSectionForm<Employee, EmployeeProfileFormData>({
            entity: employee,
            buildDefaultValues,
            extractSectionData,
            saveSection: (section, data) =>
                saveEmployeeSection(employee.id, section, data),
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

    const validateSubmit = useMemo(
        () => buildSubmitValidator(submitOptions),
        [submitOptions],
    );

    const validation = validateSubmit(form.state.values);

    const dependentRows =
        (
            form.state.values.dependents as
                | { dependents?: { relationship_type?: string }[] }
                | undefined
        )?.dependents ?? [];
    const { messages: dependentDocErrors } = useDependentDocsFeedback(
        employee.id,
        dependentRows,
    );
    const educationRecords =
        (
            form.state.values.education as
                | { education_records?: Record<string, unknown>[] }
                | undefined
        )?.education_records ?? [];
    const { messages: educationDocErrors } = useRowDocsFeedback(
        {
            entity: "employees",
            uuid: employee.id,
            sectionKey: "education",
            categories: EDUCATION_ROW_DOC_CATEGORIES,
            fieldKeyFor: (index) => `edu-${index}`,
        },
        educationRecords,
        { rowLabel: educationRowLabel },
    );

    const rowDocErrors = [...dependentDocErrors, ...educationDocErrors];

    const { submitErrors, submitMutation, handleSubmit, handleValidateClick } =
        useWizardSubmit({
            form,
            isDirty,
            optionsReady,
            validateSubmit,
            getCurrentSectionKey: () => activeSection,
            validationSections: EMPLOYEE_VALIDATION_SECTIONS,
            guards: [
                {
                    errors: () => rowDocErrors,
                    message: "مدارک بارگذاری‌شده ناقص است.",
                },
            ],
            submit: {
                submitFn: () => submitEmployee(employee.id),
                detailQueryKey: () => employeeKeys.detail(employee.id),
                successMessage: "پروفایل کارمند با موفقیت ثبت شد.",
                errorFallback: "خطا در ثبت پروفایل",
            },
        });

    const canSubmit = optionsReady && validation.success;

    const handleTabChange = (value: string | number | null) => {
        if (!value) return;
        const next = String(value);
        // persistSection self-guards: untouched sections never hit the API.
        if (next !== activeSection && formSectionKeys.has(activeSection)) {
            persistSection(activeSection);
        }
        goToKey(next);
    };

    const navigateToSection = (key: string) => {
        goToKey(key);
    };

    const renderSection = (sectionKey: string) => {
        switch (sectionKey) {
            case "personal_info":
                return (
                    <PersonalInfoSection
                        form={form as unknown as EmployeeFormApi}
                        questionnaire={null}
                        uuid={String(employee.id)}
                        entity="employees"
                        onDefaultsSynced={syncDefaults}
                    />
                );
            case "contact_info":
                return (
                    <ContactInfoSection
                        form={form as unknown as EmployeeFormApi}
                    />
                );
            case "employment":
                return (
                    <EmploymentSection
                        form={form as unknown as EmployeeFormApi}
                    />
                );
            case "education":
                return (
                    <EducationSection
                        form={form as unknown as EmployeeFormApi}
                        entity="employees"
                        uuid={String(employee.id)}
                        onPersist={handlePersist}
                    />
                );
            case "work_experience":
                return (
                    <WorkExperienceSection
                        form={form as unknown as EmployeeFormApi}
                        entity="employees"
                        uuid={String(employee.id)}
                        onPersist={handlePersist}
                    />
                );
            case "social_insurance":
                return (
                    <SocialInsuranceSection
                        form={form as unknown as EmployeeFormApi}
                        uuid={String(employee.id)}
                    />
                );
            case "contracts":
                return (
                    <ContractsSection
                        form={form as unknown as EmployeeFormApi}
                        uuid={String(employee.id)}
                        onPersist={handlePersist}
                    />
                );
            case "financial":
                return (
                    <FinancialSection
                        form={form as unknown as EmployeeFormApi}
                        uuid={String(employee.id)}
                    />
                );
            case "supplementary_insurance":
                return (
                    <SupplementaryInsuranceSection
                        form={form as unknown as EmployeeFormApi}
                        uuid={String(employee.id)}
                        onPersist={handlePersist}
                    />
                );
            case "dependents":
                return (
                    <DependentsSection
                        form={form as unknown as EmployeeFormApi}
                        uuid={String(employee.id)}
                        onPersist={handlePersist}
                    />
                );
            case "document_inquiries":
                return (
                    <DocumentInquiriesSection
                        form={form as unknown as EmployeeFormApi}
                        uuid={String(employee.id)}
                        canUpdate={
                            employee.capabilities.document_inquiries_update
                        }
                    />
                );
            case "skills":
                return (
                    <SkillsSection
                        form={form as unknown as EmployeeFormApi}
                        entity="employees"
                        uuid={String(employee.id)}
                        onPersist={handlePersist}
                    />
                );
            case "training":
                return (
                    <TrainingSection
                        form={form as unknown as EmployeeFormApi}
                        entity="employees"
                        uuid={String(employee.id)}
                        onPersist={handlePersist}
                    />
                );
            case "additional_info":
                return (
                    <AdditionalInfoSection
                        form={form as unknown as EmployeeFormApi}
                        onPersist={handlePersist}
                    />
                );
            case "documents":
                return (
                    <DocumentsSection
                        employeeId={employee.id}
                        gender={employee.gender}
                    />
                );
            case "linked_user":
                return <LinkedUserSection employee={employee} />;
            case "review":
                return (
                    <EmployeeReviewSection
                        form={form as unknown as EmployeeFormApi}
                        employee={employee}
                        onNavigateToSection={navigateToSection}
                    />
                );
            default:
                return null;
        }
    };

    // The final-submit toolbar: the page may place it in its header via the
    // `header` render-prop; without one it renders as a bar above the tabs.
    // Loose nodes (no layout div) so the header's own flex-wrap lays them out.
    const finalSubmitActions = (
        <>
            {!validation.success && (
                <Button
                    type="button"
                    variant="outline"
                    size="icon-sm"
                    onClick={handleValidateClick}
                    disabled={saveMutation.isPending || submitMutation.isPending}
                    title="همه فیلدهای الزامی باید تکمیل شوند"
                >
                    <IconExclamationCircle className="size-4" />
                </Button>
            )}
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
                ثبت نهایی
            </Button>
        </>
    );

    return (
        <div className="space-y-6">
            <UnsavedChangesDialog
                isDirty={isDirty}
                isSubmitting={
                    saveMutation.isPending || submitMutation.isPending
                }
            />

            {header ? (
                header(finalSubmitActions)
            ) : (
                <div className="flex flex-wrap items-center justify-between gap-2 rounded-lg border p-4">
                    <div>
                        {!validation.success && (
                            <p className="text-sm text-muted-foreground">
                                همه فیلدهای الزامی باید تکمیل شوند
                            </p>
                        )}
                    </div>
                    <div className="flex items-center gap-2">
                        {finalSubmitActions}
                    </div>
                </div>
            )}

            <SubmitErrors errors={submitErrors} />

            <Tabs
                orientation="vertical"
                value={activeSection}
                onValueChange={handleTabChange}
                className="flex-col gap-4 lg:flex-row lg:gap-6 items-stretch lg:items-start"
            >
                <SectionTabNav
                    tabs={PROFILE_STEPS}
                    value={activeSection}
                    onValueChange={(key) => handleTabChange(key)}
                />

                {PROFILE_STEPS.map((section) => (
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
