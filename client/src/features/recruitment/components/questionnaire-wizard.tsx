import { useState } from "react";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useForm, useStore } from "@tanstack/react-form";
import { toast } from "sonner";
import { IconLoader2, IconChecks, IconArrowRight, IconArrowLeft } from "@tabler/icons-react";

import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { ErrorBanner } from "@/components/shared/error-banner";
import {
    Stepper,
    StepperNav,
    StepperItem,
    StepperTrigger,
    StepperIndicator,
    StepperTitle,
    StepperDescription,
    StepperPanel,
    StepperContent,
} from "@/components/reui/stepper";
import { saveQuestionnaire } from "@/features/recruitment/api";
import { getApiError } from "@/lib/error-utils";
import { WIZARD_STEPS } from "@/features/recruitment/constants";
import type { Questionnaire } from "@/features/recruitment/types";

import { PersonalInfoSection } from "./sections/personal-info-section";
import { EducationSection } from "./sections/education-section";
import { WorkExperienceSection } from "./sections/work-experience-section";
import { SkillsSection } from "./sections/skills-section";
import { TrainingSection } from "./sections/training-section";
import { AdditionalInfoSection } from "./sections/additional-info-section";
import { JobRequestSection } from "./sections/job-request-section";
import { ReviewSection } from "./sections/review-section";

type QuestionnaireWizardProps = {
    questionnaire: Questionnaire;
};

const SECTION_COMPONENTS = [
    PersonalInfoSection,
    EducationSection,
    WorkExperienceSection,
    SkillsSection,
    TrainingSection,
    AdditionalInfoSection,
    JobRequestSection,
];

export function QuestionnaireWizard({ questionnaire }: QuestionnaireWizardProps) {
    const queryClient = useQueryClient();
    const [currentStep, setCurrentStep] = useState(questionnaire.current_step);

    const saveMutation = useMutation({
        mutationFn: (data: Parameters<typeof saveQuestionnaire>[1]) =>
            saveQuestionnaire(questionnaire.uuid, data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ["questionnaire", questionnaire.uuid] });
        },
        onError: () => {
            toast.error("خطا در ذخیره‌سازی");
        },
    });

    const form = useForm({
        defaultValues: {
            personal_info: questionnaire.personal_info ?? {
                gender: "", blood_group: "", birth_date: "", birth_place: "",
                birth_certificate_number: "", father_name: "", religion: "",
                marital_status: "", first_name_en: "", last_name_en: "",
                dependents_count: null, children_count: null,
                spouse_employment_status: "",
                military_status: { status: "", organization: "", from: "", to: "", reason: "" },
                photo: "", national_id: "", address: "", phone: "", emergency_phone: "",
            },
            education: questionnaire.education ?? {
                education_records: [], is_student: false,
                student_degree: "", student_field: "", student_university: "",
                student_country: "", student_city: "", student_semester: null,
                passed_units: null, remaining_units: null, student_gpa: "",
                study_start: "", expected_graduation: "", thesis_submitted: false,
                student_thesis_title: "", free_days_per_week: null, education_description: "",
            },
            work_experience: questionnaire.work_experience ?? {
                work_experiences: [], achievements: "",
                allow_contact_previous_managers: false, contact_restriction_description: "",
            },
            skills: questionnaire.skills ?? {
                languages: [], certificates: [], special_skills: [],
                software_skills: { specialized: [], general: [] },
            },
            training: questionnaire.training ?? {
                training_courses: [], professional_memberships: "", researches: [],
            },
            additional_info: questionnaire.additional_info ?? {
                has_chronic_disease: false, chronic_disease_description: "",
                company_introduction_method: "", has_major_surgery: false,
                major_surgery_description: "", reason_for_joining: "",
                has_disability: false, disability_description: "",
                can_travel: false, travel_description: "",
                has_criminal_record: false, criminal_record_description: "",
                hobbies: "", references: [], strengths_and_improvements: "",
            },
            job_request: questionnaire.job_request ?? {
                employment_type: "", expected_monthly_salary: null,
                minimum_hours_per_month: null, expected_hourly_salary: null,
                submitted_resume_before: false, interviewed_before: false,
                other_information: "", accept_information: false,
                preferred_workplace: [], job_priority_1: "", job_priority_2: "",
                currently_employed: false, available_start_date: "",
            },
        },
        onSubmit: async ({ value }) => {
            const sectionKey = WIZARD_STEPS[currentStep]?.key;
            const sectionData: Record<string, unknown> = {};
            if (sectionKey && sectionKey !== "summary" && sectionKey in value) {
                sectionData[sectionKey] = value[sectionKey as keyof typeof value];
            }
            sectionData.current_step = currentStep;

            saveMutation.mutate(sectionData);
        },
    });

    const isDirty = useStore(form.store, (s) => s.isDirty);

    const goToStep = async (step: number) => {
        const sectionKey = WIZARD_STEPS[currentStep]?.key;
        const sectionData: Record<string, unknown> = {};
        if (sectionKey && sectionKey !== "summary" && sectionKey in form.state.values) {
            sectionData[sectionKey] = form.state.values[sectionKey as keyof typeof form.state.values];
        }
        sectionData.current_step = step;

        await saveMutation.mutateAsync(sectionData);
        setCurrentStep(step);
        form.reset(form.state.values);
    };

    const handleStepChange = (step: number) => {
        goToStep(step);
    };

    return (
        <div className="space-y-6" dir="rtl">
            <Stepper
                value={currentStep}
                onValueChange={handleStepChange}
            >
                <StepperNav className="mb-4 gap-5">
                    {WIZARD_STEPS.map((step, index) => (
                        <StepperItem key={step.id} index={index} className="relative flex-1 items-start">
                            <StepperTrigger className="flex w-full grow flex-col items-start justify-center gap-3.5">
                                <StepperIndicator className="bg-border data-[state=active]:bg-primary data-[state=completed]:bg-primary h-1 w-full rounded-full" />
                                <div className="flex flex-col items-start text-start">
                                    <StepperTitle className="group-data-[state=inactive]/step:text-muted-foreground text-start font-semibold">
                                        {step.label}
                                    </StepperTitle>
                                    <StepperDescription className="text-start">
                                        {step.description}
                                    </StepperDescription>
                                </div>
                            </StepperTrigger>
                        </StepperItem>
                    ))}
                </StepperNav>

                <StepperPanel>
                    <StepperContent index={0}>
                        <Card>
                            <CardContent className="pt-6">
                                <PersonalInfoSection form={form as never} questionnaire={questionnaire} />
                            </CardContent>
                        </Card>
                    </StepperContent>

                    {SECTION_COMPONENTS.slice(1).map((Section, index) => (
                        <StepperContent key={index + 1} index={index + 1}>
                            <Card>
                                <CardContent className="pt-6">
                                    <Section form={form as never} />
                                </CardContent>
                            </Card>
                        </StepperContent>
                    ))}

                    <StepperContent index={7}>
                        <ReviewSection form={form as never} questionnaire={questionnaire} />
                    </StepperContent>
                </StepperPanel>
            </Stepper>

            {saveMutation.error && (
                <ErrorBanner message={getApiError(saveMutation.error) ?? "خطای ناشناخته"} />
            )}

            {/* Navigation */}
            <div className="flex items-center justify-between">
                <div>
                    {currentStep > 0 && (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => goToStep(currentStep - 1)}
                            disabled={saveMutation.isPending}
                        >
                            <IconArrowRight className="size-4 ms-1" />
                            مرحله قبل
                        </Button>
                    )}
                </div>

                <div className="flex gap-2">
                    {currentStep < 7 && (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => {
                                form.handleSubmit();
                            }}
                            disabled={saveMutation.isPending || !isDirty}
                        >
                            {saveMutation.isPending ? (
                                <IconLoader2 className="size-4 animate-spin" />
                            ) : (
                                <IconChecks className="size-4" />
                            )}
                            ذخیره
                        </Button>
                    )}

                    {currentStep < 7 && (
                        <Button
                            type="button"
                            onClick={() => goToStep(currentStep + 1)}
                            disabled={saveMutation.isPending}
                        >
                            مرحله بعد
                            <IconArrowLeft className="size-4 me-1" />
                        </Button>
                    )}
                </div>
            </div>
        </div>
    );
}
