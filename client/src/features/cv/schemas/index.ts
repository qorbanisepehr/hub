import { z } from "zod";

import {
    buildPersonalInfoSchemas,
    type PersonalInfoOptions,
} from "./personal-info.schema";
import { contactInfoFieldSchema } from "./contact-info.schema";
import { additionalInfoFieldSchema } from "./additional-info.schema";
import { educationFieldSchema } from "@/features/recruitment/schemas/education.schema";
import { workExperienceFieldSchema } from "@/features/recruitment/schemas/work-experience.schema";
import { skillsFieldSchema } from "@/features/recruitment/schemas/skills.schema";
import { trainingFieldSchema } from "@/features/recruitment/schemas/training.schema";
import { mobile, optionalEmail } from "@/lib/field-rules";
import {
    zodFieldErrors,
    zodIssueMessage,
    type FieldErrors,
} from "@/lib/validation-helpers";
import { requiredText } from "@/lib/zod-primitives";

export type SubmitOptions = {
    personal_info: PersonalInfoOptions;
};

export function buildSubmitSchema(options: SubmitOptions) {
    const { personalInfoFieldSchema } = buildPersonalInfoSchemas(options.personal_info);

    return z.object({
        first_name: requiredText("نام الزامی است.", 100),
        last_name: requiredText("نام خانوادگی الزامی است.", 100),
        // Email stays optional on a CV, but once filled it must be OTP-verified.
        email: optionalEmail(),
        mobile: mobile(),
        personal_info: personalInfoFieldSchema,
        contact_info: contactInfoFieldSchema,
        education: educationFieldSchema,
        work_experience: workExperienceFieldSchema,
        skills: skillsFieldSchema,
        training: trainingFieldSchema,
        additional_info: additionalInfoFieldSchema,
    });
}

export type SubmitFormData = z.infer<ReturnType<typeof buildSubmitSchema>>;

export type SubmitValidationResult = {
    success: boolean;
    errors: string[];
    fieldErrors: FieldErrors;
};

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

export {
    buildPersonalInfoSchemas,
    type PersonalInfoOptions,
    type PersonalInfoSchemas,
} from "./personal-info.schema";
export {
    contactInfoFieldSchema,
    fieldSchemas as contactInfoFieldSchemas,
    addressSchema,
} from "./contact-info.schema";
export {
    additionalInfoFieldSchema,
    fieldSchemas as additionalInfoFieldSchemas,
    referenceSchema,
} from "./additional-info.schema";
