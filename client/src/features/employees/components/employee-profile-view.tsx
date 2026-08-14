import { useState } from "react";

import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { DocumentSection } from "@/features/documents/components/document-section";
import { LinkedUserSection } from "@/features/employees/components/sections/linked-user-section";
import { usePermission } from "@/features/auth/components/permission-guard";
import { PERMISSIONS } from "@/lib/permissions";
import {
    EMPLOYEE_DOCUMENTS_TAB,
    EMPLOYEE_LINKED_USER_TAB,
    EMPLOYEE_SECTION_DOCS,
    EMPLOYEE_SECTIONS,
} from "@/features/employees/constants";
import { useEmployeeDocuments } from "@/features/employees/hooks/use-employee-documents";
import type { Employee } from "@/features/employees/types";
import { QuestionnaireDocumentPreview } from "@/components/shared/questionnaire-document-preview";
import { PersonalInfoView } from "@/components/shared/section-views/personal-info-view";
import { ContactInfoView } from "@/components/shared/section-views/contact-info-view";
import { EducationView } from "@/components/shared/section-views/education-view";
import { WorkExperienceView } from "@/components/shared/section-views/work-experience-view";
import { SkillsView } from "@/components/shared/section-views/skills-view";
import { TrainingView } from "@/components/shared/section-views/training-view";
import { AdditionalInfoView } from "@/components/shared/section-views/additional-info-view";
import { EmploymentInfoView } from "./views/employment-info-view";
import { SocialInsuranceView } from "./views/social-insurance-view";

const DOC_EXTRA_CLASS = "mt-4 pt-4 border-t";

export function EmployeeProfileView({ employee }: EmployeeProfileViewProps) {
    const [activeTab, setActiveTab] = useState<string>(
        EMPLOYEE_SECTIONS[0].key,
    );
    const canManageDocuments = usePermission([
        PERMISSIONS.EMPLOYEE_DOCUMENTS_UPLOAD,
        PERMISSIONS.EMPLOYEE_DOCUMENTS_DELETE,
    ]);
    const { getDocumentsBySlug } = useEmployeeDocuments(employee.id);
    const tabs = [
        ...EMPLOYEE_SECTIONS,
        EMPLOYEE_DOCUMENTS_TAB,
        EMPLOYEE_LINKED_USER_TAB,
    ];

    const sectionData: Record<string, Record<string, unknown>> = {
        personal_info: {
            ...(employee.section_personal ?? {}),
            first_name: employee.first_name ?? "",
            last_name: employee.last_name ?? "",
            id_number: employee.id_number ?? "",
            gender: employee.gender ?? "",
            birth_date: employee.birth_date ?? "",
            marital_status: employee.marital_status ?? "",
        },
        contact_info: {
            ...(employee.section_contact_address ?? {}),
            email: employee.email ?? "",
            mobile: employee.mobile ?? "",
        },
        education: employee.section_education ?? {},
        work_experience: employee.section_work_experience ?? {},
        skills: employee.section_skills ?? {},
        training: employee.section_training ?? {},
        additional_info: employee.section_additional_info ?? {},
    };

    function docsFor(key: string) {
        return EMPLOYEE_SECTION_DOCS.find((entry) => entry.key === key)?.slugs.flatMap(
            (slug) => getDocumentsBySlug(slug),
        ) ?? [];
    }

    const docExtra = (key: string) => (
        <QuestionnaireDocumentPreview
            documents={docsFor(key)}
            variant="compact"
            className={DOC_EXTRA_CLASS}
        />
    );

    const sectionViews: Record<string, () => React.ReactNode> = {
        personal_info: () => (
            <PersonalInfoView
                data={sectionData.personal_info}
                extra={docExtra("personal_info")}
            />
        ),
        contact_info: () => (
            <ContactInfoView data={sectionData.contact_info} />
        ),
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
            <EducationView data={sectionData.education} extra={docExtra("education")} />
        ),
        work_experience: () => (
            <WorkExperienceView
                data={sectionData.work_experience}
                extra={docExtra("work_experience")}
            />
        ),
        social_insurance: () => <SocialInsuranceView employee={employee} />,
        skills: () => (
            <SkillsView data={sectionData.skills} extra={docExtra("skills")} />
        ),
        training: () => (
            <TrainingView data={sectionData.training} extra={docExtra("training")} />
        ),
        additional_info: () => (
            <AdditionalInfoView data={sectionData.additional_info} />
        ),
    };

    const renderTab = (key: string) => {
        if (key === EMPLOYEE_DOCUMENTS_TAB.key) {
            return (
                <DocumentSection
                    documentableType="employee"
                    documentableId={employee.id}
                    showActions={canManageDocuments}
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
