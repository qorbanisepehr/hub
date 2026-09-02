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
    buildValidateSubmitData,
    type SubmitValidationResult,
} from "@/lib/submit-validation";
import { requiredText } from "@/lib/zod-primitives";
import { socialInsuranceSubmitSchema } from "./schemas/social-insurance.schema";
import { dependentsSubmitSchema } from "./schemas/dependents.schema";
import { documentInquiriesSubmitSchema } from "./schemas/document-inquiries.schema";

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
        document_inquiries: documentInquiriesSubmitSchema,
    });
}

export type SubmitFormData = z.infer<ReturnType<typeof buildSubmitSchema>>;

/**
 * Build a submit validator from fetched options. Pass `undefined` while the
 * options are still loading: the validator then reports no errors (so the
 * profile doesn't flash spurious errors) and callers gate submission on
 * `optionsReady` instead.
 */
export function buildSubmitValidator(
    options: SubmitOptions | undefined,
): (data: unknown) => SubmitValidationResult {
    return buildValidateSubmitData(
        options ? buildSubmitSchema(options) : undefined,
    );
}
