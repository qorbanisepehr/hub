import { useMemo } from "react";

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { FormValidationSummary } from "@/components/forms";
import { DocumentViewer } from "@/components/documents";
import { QuestionnaireDocumentPreview } from "@/components/documents";
import type { Employee, EmployeeFormApi } from "@/features/employees/types";
import {
    EMPLOYEE_DOC_REQUIREMENTS,
    EMPLOYEE_DOCUMENTS_TAB,
    EMPLOYEE_SECTION_DOCS,
    EMPLOYEE_SECTIONS,
    EMPLOYEE_VALIDATION_SECTIONS,
} from "@/features/employees/constants";
import { useEmployeeDocuments } from "@/features/employees/hooks/use-employee-documents";
import { useEmployeeSubmitOptions } from "@/features/employees/hooks/use-employee-submit-options";
import { buildSubmitValidator } from "@/features/employees/validation";
import {
    groupFieldErrorsBySection,
    validateDocumentRequirements,
} from "@/lib/validation-helpers";
import { toPersonalInfoPayload } from "@/features/questionnaire/schemas/personal-info.schema";
import { toContactInfoPayload } from "@/features/questionnaire/schemas/contact-info.schema";
import { toEmploymentPayload } from "@/features/employees/schemas/employment.schema";
import { toSocialInsurancePayload } from "@/features/employees/schemas/social-insurance.schema";
import { toDependentsPayload } from "@/features/employees/schemas/dependents.schema";
import type { DependentRow } from "@/features/employees/schemas/dependents.schema";
import { DOC_CATEGORY_SLUGS } from "@/features/questionnaire/constants";
import { useDependentDocsFeedback } from "@/features/employees/hooks/use-dependent-docs-feedback";
import { useRowDocsFeedback } from "@/features/documents/hooks/use-row-docs-feedback";
import {
    educationRowLabel,
    EDUCATION_ROW_DOC_CATEGORIES,
} from "@/features/questionnaire/education-docs";
import { PersonalInfoView } from "@/components/section-views/personal-info-view";
import { ContactInfoView } from "@/components/section-views/contact-info-view";
import { EmploymentInfoView } from "@/features/employees/components/views/employment-info-view";
import { SocialInsuranceView } from "@/features/employees/components/views/social-insurance-view";
import { DependentsView } from "@/features/employees/components/views/dependents-view";
import { DocumentInquiriesView } from "@/features/employees/components/views/document-inquiries-view";
import { ContractsView } from "@/features/employees/components/views/contracts-view";
import { FinancialView } from "@/features/employees/components/views/financial-view";
import { SupplementaryInsuranceView } from "@/features/employees/components/views/supplementary-insurance-view";
import { EducationView } from "@/components/section-views/education-view";
import { WorkExperienceView } from "@/components/section-views/work-experience-view";
import { SkillsView } from "@/components/section-views/skills-view";
import { TrainingView } from "@/components/section-views/training-view";
import { AdditionalInfoView } from "@/components/section-views/additional-info-view";
import { SectionEditButton } from "@/components/section-views/section-card";

type EmployeeReviewSectionProps = {
    form: EmployeeFormApi;
    employee: Employee;
    onNavigateToSection: (key: string) => void;
};

function sectionValue(
    values: Record<string, unknown>,
    key: string,
): Record<string, unknown> {
    return (values[key] as Record<string, unknown> | undefined) ?? {};
}

function sectionLabel(key: string): string {
    return EMPLOYEE_SECTIONS.find((section) => section.key === key)?.label ?? "";
}

export function EmployeeReviewSection({
    form,
    employee,
    onNavigateToSection,
}: EmployeeReviewSectionProps) {
    const values = form.state.values as Record<string, unknown>;

    const { documents, isLoading: documentsLoading, getDocumentsBySlug } =
        useEmployeeDocuments(employee.id);

    const { submitOptions } = useEmployeeSubmitOptions();

    const validateSubmit = useMemo(
        () => buildSubmitValidator(submitOptions),
        [submitOptions],
    );

    const validation = validateSubmit(values);
    const dependentsPayload = toDependentsPayload(values) as {
        dependents?: DependentRow[];
    };
    const { messages: dependentDocMessages } = useDependentDocsFeedback(
        employee.id,
        dependentsPayload.dependents ?? [],
    );
    const educationPayload = sectionValue(values, "education") as {
        education_records?: Record<string, unknown>[];
    };
    const {
        messages: educationDocMessages,
        getMissing: educationMissing,
    } = useRowDocsFeedback(
        {
            entity: "employees",
            uuid: employee.id,
            sectionKey: "education",
            categories: EDUCATION_ROW_DOC_CATEGORIES,
            fieldKeyFor: (index) => `edu-${index}`,
        },
        educationPayload.education_records ?? [],
        { rowLabel: educationRowLabel },
    );
    const docMessages = documentsLoading
        ? []
        : [
              ...validateDocumentRequirements(
                  documents,
                  EMPLOYEE_DOC_REQUIREMENTS,
              ),
              ...dependentDocMessages,
              ...educationDocMessages,
          ];
    const validationGroups = groupFieldErrorsBySection(
        validation.fieldErrors,
        EMPLOYEE_VALIDATION_SECTIONS,
    );

    const reviewSteps = [
        ...EMPLOYEE_SECTIONS.map((section, index) => ({
            id: index,
            key: section.key,
            label: section.label,
        })),
        {
            id: EMPLOYEE_SECTIONS.length,
            key: EMPLOYEE_DOCUMENTS_TAB.key,
            label: EMPLOYEE_DOCUMENTS_TAB.label,
        },
    ];

    function docsFor(key: string) {
        return EMPLOYEE_SECTION_DOCS.find((entry) => entry.key === key)?.slugs.flatMap(
            (slug) => getDocumentsBySlug(slug),
        ) ?? [];
    }

    const hasAnyDoc = documents.length > 0;

    const edit = (key: string) => () => onNavigateToSection(key);



    return (
        <div className="space-y-4">
            <FormValidationSummary
                groups={validationGroups}
                docMessages={docMessages}
                steps={reviewSteps}
                onNavigateToStep={(id) =>
                    onNavigateToSection(reviewSteps[id]?.key ?? "")
                }
            />

            <PersonalInfoView
                data={toPersonalInfoPayload(values)}
                title={sectionLabel("personal_info")}
                action={<SectionEditButton onClick={edit("personal_info")} />}
                extra={
                    <QuestionnaireDocumentPreview
                        documents={docsFor("personal_info")}
                        variant="compact"
                        className="mt-4 pt-4 border-t"
                    />
                }
            />

            <ContactInfoView
                data={toContactInfoPayload(values)}
                title={sectionLabel("contact_info")}
                action={<SectionEditButton onClick={edit("contact_info")} />}
            />

            <EmploymentInfoView
                data={toEmploymentPayload(values)}
                user={employee.user}
                title={sectionLabel("employment")}
                action={<SectionEditButton onClick={edit("employment")} />}
                extra={
                    <QuestionnaireDocumentPreview
                        documents={docsFor("employment")}
                        variant="compact"
                        className="mt-4 pt-4 border-t"
                    />
                }
            />

            <EducationView
                data={sectionValue(values, "education")}
                title={sectionLabel("education")}
                action={<SectionEditButton onClick={edit("education")} />}
                missingFor={educationMissing}
                docsFor={(index) =>
                    getDocumentsBySlug(
                        DOC_CATEGORY_SLUGS.ACADEMIC_DEGREE,
                        `edu-${index}`,
                    )
                }
                extra={
                    <QuestionnaireDocumentPreview
                        documents={docsFor("education")}
                        variant="compact"
                        className="mt-4 pt-4 border-t"
                    />
                }
            />

            <SocialInsuranceView
                employee={employee}
                data={toSocialInsurancePayload(values)}
                title={sectionLabel("social_insurance")}
                action={
                    <SectionEditButton onClick={edit("social_insurance")} />
                }
                extra={
                    <QuestionnaireDocumentPreview
                        documents={docsFor("social_insurance")}
                        variant="compact"
                        className="mt-4 pt-4 border-t"
                    />
                }
            />

            <ContractsView
                employee={employee}
                data={sectionValue(values, "contracts")}
                title={sectionLabel("contracts")}
                action={<SectionEditButton onClick={edit("contracts")} />}
                extra={
                    <QuestionnaireDocumentPreview
                        documents={docsFor("contracts")}
                        variant="compact"
                        className="mt-4 pt-4 border-t"
                    />
                }
            />

            <FinancialView
                employee={employee}
                data={sectionValue(values, "financial")}
                title={sectionLabel("financial")}
                action={<SectionEditButton onClick={edit("financial")} />}
                extra={
                    <QuestionnaireDocumentPreview
                        documents={docsFor("financial")}
                        variant="compact"
                        className="mt-4 pt-4 border-t"
                    />
                }
            />

            <SupplementaryInsuranceView
                employee={employee}
                data={sectionValue(values, "supplementary_insurance")}
                title={sectionLabel("supplementary_insurance")}
                action={
                    <SectionEditButton
                        onClick={edit("supplementary_insurance")}
                    />
                }
                extra={
                    <QuestionnaireDocumentPreview
                        documents={docsFor("supplementary_insurance")}
                        variant="compact"
                        className="mt-4 pt-4 border-t"
                    />
                }
            />

            <DependentsView
                employee={employee}
                data={toDependentsPayload(values)}
                title={sectionLabel("dependents")}
                action={<SectionEditButton onClick={edit("dependents")} />}
            />

            <DocumentInquiriesView
                employee={employee}
                data={sectionValue(values, "document_inquiries")}
                title={sectionLabel("document_inquiries")}
                action={
                    <SectionEditButton onClick={edit("document_inquiries")} />
                }
            />

            <WorkExperienceView
data={sectionValue(values, "work_experience")}
            title={sectionLabel("work_experience")}
            action={
                <SectionEditButton onClick={edit("work_experience")} />
            }
            docsFor={(index) =>
                getDocumentsBySlug(
                    DOC_CATEGORY_SLUGS.EMPLOYMENT_CERTIFICATE,
                    `work-${index}`,
                )
            }
            extra={
                    <QuestionnaireDocumentPreview
                        documents={docsFor("work_experience")}
                        variant="compact"
                        className="mt-4 pt-4 border-t"
                    />
                }
            />

            <SkillsView
                data={sectionValue(values, "skills")}
                title={sectionLabel("skills")}
                action={<SectionEditButton onClick={edit("skills")} />}
                extra={
                    <QuestionnaireDocumentPreview
                        documents={docsFor("skills")}
                        variant="compact"
                        className="mt-4 pt-4 border-t"
                    />
                }
            />

            <TrainingView
                data={sectionValue(values, "training")}
                title={sectionLabel("training")}
                action={<SectionEditButton onClick={edit("training")} />}
                extra={
                    <QuestionnaireDocumentPreview
                        documents={docsFor("training")}
                        variant="compact"
                        className="mt-4 pt-4 border-t"
                    />
                }
            />

            <AdditionalInfoView
                data={sectionValue(values, "additional_info")}
                title={sectionLabel("additional_info")}
                action={
                    <SectionEditButton onClick={edit("additional_info")} />
                }
            />

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
        </div>
    );
}
