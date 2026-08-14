import type { Employee, EmployeeFormApi } from "@/features/employees/types";
import { EMPLOYEE_SECTIONS } from "@/features/employees/constants";
import { toPersonalInfoPayload } from "@/features/questionnaire/schemas/personal-info.schema";
import { toContactInfoPayload } from "@/features/questionnaire/schemas/contact-info.schema";
import { toEmploymentPayload } from "@/features/employees/schemas/employment.schema";
import { toSocialInsurancePayload } from "@/features/employees/schemas/social-insurance.schema";
import { PersonalInfoView } from "@/components/shared/section-views/personal-info-view";
import { ContactInfoView } from "@/components/shared/section-views/contact-info-view";
import { EmploymentInfoView } from "@/features/employees/components/views/employment-info-view";
import { SocialInsuranceView } from "@/features/employees/components/views/social-insurance-view";
import { EducationView } from "@/components/shared/section-views/education-view";
import { WorkExperienceView } from "@/components/shared/section-views/work-experience-view";
import { SkillsView } from "@/components/shared/section-views/skills-view";
import { TrainingView } from "@/components/shared/section-views/training-view";
import { AdditionalInfoView } from "@/components/shared/section-views/additional-info-view";
import { SectionEditButton } from "@/components/shared/section-views/section-card";

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

export function EmployeeReviewSection({
    form,
    employee,
    onNavigateToSection,
}: EmployeeReviewSectionProps) {
    const values = form.state.values as Record<string, unknown>;

    const edit = (key: string) => () => onNavigateToSection(key);

    const label = (key: string) =>
        EMPLOYEE_SECTIONS.find((section) => section.key === key)?.label ?? "";

    return (
        <div className="space-y-4">
            <PersonalInfoView
                data={toPersonalInfoPayload(values)}
                title={label("personal_info")}
                action={<SectionEditButton onClick={edit("personal_info")} />}
            />

            <ContactInfoView
                data={toContactInfoPayload(values)}
                title={label("contact_info")}
                action={<SectionEditButton onClick={edit("contact_info")} />}
            />

            <EmploymentInfoView
                data={toEmploymentPayload(values)}
                user={employee.user}
                title={label("employment")}
                action={<SectionEditButton onClick={edit("employment")} />}
            />

            <EducationView
                data={sectionValue(values, "education")}
                title={label("education")}
                action={<SectionEditButton onClick={edit("education")} />}
            />

            <SocialInsuranceView
                employee={employee}
                data={toSocialInsurancePayload(values)}
                title={label("social_insurance")}
                action={
                    <SectionEditButton onClick={edit("social_insurance")} />
                }
            />

            <WorkExperienceView
                data={sectionValue(values, "work_experience")}
                title={label("work_experience")}
                action={
                    <SectionEditButton onClick={edit("work_experience")} />
                }
            />

            <SkillsView
                data={sectionValue(values, "skills")}
                title={label("skills")}
                action={<SectionEditButton onClick={edit("skills")} />}
            />

            <TrainingView
                data={sectionValue(values, "training")}
                title={label("training")}
                action={<SectionEditButton onClick={edit("training")} />}
            />

            <AdditionalInfoView
                data={sectionValue(values, "additional_info")}
                title={label("additional_info")}
                action={
                    <SectionEditButton onClick={edit("additional_info")} />
                }
            />
        </div>
    );
}
