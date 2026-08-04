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
import { saveCvSection, submitCv } from "@/features/cv/api";
import { getApiError } from "@/lib/error-utils";
import {
    CV_WIZARD_STEPS,
    CV_VALIDATION_SECTIONS,
    CV_DOC_REQUIREMENTS,
} from "@/features/cv/constants";
import { useCvDocuments } from "@/features/cv/hooks/use-cv-documents";
import { validateSubmitData } from "@/features/cv/validation";
import { useInjectedFieldErrors } from "@/hooks/use-injected-field-errors";
import {
    countSectionFieldErrors,
    scrollToFirstInvalidField,
    validateDocumentRequirements,
} from "@/lib/validation-helpers";
import { cvKeys } from "@/lib/query-keys";
import type { Cv } from "@/features/cv/types";

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

const SECTION_COMPONENTS = [
    PersonalInfoSection,
    ContactInfoSection,
    EducationSection,
    WorkExperienceSection,
    SkillsSection,
    TrainingSection,
    AdditionalInfoSection,
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
    if (!isNaN(step) && step >= 0 && step < CV_WIZARD_STEPS.length) {
        return step;
    }
    return 0;
}

function setStepHash(step: number) {
    window.location.hash = `#${step}`;
}

export function CvWizard({ cv }: CvWizardProps) {
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
        }) => saveCvSection(cv.uuid, section, data),
        onSuccess: () => {
            queryClient.invalidateQueries({
                queryKey: cvKeys.detail(cv.uuid),
            });
            form.reset(form.state.values);
        },
        onError: () => {
            toast.error("خطا در ذخیره‌سازی");
        },
    });

    const submitMutation = useMutation({
        mutationFn: () => submitCv(cv.uuid),
        onSuccess: () => {
            queryClient.invalidateQueries({
                queryKey: cvKeys.detail(cv.uuid),
            });
            toast.success("رزومه با موفقیت ارسال شد.");
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
                setSubmitErrors([getApiError(error) ?? "خطا در ارسال رزومه"]);
            }
        },
    });

    const form = useForm({
        defaultValues: {
            first_name: cv.first_name ?? "",
            last_name: cv.last_name ?? "",
            email: cv.email ?? "",
            mobile: cv.mobile ?? "",
            personal_info: cv.personal_info ?? {
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
                national_id: "",
                birth_place: "",
                birth_certificate_number: "",
            },
            contact_info: cv.contact_info ?? {
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
            education: cv.education ?? {
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
            work_experience: cv.work_experience ?? {
                work_experiences: [],
                achievements: "",
                allow_contact_previous_managers: false,
                contact_restriction_description: "",
            },
            skills: cv.skills ?? {
                languages: [],
                certificates: [],
                special_skills: [],
                software_skills: { specialized: [], general: [] },
            },
            training: cv.training ?? {
                training_courses: [],
                professional_memberships: "",
                researches: [],
            },
            additional_info: cv.additional_info ?? {
                hobbies: "",
                references: [],
                strengths_and_improvements: "",
            },
        },
        onSubmit: async ({ value }) => {
            const sectionKey = CV_WIZARD_STEPS[currentStep]?.key;
            if (
                !sectionKey ||
                sectionKey === "summary" ||
                sectionKey === "documents"
            ) {
                return;
            }
            const data = extractSectionData(value, sectionKey);
            saveMutation.mutate({ section: sectionKey, data });
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

    const { documents, isLoading: documentsLoading } = useCvDocuments(cv.uuid);
    const { inject: injectFieldErrors, clear: clearInjectedErrors } =
        useInjectedFieldErrors(form);

    // Email stays optional on a CV; when filled it must be OTP-verified.
    const emailIsSettled =
        !cv.email || cv.email_verified || form.state.values.email === "";

    const canSubmit =
        validation.success &&
        cv.mobile_verified &&
        emailIsSettled &&
        cv.status === "draft";

    const handleSubmit = () => {
        if (!validation.success) {
            setSubmitErrors(validation.errors);
            toast.error("لطفاً خطاهای زیر را اصلاح کنید.");
            return;
        }
        if (!cv.mobile_verified) {
            setSubmitErrors(["شماره موبایل تأیید نشده است."]);
            return;
        }
        if (cv.email && !cv.email_verified && form.state.values.email !== "") {
            setSubmitErrors(["ایمیل تأیید نشده است."]);
            return;
        }
        setSubmitErrors([]);
        submitMutation.mutate();
    };

    const handleValidateClick = () => {
        const result = validateSubmitData(form.state.values);
        const docErrors = documentsLoading
            ? []
            : validateDocumentRequirements(documents, CV_DOC_REQUIREMENTS);

        if (result.success && docErrors.length === 0) {
            clearInjectedErrors();
            toast.success("همه فیلدهای الزامی تکمیل شده‌اند.");
            return;
        }

        const sectionKey = CV_WIZARD_STEPS[currentStep]?.key;
        const section = CV_VALIDATION_SECTIONS.find((s) => s.key === sectionKey);
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
        if (isDirty) {
            const sectionKey = CV_WIZARD_STEPS[currentStep]?.key;
            if (
                sectionKey &&
                sectionKey !== "summary" &&
                sectionKey !== "documents"
            ) {
                const data = extractSectionData(form.state.values, sectionKey);
                await saveMutation.mutateAsync({
                    section: sectionKey,
                    data,
                });
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
                        <PersonalInfoSection form={form as never} cv={cv} />
                    </StepperContent>

                    <StepperContent index={1}>
                        <ContactInfoSection form={form as never} cv={cv} />
                    </StepperContent>

                    <StepperContent index={2}>
                        <EducationSection
                            form={form as never}
                            uuid={cv.uuid}
                            onPersist={handlePersist}
                        />
                    </StepperContent>

                    <StepperContent index={3}>
                        <WorkExperienceSection
                            form={form as never}
                            uuid={cv.uuid}
                            onPersist={handlePersist}
                        />
                    </StepperContent>

                    <StepperContent index={4}>
                        <SkillsSection
                            form={form as never}
                            uuid={cv.uuid}
                            onPersist={handlePersist}
                        />
                    </StepperContent>

                    <StepperContent index={5}>
                        <TrainingSection
                            form={form as never}
                            uuid={cv.uuid}
                            onPersist={handlePersist}
                        />
                    </StepperContent>

                    <StepperContent index={6}>
                        <AdditionalInfoSection
                            form={form as never}
                            onPersist={handlePersist}
                        />
                    </StepperContent>

                    <StepperContent index={7}>
                        <DocumentsSection uuid={cv.uuid} />
                    </StepperContent>

                    <StepperContent index={8}>
                        <ReviewSection
                            form={form as never}
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
                            <IconArrowLeft className="size-4 me-1" />
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
