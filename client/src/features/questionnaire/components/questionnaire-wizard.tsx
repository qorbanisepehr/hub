import { useEffect, useState, useCallback, useMemo } from "react";
import { useMutation, useQueryClient } from "@tanstack/react-query";
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
import { ErrorBanner } from "@/components/layout";
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
} from "@/features/questionnaire/api";
import { getApiError } from "@/lib/error-utils";
import {
    WIZARD_STEPS,
    QUESTIONNAIRE_VALIDATION_SECTIONS,
    QUESTIONNAIRE_DOC_REQUIREMENTS,
} from "@/features/questionnaire/constants";
import { useQuestionnaireDocuments } from "@/features/questionnaire/hooks/use-questionnaire-documents";
import { useQuestionnaireSubmitOptions } from "@/features/questionnaire/hooks/use-questionnaire-submit-options";
import { buildValidateSubmitData } from "@/features/questionnaire/validation";
import { useInjectedFieldErrors } from "@/hooks/use-injected-field-errors";
import { useSectionForm } from "@/hooks/use-section-form";
import {
    countSectionFieldErrors,
    scrollToFirstInvalidField,
    validateDocumentRequirements,
} from "@/lib/validation-helpers";
import type { Questionnaire } from "@/features/questionnaire/types";
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
import { defaultJobRequest } from "@/features/questionnaire/schemas/job-request.schema";

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
 * Build the wizard's default values from a questionnaire. The server is the
 * source of truth after every section save, so this is also used to reset the
 * form from the save response instead of re-reading possibly stale local state.
 */
function buildDefaultValues(questionnaire: Questionnaire): WizardFormValues {
    return {
        first_name: questionnaire.first_name ?? "",
        last_name: questionnaire.last_name ?? "",
        email: questionnaire.email ?? "",
        mobile: questionnaire.mobile ?? "",
        personal_info: questionnaire.personal_info ?? defaultPersonalInfo(),
        contact_info: questionnaire.contact_info ?? defaultContactInfo(),
        education: questionnaire.education ?? defaultEducation(),
        work_experience:
            questionnaire.work_experience ?? defaultWorkExperience(),
        skills: questionnaire.skills ?? defaultSkills(),
        training: questionnaire.training ?? defaultTraining(),
        additional_info:
            questionnaire.additional_info ?? defaultAdditionalInfo(),
        job_request: questionnaire.job_request ?? defaultJobRequest(),
    };
}

const SECTION_PAYLOAD_BUILDERS: Record<
    string,
    (values: WizardFormValues) => Record<string, unknown>
> = {
    personal_info: toPersonalInfoPayload,
    contact_info: toContactInfoPayload,
};

/**
 * Extract the data payload for a given wizard step key from the full form values.
 */
function extractSectionData(
    values: WizardFormValues,
    sectionKey: string,
): Record<string, unknown> {
    const builder = SECTION_PAYLOAD_BUILDERS[sectionKey];
    if (builder) {
        return builder(values);
    }
    if (sectionKey in values) {
        return (
            (values[sectionKey] as Record<string, unknown> | undefined) ?? {}
        );
    }
    return {};
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

    const { form, saveMutation, persistSection, isDirty, isSectionDirty } = useSectionForm<
        Questionnaire,
        WizardFormValues
    >({
        entity: questionnaire,
        buildDefaultValues,
        extractSectionData,
        saveSection: (section, data) =>
            saveQuestionnaireSection(questionnaire.uuid, section, data),
        detailQueryKey: () => ["questionnaire", questionnaire.uuid],
        sectionTopLevelKeys: {
            personal_info: ["first_name", "last_name"],
            contact_info: ["email", "mobile"],
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

    const { submitOptions, optionsReady } = useQuestionnaireSubmitOptions();

    const validateSubmit = useMemo(
        () => buildValidateSubmitData(submitOptions),
        [submitOptions],
    );

    const handlePersist = useCallback(() => {
        const sectionKey = WIZARD_STEPS[currentStep]?.key;
        if (!sectionKey || sectionKey === "summary" || sectionKey === "documents") {
            return;
        }
        persistSection(sectionKey);
    }, [currentStep, persistSection]);

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

    const validation = validateSubmit(form.state.values);

    const { documents, isLoading: documentsLoading } = useQuestionnaireDocuments(
        questionnaire.uuid,
    );
    const { inject: injectFieldErrors, clear: clearInjectedErrors } =
        useInjectedFieldErrors(form);

    const canSubmit =
        optionsReady &&
        validation.success &&
        questionnaire.email_verified &&
        questionnaire.mobile_verified &&
        questionnaire.status === "draft";

    const handleSubmit = () => {
        if (!optionsReady) return;
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

    const handleValidateClick = () => {
        if (!optionsReady) return;
        const result = validateSubmit(form.state.values);
        const docErrors = documentsLoading
            ? []
            : validateDocumentRequirements(documents, QUESTIONNAIRE_DOC_REQUIREMENTS);

        if (result.success && docErrors.length === 0) {
            clearInjectedErrors();
            toast.success("همه فیلدهای الزامی تکمیل شده‌اند.");
            return;
        }

        const sectionKey = WIZARD_STEPS[currentStep]?.key;
        const section = QUESTIONNAIRE_VALIDATION_SECTIONS.find(
            (s) => s.key === sectionKey,
        );
        if (section) {
            injectFieldErrors(result.fieldErrors, section);
        }

        const currentFieldCount = section
            ? countSectionFieldErrors(result.fieldErrors, section)
            : 0;
        const currentDocCount =
            sectionKey === "documents" ? docErrors.length : 0;
        const currentCount = currentFieldCount + currentDocCount;
        const otherCount = result.errors.length + docErrors.length - currentCount;

        if (currentCount > 0) {
            scrollToFirstInvalidField();
        }

        toast.error("فیلدهای الزامی ناقص هستند", {
            description:
                currentCount > 0
                    ? `${currentCount} خطا در این بخش${otherCount > 0 ? ` و ${otherCount} خطا در سایر بخش‌ها` : ""} (در «خلاصه و تأیید» قابل مشاهده است).`
                    : `${otherCount} خطا در سایر بخش‌ها وجود دارد که در «خلاصه و تأیید» قابل مشاهده است.`,
            duration: 5000,
        });
    };

    const goToStep = async (step: number) => {
        const sectionKey = WIZARD_STEPS[currentStep]?.key;
        if (
            sectionKey &&
            sectionKey !== "summary" &&
            sectionKey !== "documents" &&
            isSectionDirty(sectionKey)
        ) {
            const data = extractSectionData(form.state.values, sectionKey);
            await saveMutation.mutateAsync({ section: sectionKey, data });
        }
        setCurrentStep(step);
        setStepHash(step);
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
                            onClick={handleValidateClick}
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
