import { z } from "zod";

import { personalInfoFieldSchema } from "./personal-info.schema";
import { contactInfoFieldSchema } from "./contact-info.schema";
import { additionalInfoFieldSchema } from "./additional-info.schema";
import { educationFieldSchema } from "@/features/recruitment/schemas/education.schema";
import { workExperienceFieldSchema } from "@/features/recruitment/schemas/work-experience.schema";
import { skillsFieldSchema } from "@/features/recruitment/schemas/skills.schema";
import { trainingFieldSchema } from "@/features/recruitment/schemas/training.schema";
import {
    zodFieldErrors,
    zodIssueMessage,
    type FieldErrors,
} from "@/lib/validation-helpers";
import { requiredText, text } from "@/lib/zod-primitives";

function isValidEmail(val: string): boolean {
    return z.string().email().safeParse(val).success;
}

export const submitSchema = z.object({
    first_name: requiredText("نام الزامی است.", 100),
    last_name: requiredText("نام خانوادگی الزامی است.", 100),
    // Email stays optional on a CV, but once filled it must be OTP-verified.
    email: text(255).refine((v) => v.trim() === "" || isValidEmail(v), "فرمت ایمیل نادرست است."),
    mobile: requiredText("شماره موبایل الزامی است.", 15).refine(
        (v) => /^09\d{9}$/.test(v),
        "شماره موبایل باید با 09 شروع شده و ۱۱ رقم باشد.",
    ),
    personal_info: personalInfoFieldSchema,
    contact_info: contactInfoFieldSchema,
    education: educationFieldSchema,
    work_experience: workExperienceFieldSchema,
    skills: skillsFieldSchema,
    training: trainingFieldSchema,
    additional_info: additionalInfoFieldSchema,
});

export type SubmitFormData = z.infer<typeof submitSchema>;

export function validateSubmitData(
    data: unknown,
): { success: boolean; errors: string[]; fieldErrors: FieldErrors } {
    const result = submitSchema.safeParse(data);
    if (result.success) {
        return { success: true, errors: [], fieldErrors: {} };
    }
    return {
        success: false,
        errors: result.error.issues.map((issue) => zodIssueMessage(issue)),
        fieldErrors: zodFieldErrors(result.error),
    };
}

export {
    personalInfoFieldSchema,
    fieldSchemas as personalInfoFieldSchemas,
    militaryStatusSchema,
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
