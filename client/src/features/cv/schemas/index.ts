import { z } from "zod";

import { personalInfoFieldSchema } from "./personal-info.schema";
import { contactInfoFieldSchema } from "./contact-info.schema";
import { additionalInfoFieldSchema } from "./additional-info.schema";
import { educationFieldSchema } from "@/features/recruitment/schemas/education.schema";
import { workExperienceFieldSchema } from "@/features/recruitment/schemas/work-experience.schema";
import { skillsFieldSchema } from "@/features/recruitment/schemas/skills.schema";
import { trainingFieldSchema } from "@/features/recruitment/schemas/training.schema";

function isValidEmail(val: string): boolean {
    return z.string().email().safeParse(val).success;
}

export const submitSchema = z.object({
    first_name: z.string().min(1, "نام الزامی است.").max(100),
    last_name: z.string().min(1, "نام خانوادگی الزامی است.").max(100),
    // Email stays optional on a CV, but once filled it must be OTP-verified.
    email: z
        .string()
        .max(255)
        .refine((v) => v.trim() === "" || isValidEmail(v), "فرمت ایمیل نادرست است."),
    mobile: z
        .string()
        .min(1, "شماره موبایل الزامی است.")
        .max(15)
        .regex(/^09\d{9}$/, "شماره موبایل باید با 09 شروع شده و ۱۱ رقم باشد."),
    personal_info: personalInfoFieldSchema,
    contact_info: contactInfoFieldSchema,
    education: educationFieldSchema,
    work_experience: workExperienceFieldSchema,
    skills: skillsFieldSchema,
    training: trainingFieldSchema,
    additional_info: additionalInfoFieldSchema,
});

export type SubmitFormData = z.infer<typeof submitSchema>;

export function validateSubmitData(data: unknown): { success: boolean; errors: string[] } {
    const result = submitSchema.safeParse(data);
    if (result.success) {
        return { success: true, errors: [] };
    }
    const errors = result.error.issues.map((issue) => issue.message);
    return { success: false, errors };
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
