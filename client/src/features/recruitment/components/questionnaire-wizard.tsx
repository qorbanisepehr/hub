import { useEffect, useState, useCallback } from "react";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useForm, useStore } from "@tanstack/react-form";
import { isAxiosError } from "axios";
import { toast } from "sonner";
import {
    IconLoader2,
    IconChecks,
    IconArrowRight,
    IconArrowLeft,
    IconSend,
    IconAlertTriangle,
    IconClipboardCheck,
} from "@tabler/icons-react";

import { Button } from "@/components/ui/button";
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
import {
    saveQuestionnaireSection,
    submitQuestionnaire,
} from "@/features/recruitment/api";
import { getApiError } from "@/lib/error-utils";
import { WIZARD_STEPS } from "@/features/recruitment/constants";
import { validateSubmitData } from "@/features/recruitment/validation";
import type { Questionnaire } from "@/features/recruitment/types";

import { PersonalInfoSection } from "./sections/personal-info-section";
import { ContactInfoSection } from "./sections/contact-info-section";
import { EducationSection } from "./sections/education-section";
import { WorkExperienceSection } from "./sections/work-experience-section";
import { SkillsSection } from "./sections/skills-section";
import { TrainingSection } from "./sections/training-section";
import { AdditionalInfoSection } from "./sections/additional-info-section";
import { JobRequestSection } from "./sections/job-request-section";
import { DocumentsSection } from "./sections/documents-section";
import { ReviewSection } from "./sections/review-section";

type QuestionnaireWizardProps = {
    questionnaire: Questionnaire;
};

const SECTION_COMPONENTS = [
    PersonalInfoSection,
    ContactInfoSection,
    EducationSection,
    WorkExperienceSection,
    SkillsSection,
    TrainingSection,
    AdditionalInfoSection,
    JobRequestSection,
];

type WizardFormValues = {
    first_name?: string;
    last_name?: string;
    email?: string;
    mobile?: string;
    personal_info?: unknown;
    contact_info?: unknown;
    [key: string]: unknown;
};

/**
 * Extract the data payload for a given wizard step key from the full form values.
 */
function extractSectionData(
    values: WizardFormValues,
    sectionKey: string,
): Record<string, unknown> {
    switch (sectionKey) {
        case "personal_info": {
            // Spread the JSONB section first so the top-level identity fields
            // win: the JSONB copy of first_name/last_name is stale and must
            // never overwrite what the user just typed.
            const personalInfo =
                (values.personal_info as Record<string, unknown> | undefined) ?? {};
            return {
                ...personalInfo,
                first_name: values.first_name,
                last_name: values.last_name,
            };
        }
        case "contact_info": {
            // Same rule as personal_info: email/mobile live on the real
            // columns, so the JSONB copy must not override the typed values.
            const contactInfo =
                (values.contact_info as Record<string, unknown> | undefined) ?? {};
            return {
                ...contactInfo,
                email: values.email,
                mobile: values.mobile,
            };
        }
        default:
            if (sectionKey in values) {
                return (values[sectionKey] as Record<string, unknown> | undefined) ?? {};
            }
            return {};
    }
}

function getStepFromHash(): number {
    const hash = window.location.hash.replace("#", "");
    const step = parseInt(hash, 10);
    if (!isNaN(step) && step >= 0 && step < WIZARD_STEPS.length) {
        return step;
    }
    return 0;
}

function setStepHash(step: number) {
    window.location.hash = `#${step}`;
}

export function QuestionnaireWizard({
    questionnaire,
}: QuestionnaireWizardProps) {
    const queryClient = useQueryClient();
    const [currentStep, setCurrentStep] = useState(getStepFromHash);
    const [submitErrors, setSubmitErrors] = useState<string[]>([]);

    useEffect(() => {
        const onHashChange = () => {
            const step = getStepFromHash();
            if (step !== currentStep) {
                setCurrentStep(step);
            }
        };
        window.addEventListener("hashchange", onHashChange);
        return () => window.removeEventListener("hashchange", onHashChange);
    }, [currentStep]);

    const saveMutation = useMutation({
        mutationFn: ({
            section,
            data,
        }: {
            section: string;
            data: Record<string, unknown>;
        }) => saveQuestionnaireSection(questionnaire.uuid, section, data),
        onSuccess: () => {
            queryClient.invalidateQueries({
                queryKey: ["questionnaire", questionnaire.uuid],
            });
            form.reset(form.state.values);
        },
        onError: () => {
            toast.error("خطا در ذخیره‌سازی");
        },
    });

    const submitMutation = useMutation({
        mutationFn: () => submitQuestionnaire(questionnaire.uuid),
        onSuccess: () => {
            queryClient.invalidateQueries({
                queryKey: ["questionnaire", questionnaire.uuid],
            });
            toast.success("پرسشنامه با موفقیت ثبت شد.");
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
                setSubmitErrors([getApiError(error) ?? "خطا در ثبت پرسشنامه"]);
            }
        },
    });

    const form = useForm({
        defaultValues: {
            first_name: questionnaire.first_name ?? "",
            last_name: questionnaire.last_name ?? "",
            email: questionnaire.email ?? "",
            mobile: questionnaire.mobile ?? "",
            personal_info: questionnaire.personal_info ?? {
                gender: "",
                blood_group: "",
                birth_date: "",
                birth_place: "",
                birth_certificate_number: "",
                father_name: "",
                religion: "",
                marital_status: "",
                first_name_en: "",
                last_name_en: "",
                dependents_count: null,
                children_count: null,
                spouse_employment_status: "",
                spouse_job: "",
                military_status: {
                    status: "",
                    organization: "",
                    from: "",
                    to: "",
                    reason: "",
                },
                national_id: "",
            },
            contact_info: questionnaire.contact_info ?? {
                phone: "",
                emergency_phone: "",
                address: {
                    postal_code: "",
                    province: "",
                    city: "",
                    address: "",
                    plaque: "",
                    floor: "",
                    unit: "",
                },
            },
            education: questionnaire.education ?? {
                education_records: [],
                is_student: false,
                student_degree: "",
                student_field: "",
                student_university: "",
                student_country: "",
                student_city: "",
                student_semester: null,
                passed_units: null,
                remaining_units: null,
                student_gpa: "",
                study_start: "",
                expected_graduation: "",
                thesis_submitted: false,
                student_thesis_title: "",
                free_days_per_week: null,
                education_description: "",
            },
            work_experience: questionnaire.work_experience ?? {
                work_experiences: [],
                achievements: "",
                allow_contact_previous_managers: false,
                contact_restriction_description: "",
            },
            skills: questionnaire.skills ?? {
                languages: [],
                certificates: [],
                special_skills: [],
                software_skills: { specialized: [], general: [] },
            },
            training: questionnaire.training ?? {
                training_courses: [],
                professional_memberships: "",
                researches: [],
            },
            additional_info: questionnaire.additional_info ?? {
                has_chronic_disease: false,
                chronic_disease_description: "",
                company_introduction_method: "",
                has_major_surgery: false,
                major_surgery_description: "",
                reason_for_joining: "",
                has_disability: false,
                disability_description: "",
                can_travel: false,
                travel_description: "",
                has_criminal_record: false,
                criminal_record_description: "",
                hobbies: "",
                references: [],
                strengths_and_improvements: "",
            },
            job_request: questionnaire.job_request ?? {
                employment_type: "",
                expected_monthly_salary: null,
                minimum_hours_per_month: null,
                expected_hourly_salary: null,
                submitted_resume_before: false,
                interviewed_before: false,
                other_information: "",
                accept_information: false,
                preferred_workplace: [],
                job_priority_1: "",
                job_priority_2: "",
                currently_employed: false,
                available_start_date: "",
            },
        },
        onSubmit: async ({ value }) => {
            const sectionKey = WIZARD_STEPS[currentStep]?.key;
            if (!sectionKey || sectionKey === "summary" || sectionKey === "documents") {
                return;
            }
            const data = extractSectionData(value, sectionKey);
            saveMutation.mutate({ section: sectionKey, data });
        },
    });

    const handlePersist = useCallback(() => {
        const sectionKey = WIZARD_STEPS[currentStep]?.key;
        if (!sectionKey || sectionKey === "summary" || sectionKey === "documents") {
            return;
        }
        const data = extractSectionData(form.state.values, sectionKey);
        saveMutation.mutate({ section: sectionKey, data });
    }, [currentStep, form, saveMutation]);

    const isDirty = useStore(form.store, (s) => s.isDirty);

    useEffect(() => {
        if (isDirty) {
            setSubmitErrors([]);
        }
    }, [isDirty]);

    useEffect(() => {
        if (!isDirty) return;

        const handler = (e: BeforeUnloadEvent) => {
            e.preventDefault();
        };
        window.addEventListener("beforeunload", handler);
        return () => window.removeEventListener("beforeunload", handler);
    }, [isDirty]);

    const validation = validateSubmitData(form.state.values);
    const canSubmit =
        validation.success &&
        questionnaire.email_verified &&
        questionnaire.mobile_verified &&
        questionnaire.status === "draft";

    const handleSubmit = () => {
        if (!validation.success) {
            setSubmitErrors(validation.errors);
            toast.error("لطفاً خطاهای زیر را اصلاح کنید.");
            return;
        }
        if (!questionnaire.email_verified) {
            setSubmitErrors(["ایمیل تأیید نشده است."]);
            return;
        }
        if (!questionnaire.mobile_verified) {
            setSubmitErrors(["شماره موبایل تأیید نشده است."]);
            return;
        }
        setSubmitErrors([]);
        submitMutation.mutate();
    };

    const goToStep = async (step: number) => {
        if (isDirty) {
            const sectionKey = WIZARD_STEPS[currentStep]?.key;
            if (sectionKey && sectionKey !== "summary" && sectionKey !== "documents") {
                const data = extractSectionData(form.state.values, sectionKey);
                await saveMutation.mutateAsync({ section: sectionKey, data });
            }
        }
        setCurrentStep(step);
        setStepHash(step);
        form.reset(form.state.values);
    };

    const handleStepChange = (step: number) => {
        goToStep(step);
    };

    return (
        <div className="space-y-6" dir="rtl">
            <Stepper value={currentStep} onValueChange={handleStepChange}>
                <StepperNav className="mb-4 gap-5">
                    {WIZARD_STEPS.map((step, index) => (
                        <StepperItem
                            key={step.id}
                            index={index}
                            className="relative flex-1 items-start"
                        >
                            <StepperTrigger className="flex w-full grow flex-col items-start justify-center gap-3.5 cursor-pointer">
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
                        <PersonalInfoSection
                            form={form as never}
                            questionnaire={questionnaire}
                            uuid={questionnaire.uuid}
                        />
                    </StepperContent>

                    <StepperContent index={1}>
                        <ContactInfoSection form={form as never} questionnaire={questionnaire} />
                    </StepperContent>

                    <StepperContent index={2}>
                        <EducationSection form={form as never} uuid={questionnaire.uuid} onPersist={handlePersist} />
                    </StepperContent>

                    <StepperContent index={3}>
                        <WorkExperienceSection form={form as never} uuid={questionnaire.uuid} onPersist={handlePersist} />
                    </StepperContent>

                    <StepperContent index={4}>
                        <SkillsSection form={form as never} uuid={questionnaire.uuid} onPersist={handlePersist} />
                    </StepperContent>

                    <StepperContent index={5}>
                        <TrainingSection form={form as never} uuid={questionnaire.uuid} onPersist={handlePersist} />
                    </StepperContent>

                    <StepperContent index={6}>
                        <AdditionalInfoSection form={form as never} onPersist={handlePersist} />
                    </StepperContent>

                    <StepperContent index={7}>
                        <JobRequestSection form={form as never} />
                    </StepperContent>

                    <StepperContent index={8}>
                        <DocumentsSection uuid={questionnaire.uuid} />
                    </StepperContent>

                    <StepperContent index={9}>
                        <ReviewSection
                            form={form as never}
                            questionnaire={questionnaire}
                            onNavigateToStep={goToStep}
                        />
                    </StepperContent>
                </StepperPanel>
            </Stepper>

            {saveMutation.error && (
                <ErrorBanner
                    message={getApiError(saveMutation.error) ?? "خطای ناشناخته"}
                />
            )}
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

            {/* Navigation */}
            <div className="flex items-center justify-between">
                <div>
                    {currentStep > 0 && (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => goToStep(currentStep - 1)}
                            disabled={
                                saveMutation.isPending ||
                                submitMutation.isPending
                            }
                        >
                            <IconArrowRight className="size-4 ms-1" />
                            مرحله قبل
                        </Button>
                    )}
                </div>

                <div className="flex gap-2">
                    {currentStep < WIZARD_STEPS.length - 1 && (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={handlePersist}
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
                            ذخیره
                        </Button>
                    )}

                    {currentStep < WIZARD_STEPS.length - 1 && (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => {
                                const result = validateSubmitData(form.state.values);
                                if (result.success) {
                                    toast.success("همه فیلدهای الزامی تکمیل شده‌اند.");
                                } else {
                                    toast.error("فیلدهای الزامی خالی هستند", {
                                        description: `${result.errors.length} مورد یافت شد. به صفحه «بررسی نهایی» بروید.`,
                                        duration: 5000,
                                    });
                                }
                            }}
                        >
                            <IconClipboardCheck className="size-4" />
                            بررسی اعتبار
                        </Button>
                    )}

                    {currentStep < WIZARD_STEPS.length - 1 && (
                        <Button
                            type="button"
                            onClick={() => goToStep(currentStep + 1)}
                            disabled={
                                saveMutation.isPending ||
                                submitMutation.isPending
                            }
                        >
                            مرحله بعد
                            <IconArrowLeft className="size-4 me-1" />
                        </Button>
                    )}

                    {currentStep === WIZARD_STEPS.length - 1 && (
                        <div className="flex flex-col items-end gap-1">
                            <Button
                                type="button"
                                onClick={handleSubmit}
                                disabled={
                                    submitMutation.isPending || !canSubmit
                                }
                            >
                                {submitMutation.isPending ? (
                                    <IconLoader2 className="size-4 animate-spin" />
                                ) : (
                                    <IconSend className="size-4" />
                                )}
                                ثبت نهایی
                            </Button>
                            {!canSubmit && (
                                <p className="text-xs text-muted-foreground">
                                    {!questionnaire.mobile_verified &&
                                        "موبایل تأیید نشده • "}
                                    {!questionnaire.email_verified &&
                                        "ایمیل تأیید نشده • "}
                                    {!validation.success &&
                                        "همه فیلدهای الزامی باید تکمیل شوند"}
                                </p>
                            )}
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}
