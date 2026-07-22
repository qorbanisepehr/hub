import { useState } from "react";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useForm, useStore } from "@tanstack/react-form";
import { toast } from "sonner";
import { IconLoader2, IconChecks, IconArrowRight, IconArrowLeft } from "@tabler/icons-react";

import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { ErrorBanner } from "@/components/shared/error-banner";
import { UnsavedChangesDialog } from "@/components/shared/unsaved-changes-dialog";
import { saveQuestionnaire, verifyQuestionnaire, sendOtp } from "@/features/recruitment/api";
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

    const verifyMutation = useMutation({
        mutationFn: (data: Parameters<typeof verifyQuestionnaire>[1]) =>
            verifyQuestionnaire(questionnaire.uuid, data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ["questionnaire", questionnaire.uuid] });
            toast.success("پرسشنامه با موفقیت ثبت شد.");
        },
        onError: (err) => {
            toast.error(getApiError(err));
        },
    });

    const form = useForm({
        defaultValues: {
            personal_info: questionnaire.personal_info ?? {
                gender: "", blood_group: "", birth_date: "", birth_place: "",
                birth_certificate_number: "", father_name: "", religion: "",
                marital_status: "", dependents_count: null, children_count: null,
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
                languages: [], certificates: [], special_skills: "",
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
            mobile_otp: "",
            email_otp: "",
        },
        onSubmit: async ({ value }) => {
            if (currentStep === 7) {
                verifyMutation.mutate({
                    mobile_otp: value.mobile_otp,
                    email_otp: value.email_otp,
                });
                return;
            }

            const sectionKey = WIZARD_STEPS[currentStep]?.key;
            const sectionData: Record<string, unknown> = {};
            if (sectionKey && sectionKey !== "verify" && sectionKey in value) {
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
        if (sectionKey && sectionKey !== "verify" && sectionKey in form.state.values) {
            sectionData[sectionKey] = form.state.values[sectionKey as keyof typeof form.state.values];
        }
        sectionData.current_step = step;

        await saveMutation.mutateAsync(sectionData);
        setCurrentStep(step);
        form.reset(form.state.values);
    };

    const isVerifyStep = currentStep === 7;

    const sectionComponents = [
        PersonalInfoSection,
        EducationSection,
        WorkExperienceSection,
        SkillsSection,
        TrainingSection,
        AdditionalInfoSection,
        JobRequestSection,
        ReviewSection,
    ];

    const CurrentSection = sectionComponents[currentStep];

    return (
        <div className="space-y-6">
            <UnsavedChangesDialog isDirty={isDirty} />

            {/* Step indicators */}
            <div className="flex flex-wrap gap-2">
                {WIZARD_STEPS.map((step) => (
                    <div
                        key={step.id}
                        className={`flex items-center gap-1 rounded-lg px-3 py-1.5 text-xs font-medium ${
                            step.id === currentStep
                                ? "bg-primary text-primary-foreground"
                                : step.id < currentStep
                                  ? "bg-primary/10 text-primary"
                                  : "bg-muted text-muted-foreground"
                        }`}
                    >
                        {step.id + 1}. {step.label}
                    </div>
                ))}
            </div>

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    form.handleSubmit();
                }}
            >
                {saveMutation.error && (
                    <ErrorBanner message={getApiError(saveMutation.error) ?? "خطای ناشناخته"} />
                )}
                {verifyMutation.error && (
                    <ErrorBanner message={getApiError(verifyMutation.error) ?? "خطای ناشناخته"} />
                )}

                <Card>
                    <CardContent className="pt-6">
                        {CurrentSection && <CurrentSection form={form as never} />}
                    </CardContent>
                </Card>

                {/* Navigation */}
                <div className="flex items-center justify-between mt-6">
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

                        {currentStep < 7 ? (
                            <Button
                                type="button"
                                onClick={() => goToStep(currentStep + 1)}
                                disabled={saveMutation.isPending}
                            >
                                مرحله بعد
                                <IconArrowLeft className="size-4 me-1" />
                            </Button>
                        ) : (
                            <Button
                                type="submit"
                                disabled={verifyMutation.isPending || !form.state.values.mobile_otp || !form.state.values.email_otp}
                            >
                                {verifyMutation.isPending ? (
                                    <IconLoader2 className="size-4 animate-spin" />
                                ) : (
                                    <IconChecks className="size-4" />
                                )}
                                ارسال نهایی
                            </Button>
                        )}
                    </div>
                </div>
            </form>
        </div>
    );
}
