import { useEffect, useMemo, useRef } from "react";
import { Tabs, TabsContent } from "@/components/ui/tabs";
import { SectionTabNav } from "@/components/wizards/section-tab-nav";
import { useWizardState } from "@/components/wizards";
import { DocumentSection } from "@/features/documents/components/document-section";
import { LinkedUserSection } from "@/features/employees/components/sections/linked-user-section";
import {
    EMPLOYEE_DOCUMENTS_TAB,
    EMPLOYEE_LINKED_USER_TAB,
    EMPLOYEE_SECTION_DOCS,
    EMPLOYEE_SECTIONS,
} from "@/features/employees/constants";
import { useEmployeeDocuments } from "@/features/employees/hooks/use-employee-documents";
import {
    clearActiveTab,
    setActiveTab,
} from "@/features/employees/profile-view-store";
import type { Employee } from "@/features/employees/types";
import { useRowDocsFeedback } from "@/features/documents/hooks/use-row-docs-feedback";
import {
    educationRowLabel,
    EDUCATION_ROW_DOC_CATEGORIES,
} from "@/features/questionnaire/education-docs";
import { QuestionnaireDocumentPreview } from "@/components/documents";
import { PersonalInfoView } from "@/components/section-views/personal-info-view";
import { ContactInfoView } from "@/components/section-views/contact-info-view";
import { EducationView } from "@/components/section-views/education-view";
import { WorkExperienceView } from "@/components/section-views/work-experience-view";
import { SkillsView } from "@/components/section-views/skills-view";
import { TrainingView } from "@/components/section-views/training-view";
import { AdditionalInfoView } from "@/components/section-views/additional-info-view";
import { EmploymentInfoView } from "./views/employment-info-view";
import { DependentsView } from "./views/dependents-view";
import { DocumentInquiriesView } from "./views/document-inquiries-view";
import { SocialInsuranceView } from "./views/social-insurance-view";
import { ContractsView } from "./views/contracts-view";
import { FinancialView } from "./views/financial-view";
import { SupplementaryInsuranceView } from "./views/supplementary-insurance-view";
import { DOC_CATEGORY_SLUGS } from "@/features/questionnaire/constants";
import { FileThumbnail } from "@/components/ui/file-thumbnail";
import { useDocumentPreview } from "@/hooks/use-document-preview";

const DOC_EXTRA_CLASS = "mt-4 pt-4 border-t";

export function EmployeeProfileView({
    employee,
}: EmployeeProfileViewProps) {
    // Same keyed-hash wizard state as the edit form: the active tab syncs to
    // the URL hash (#contracts, #documents, ...) and survives reload/back.
    const tabs = useMemo(
        () => [
            ...EMPLOYEE_SECTIONS,
            EMPLOYEE_DOCUMENTS_TAB,
            EMPLOYEE_LINKED_USER_TAB,
        ],
        [],
    );
    const { currentKey, goToKey } = useWizardState(tabs);
    const activeTab = currentKey ?? EMPLOYEE_SECTIONS[0].key;

    // The page header's edit button reads the active tab from the store to
    // deep-link the edit form (same tab the user was reading). One-way sync:
    // useWizardState stays the source of truth; the store is the read side.
    // Cleared on unmount so the next employee's page never inherits a
    // previous employee's tab.
    useEffect(() => {
        setActiveTab(activeTab);
        return () => clearActiveTab();
    }, [activeTab]);
    const contentRef = useRef<HTMLDivElement>(null);
    const { getDocumentsBySlug, capabilities } = useEmployeeDocuments(
        employee.id,
    );

    const educationRecords = Array.isArray(
        (employee.section_education ?? {}).education_records,
    )
        ? ((employee.section_education as Record<string, unknown>)
              .education_records as Record<string, unknown>[])
        : [];
    const { getMissing: educationMissing } = useRowDocsFeedback(
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
    const personnelPhoto = getDocumentsBySlug(
        DOC_CATEGORY_SLUGS.PERSONNEL_PHOTO,
    )[0];

    const { openPreview } = useDocumentPreview(
        personnelPhoto ? [personnelPhoto] : [],
    );

    const sectionData: Record<string, Record<string, unknown>> = {
        personal_info: {
            ...employee.section_personal,
            first_name: employee.first_name ?? "",
            last_name: employee.last_name ?? "",
            id_number: employee.id_number ?? "",
            gender: employee.gender ?? "",
            birth_date: employee.birth_date ?? "",
            marital_status: employee.marital_status ?? "",
        },
        contact_info: {
            ...employee.section_contact_address,
            email: employee.email ?? "",
            mobile: employee.mobile ?? "",
        },
        education: employee.section_education ?? {},
        work_experience: employee.section_work_experience ?? {},
        skills: employee.section_skills ?? {},
        training: employee.section_training ?? {},
        additional_info: employee.section_additional_info ?? {},
        dependents: employee.section_dependents ?? {},
        document_inquiries: employee.section_document_inquiries ?? {},
    };

    function docsFor(key: string) {
        return (
            EMPLOYEE_SECTION_DOCS.find(
                (entry) => entry.key === key,
            )?.slugs.flatMap((slug) => getDocumentsBySlug(slug)) ?? []
        );
    }

    const docExtra = (key: string) => (
        <QuestionnaireDocumentPreview
            documents={docsFor(key)}
            variant="compact"
            className={DOC_EXTRA_CLASS}
        />
    );

    // Rebuilt only when the employee snapshot or its documents change — the
    // view factories otherwise re-created on every render.
    const sectionViews = useMemo<Record<string, () => React.ReactNode>>(
        () => ({
            personal_info: () => (
                <PersonalInfoView
                    data={sectionData.personal_info}
                    topRight={
                        <div className="shrink-0">
                            <FileThumbnail
                                file={
                                    personnelPhoto
                                        ? {
                                              name: personnelPhoto.structure_name,
                                              type: personnelPhoto.mime_type,
                                          }
                                        : undefined
                                }
                                previewImageUrl={personnelPhoto?.url}
                                className="w-28 rounded-xl overflow-hidden"
                                previewAspectRatio={3 / 4}
                                onPreview={
                                    personnelPhoto
                                        ? () => openPreview(personnelPhoto)
                                        : undefined
                                }
                            />
                        </div>
                    }
                    extra={docExtra("personal_info")}
                />
            ),
            contact_info: () => <ContactInfoView data={sectionData.contact_info} />,
            employment: () => (
                <EmploymentInfoView
                    data={{
                        personnel_code: employee.personnel_code ?? "",
                        employment_type: employee.employment_type ?? "",
                        hire_date: employee.hire_date ?? "",
                        employment_status: employee.employment_status ?? "",
                    }}
                    user={employee.user}
                    extra={docExtra("employment")}
                />
            ),
            education: () => (
                <EducationView
                    data={sectionData.education}
                    missingFor={educationMissing}
                    docsFor={(index) =>
                        getDocumentsBySlug(
                            DOC_CATEGORY_SLUGS.ACADEMIC_DEGREE,
                            `edu-${index}`,
                        )
                    }
                    extra={docExtra("education")}
                />
            ),
            work_experience: () => (
                <WorkExperienceView
                    data={sectionData.work_experience}
                    docsFor={(index) =>
                        getDocumentsBySlug(
                            DOC_CATEGORY_SLUGS.EMPLOYMENT_CERTIFICATE,
                            `work-${index}`,
                        )
                    }
                    extra={docExtra("work_experience")}
                />
            ),
            social_insurance: () => <SocialInsuranceView employee={employee} />,
            contracts: () => <ContractsView employee={employee} />,
            financial: () => <FinancialView employee={employee} />,
            supplementary_insurance: () => (
                <SupplementaryInsuranceView employee={employee} />
            ),
            skills: () => (
                <SkillsView data={sectionData.skills} extra={docExtra("skills")} />
            ),
            training: () => (
                <TrainingView
                    data={sectionData.training}
                    extra={docExtra("training")}
                />
            ),
            additional_info: () => (
                <AdditionalInfoView data={sectionData.additional_info} />
            ),
            dependents: () => <DependentsView employee={employee} />,
            document_inquiries: () => (
                <DocumentInquiriesView
                    employee={employee}
                    data={sectionData.document_inquiries}
                />
            ),
        }),
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [employee, personnelPhoto, educationMissing],
    );

    const renderTab = (key: string) => {
        if (key === EMPLOYEE_DOCUMENTS_TAB.key) {
            return (
                <DocumentSection
                    documentableType="employee"
                    documentableId={employee.id}
                    showActions={capabilities.upload || capabilities.delete}
                    capabilities={capabilities}
                />
            );
        }
        if (key === EMPLOYEE_LINKED_USER_TAB.key) {
            return <LinkedUserSection employee={employee} />;
        }
        return sectionViews[key]?.() ?? null;
    };

    return (
        <Tabs
            value={activeTab}
            onValueChange={(value) => {
                if (value) goToKey(String(value));
            }}
            orientation="vertical"
            className="flex-col gap-4 lg:flex-row lg:gap-6 items-stretch lg:items-start"
        >
            <SectionTabNav
                tabs={tabs}
                value={activeTab}
                onValueChange={(key) => goToKey(key)}
                contentRef={contentRef}
            />
            <div ref={contentRef} className="min-w-0 flex-1">
                {tabs.map((tab) => (
                    <TabsContent key={tab.key} value={tab.key}>
                        {renderTab(tab.key)}
                    </TabsContent>
                ))}
            </div>
        </Tabs>
    );
}

type EmployeeProfileViewProps = {
    employee: Employee;
};
