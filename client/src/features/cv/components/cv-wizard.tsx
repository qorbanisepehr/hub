import { useCallback, useMemo } from "react";
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
import { useWizardState, useWizardSubmit, SubmitErrors } from "@/components/wizards";
import { saveCvSection, submitCv } from "@/features/cv/api";
import { getApiError } from "@/lib/error-utils";
import { cleanServerSection } from "@/lib/form-utils";
import {
    CV_WIZARD_STEPS,
    CV_VALIDATION_SECTIONS,
    CV_DOC_REQUIREMENTS,
} from "@/features/cv/constants";
import { useCvDocuments } from "@/features/cv/hooks/use-cv-documents";
import { useCvSubmitOptions } from "@/features/cv/hooks/use-cv-submit-options";
import { buildSubmitValidator } from "@/features/cv/validation";
import { useSectionForm } from "@/hooks/use-section-form";
import { validateDocumentRequirements } from "@/lib/validation-helpers";
import { cvKeys } from "@/lib/query-keys";
import type { Cv, CvFormApi } from "@/features/cv/types";

import { PersonalInfoSection } from "./sections/personal-info-section";
import { ContactInfoSection } from "./sections/contact-info-section";
import { EducationSection } from "./sections/education-section";
import { WorkExperienceSection } from "./sections/work-experience-section";
import { SkillsSection } from "./sections/skills-section";
import { TrainingSection } from "./sections/training-section";
import { AdditionalInfoSection } from "./sections/additional-info-section";
import { DocumentsSection } from "./sections/documents-section";
import { ReviewSection } from "./sections/review-section";

type CvWizardProps = {
    cv: Cv;
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
 * Build the wizard's default values from a CV. The server is the source of
 * truth after every section save, so this is also used to reset the form
 * from the save response instead of re-reading possibly stale local state.
 */
function buildDefaultValues(cv: Cv): WizardFormValues {
    return {
        first_name: cv.first_name ?? "",
        last_name: cv.last_name ?? "",
        email: cv.email ?? "",
        mobile: cv.mobile ?? "",
        personal_info: {
            gender: "",
            birth_date: "",
            marital_status: "",
            military_status: {
                status: "",
                organization: "",
                from: "",
                to: "",
                reason: "",
            },
            id_number: "",
            birth_place: "",
            birth_certificate_number: "",
            ...cleanServerSection(cv.personal_info),
        },
        contact_info: {
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
                neighborhood: "",
            },
            ...cleanServerSection(cv.contact_info),
        },
        education: {
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
            ...cleanServerSection(cv.education),
        },
        work_experience: {
            work_experiences: [],
            achievements: "",
            allow_contact_previous_managers: false,
            contact_restriction_description: "",
            ...cleanServerSection(cv.work_experience),
        },
        skills: {
            languages: [],
            certificates: [],
            special_skills: [],
            software_skills: { specialized: [], general: [] },
            ...cleanServerSection(cv.skills),
        },
        training: {
            training_courses: [],
            professional_memberships: "",
            researches: [],
            ...cleanServerSection(cv.training),
        },
        additional_info: {
            hobbies: "",
            references: [],
            strengths_and_improvements: "",
            ...cleanServerSection(cv.additional_info),
        },
    };
}

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

export function CvWizard({ cv }: CvWizardProps) {
    const { currentStep, goToStep: setStep } = useWizardState(CV_WIZARD_STEPS);

    const { form, saveMutation, persistSection, isDirty, isSectionDirty, syncDefaults } = useSectionForm<
        Cv,
        WizardFormValues
    >({
        entity: cv,
        buildDefaultValues,
        extractSectionData,
        saveSection: (section, data) => saveCvSection(cv.uuid, section, data),
        detailQueryKey: () => cvKeys.detail(cv.uuid),
        sectionTopLevelKeys: {
            personal_info: ["first_name", "last_name"],
            contact_info: ["email", "mobile"],
        },
    });

    const handlePersist = useCallback(() => {
        const sectionKey = CV_WIZARD_STEPS[currentStep]?.key;
        if (
            !sectionKey ||
            sectionKey === "summary" ||
            sectionKey === "documents"
        ) {
            return;
        }
        persistSection(sectionKey);
    }, [currentStep, persistSection]);

    const { submitOptions, optionsReady } = useCvSubmitOptions();

    const validateSubmit = useMemo(
        () => buildSubmitValidator(submitOptions),
        [submitOptions],
    );

    const validation = validateSubmit(form.state.values);

    const { documents, isLoading: documentsLoading } = useCvDocuments(cv.uuid);

    // Email stays optional on a CV; when filled it must be OTP-verified.
    const emailIsSettled =
        !cv.email || cv.email_verified || form.state.values.email === "";

    const { submitErrors, submitMutation, handleSubmit, handleValidateClick } =
        useWizardSubmit({
            form,
            isDirty,
            optionsReady,
            validateSubmit,
            getCurrentSectionKey: () => CV_WIZARD_STEPS[currentStep]?.key ?? "",
            validationSections: CV_VALIDATION_SECTIONS,
            guards: [
                {
                    errors: () =>
                        cv.mobile_verified
                            ? []
                            : ["شماره موبایل تأیید نشده است."],
                },
                {
                    errors: () =>
                        cv.email &&
                        !cv.email_verified &&
                        form.state.values.email !== ""
                            ? ["ایمیل تأیید نشده است."]
                            : [],
                },
            ],
            getDocumentErrors: () =>
                documentsLoading
                    ? []
                    : validateDocumentRequirements(
                          documents,
                          CV_DOC_REQUIREMENTS,
                      ),
            reviewStepLabel: "خلاصه و تأیید",
            submit: {
                submitFn: () => submitCv(cv.uuid),
                detailQueryKey: () => cvKeys.detail(cv.uuid),
                successMessage: "رزومه با موفقیت ارسال شد.",
                errorFallback: "خطا در ارسال رزومه",
            },
        });

    const canSubmit =
        optionsReady &&
        validation.success &&
        cv.mobile_verified &&
        emailIsSettled &&
        (cv.status === "draft" || cv.status === "rejected");

    const goToStep = async (step: number) => {
        const sectionKey = CV_WIZARD_STEPS[currentStep]?.key;
        if (
            sectionKey &&
            sectionKey !== "summary" &&
            sectionKey !== "documents" &&
            isSectionDirty(sectionKey)
        ) {
            const data = extractSectionData(form.state.values, sectionKey);
            await saveMutation.mutateAsync({
                section: sectionKey,
                data,
            });
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
                    {CV_WIZARD_STEPS.map((step, index) => (
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
                            form={form as unknown as CvFormApi}
                            cv={cv}
                            uuid={cv.uuid}
                            onDefaultsSynced={syncDefaults}
                        />
                    </StepperContent>

                    <StepperContent index={1}>
                        <ContactInfoSection form={form as unknown as CvFormApi} cv={cv} />
                    </StepperContent>

                    <StepperContent index={2}>
                        <EducationSection
                            form={form as unknown as CvFormApi}
                            uuid={cv.uuid}
                            onPersist={handlePersist}
                        />
                    </StepperContent>

                    <StepperContent index={3}>
                        <WorkExperienceSection
                            form={form as unknown as CvFormApi}
                            uuid={cv.uuid}
                            onPersist={handlePersist}
                        />
                    </StepperContent>

                    <StepperContent index={4}>
                        <SkillsSection
                            form={form as unknown as CvFormApi}
                            uuid={cv.uuid}
                            onPersist={handlePersist}
                        />
                    </StepperContent>

                    <StepperContent index={5}>
                        <TrainingSection
                            form={form as unknown as CvFormApi}
                            uuid={cv.uuid}
                            onPersist={handlePersist}
                        />
                    </StepperContent>

                    <StepperContent index={6}>
                        <AdditionalInfoSection
                            form={form as unknown as CvFormApi}
                            onPersist={handlePersist}
                        />
                    </StepperContent>

                    <StepperContent index={7}>
                        <DocumentsSection uuid={cv.uuid} />
                    </StepperContent>

                    <StepperContent index={8}>
                        <ReviewSection
                            form={form as unknown as CvFormApi}
                            cv={cv}
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
                            <IconArrowRight className="size-4 ms-1 ltr:-scale-x-100" />
                            مرحله قبل
                        </Button>
                    )}
                </div>

                <div className="flex gap-2">
                    {currentStep < CV_WIZARD_STEPS.length - 1 && (
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

                    {currentStep < CV_WIZARD_STEPS.length - 1 && (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={handleValidateClick}
                        >
                            <IconClipboardCheck className="size-4" />
                            بررسی اعتبار
                        </Button>
                    )}

                    {currentStep < CV_WIZARD_STEPS.length - 1 && (
                        <Button
                            type="button"
                            onClick={() => goToStep(currentStep + 1)}
                            disabled={
                                saveMutation.isPending ||
                                submitMutation.isPending
                            }
                        >
                            مرحله بعد
                            <IconArrowLeft className="size-4 me-1 ltr:-scale-x-100" />
                        </Button>
                    )}

                    {currentStep === CV_WIZARD_STEPS.length - 1 && (
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
                                    {!cv.mobile_verified &&
                                        "موبایل تأیید نشده • "}
                                    {cv.email &&
                                        !cv.email_verified &&
                                        form.state.values.email !== "" &&
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
