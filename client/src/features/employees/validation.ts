import { z } from "zod";

import {
    buildPersonalInfoSchemas,
    type PersonalInfoOptions,
} from "@/features/questionnaire/schemas/personal-info.schema";
import { contactInfoFieldSchema } from "@/features/questionnaire/schemas/contact-info.schema";
import { employmentFieldSchema } from "@/features/questionnaire/schemas/employment.schema";
import { educationFieldSchema } from "@/features/questionnaire/schemas/education.schema";
import { workExperienceFieldSchema } from "@/features/questionnaire/schemas/work-experience.schema";
import { skillsFieldSchema } from "@/features/questionnaire/schemas/skills.schema";
import { trainingFieldSchema } from "@/features/questionnaire/schemas/training.schema";
import { additionalInfoFieldSchema } from "@/features/questionnaire/schemas/additional-info.schema";
import { email, mobile } from "@/lib/field-rules";
import {
    zodFieldErrors,
    zodIssueMessage,
    type FieldErrors,
} from "@/lib/validation-helpers";
import { requiredText } from "@/lib/zod-primitives";
import { socialInsuranceSubmitSchema } from "./schemas/social-insurance.schema";
import { dependentsSubmitSchema } from "./schemas/dependents.schema";

export type SubmitOptions = {
    personal_info: PersonalInfoOptions;
};

// The shared questionnaire schema keeps employment fields draft-optional. The
// personnel code is the one employment field that must survive a completed
// profile (NOT NULL, unique column), so it is required here at submit time.
const employeeEmploymentSchema = employmentFieldSchema.extend({
    personnel_code: requiredText("کد پرسنلی الزامی است.", 50),
});

export function buildSubmitSchema(options: SubmitOptions) {
    const { personalInfoFieldSchema } = buildPersonalInfoSchemas(
        options.personal_info,
    );

    return z.object({
        first_name: requiredText("نام الزامی است.", 100),
        last_name: requiredText("نام خانوادگی الزامی است.", 100),
        // Unlike the CV, the employee's contact info is not OTP-gated, so the
        // email is a plain required field at submit time (structural saves are
        // draft-safe and allow leaving it empty).
        email: email(),
        mobile: mobile(),
        personal_info: personalInfoFieldSchema,
        contact_info: contactInfoFieldSchema,
        employment: employeeEmploymentSchema,
        education: educationFieldSchema,
        work_experience: workExperienceFieldSchema,
        social_insurance: socialInsuranceSubmitSchema,
        skills: skillsFieldSchema,
        training: trainingFieldSchema,
        additional_info: additionalInfoFieldSchema,
        dependents: dependentsSubmitSchema,
    });
}

export type SubmitFormData = z.infer<ReturnType<typeof buildSubmitSchema>>;

export type SubmitValidationResult = {
    success: boolean;
    errors: string[];
    fieldErrors: FieldErrors;
};

/**
 * Build a submit validator from fetched options. Pass `undefined` while the
 * options are still loading: the validator then reports no errors (so the
 * profile doesn't flash spurious errors) and callers gate submission on
 * `optionsReady` instead.
 */
export function buildValidateSubmitData(
    options: SubmitOptions | undefined,
): (data: unknown) => SubmitValidationResult {
    const schema = options ? buildSubmitSchema(options) : undefined;

    return (data) => {
        if (!schema) {
            return { success: false, errors: [], fieldErrors: {} };
        }
        const result = schema.safeParse(data);
        if (result.success) {
            return { success: true, errors: [], fieldErrors: {} };
        }
        return {
            success: false,
            errors: result.error.issues.map((issue) => zodIssueMessage(issue)),
            fieldErrors: zodFieldErrors(result.error),
        };
    };
}
