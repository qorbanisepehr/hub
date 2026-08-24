import { useEffect, useState, useCallback, useMemo } from "react";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import {
    IconLoader2,
    IconChecks,
    IconArrowRight,
    IconArrowLeft,
    IconSend,
    IconClipboardCheck,
} from "@tabler/icons-react";

import { Button } from "@/components/ui/button";
import { ErrorBanner } from "@/components/layout";
import { UnsavedChangesDialog } from "@/components/layout";
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
import { useWizardState, SubmitErrors } from "@/components/wizards";
import {
    saveQuestionnaireSection,
    submitQuestionnaire,
} from "@/features/questionnaire/api";
import { getApiError, getSubmitErrors } from "@/lib/error-utils";
import { cleanServerSection } from "@/lib/form-utils";
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
import type { Questionnaire, QuestionnaireFormApi } from "@/features/questionnaire/types";
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
        personal_info: { ...defaultPersonalInfo(), ...cleanServerSection(questionnaire.personal_info as Record<string, unknown>) },
        contact_info: { ...defaultContactInfo(), ...cleanServerSection(questionnaire.contact_info as Record<string, unknown>) },
        education: { ...defaultEducation(), ...cleanServerSection(questionnaire.education as Record<string, unknown>) },
        work_experience:
            { ...defaultWorkExperience(), ...cleanServerSection(questionnaire.work_experience as Record<string, unknown>) },
        skills: { ...defaultSkills(), ...cleanServerSection(questionnaire.skills as Record<string, unknown>) },
        training: { ...defaultTraining(), ...cleanServerSection(questionnaire.training as Record<string, unknown>) },
        additional_info:
            { ...defaultAdditionalInfo(), ...cleanServerSection(questionnaire.additional_info as Record<string, unknown>) },
        job_request: { ...defaultJobRequest(), ...cleanServerSection(questionnaire.job_request as Record<string, unknown>) },
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

export function QuestionnaireWizard({
    questionnaire,
}: QuestionnaireWizardProps) {
    const queryClient = useQueryClient();
    const { currentStep, goToStep: setStep } = useWizardState(WIZARD_STEPS);
    const [submitErrors, setSubmitErrors] = useState<string[]>([]);

    const { form, saveMutation, persistSection, isDirty, isSectionDirty, syncDefaults } = useSectionForm<
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
            setSubmitErrors(getSubmitErrors(error, "خطا در ثبت پرسشنامه"));
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
        setStep(step);
    };

    return (
        <div className="space-y-6" dir="rtl">
            <UnsavedChangesDialog
                isDirty={isDirty}
                isSubmitting={
                    saveMutation.isPending || submitMutation.isPending
                }
            />

            <Stepper value={currentStep} onValueChange={goToStep}>
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
                            form={form as unknown as QuestionnaireFormApi}
                            questionnaire={questionnaire}
                            uuid={questionnaire.uuid}
                            onDefaultsSynced={syncDefaults}
                        />
                    </StepperContent>

                    <StepperContent index={1}>
                        <ContactInfoSection form={form as unknown as QuestionnaireFormApi} questionnaire={questionnaire} />
                    </StepperContent>

                    <StepperContent index={2}>
                        <EducationSection form={form as unknown as QuestionnaireFormApi} uuid={questionnaire.uuid} onPersist={handlePersist} />
                    </StepperContent>

                    <StepperContent index={3}>
                        <WorkExperienceSection form={form as unknown as QuestionnaireFormApi} uuid={questionnaire.uuid} onPersist={handlePersist} />
                    </StepperContent>

                    <StepperContent index={4}>
                        <SkillsSection form={form as unknown as QuestionnaireFormApi} uuid={questionnaire.uuid} onPersist={handlePersist} />
                    </StepperContent>

                    <StepperContent index={5}>
                        <TrainingSection form={form as unknown as QuestionnaireFormApi} uuid={questionnaire.uuid} onPersist={handlePersist} />
                    </StepperContent>

                    <StepperContent index={6}>
                        <AdditionalInfoSection form={form as unknown as QuestionnaireFormApi} onPersist={handlePersist} />
                    </StepperContent>

                    <StepperContent index={7}>
                        <JobRequestSection form={form as unknown as QuestionnaireFormApi} />
                    </StepperContent>

                    <StepperContent index={8}>
                        <DocumentsSection uuid={questionnaire.uuid} />
                    </StepperContent>

                    <StepperContent index={9}>
                        <ReviewSection
                            form={form as unknown as QuestionnaireFormApi}
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
            <SubmitErrors errors={submitErrors} />

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
