import { z } from "zod";

export const requiredString = z.string().min(1, "این فیلد الزامی است.");

export const addressSchema = z.object({
    postal_code: z.string().max(10, "حداکثر ۱۰ کاراکتر.").optional(),
    province: z.string().max(50, "حداکثر ۵۰ کاراکتر.").optional(),
    city: z.string().max(50, "حداکثر ۵۰ کاراکتر.").optional(),
    address: z.string().max(500, "حداکثر ۵۰۰ کاراکتر.").optional(),
    plaque: z.string().max(10, "حداکثر ۱۰ کاراکتر.").optional(),
    floor: z.string().max(10, "حداکثر ۱۰ کاراکتر.").optional(),
    unit: z.string().max(10, "حداکثر ۱۰ کاراکتر.").optional(),
});

export type AddressFormData = z.infer<typeof addressSchema>;

export const contactInfoFieldSchema = z.object({
    phone: z
        .string()
        .max(15, "حداکثر ۱۵ کاراکتر.")
        .refine((v) => v === "" || /^0\d{10}$/.test(v), "فرمت تلفن ثابت صحیح نیست.")
        .optional(),
    emergency_phone: z
        .string()
        .max(15, "حداکثر ۱۵ کاراکتر.")
        .refine((v) => v === "" || /^0\d{10}$/.test(v), "فرمت تلفن صحیح نیست.")
        .optional(),
    address: addressSchema.optional(),
});

export type ContactInfoFormData = z.infer<typeof contactInfoFieldSchema>;

function isValidEmail(val: string): boolean {
    return z.string().email().safeParse(val).success;
}

export const fieldSchemas = {
    // Email stays optional on a CV: empty is valid, a non-empty value must be
    // a well-formed email (and later OTP-verified before submit).
    email: z
        .string()
        .max(255, "حداکثر ۲۵۵ کاراکتر.")
        .refine((v) => v.trim() === "" || isValidEmail(v), "فرمت ایمیل صحیح نیست."),
    mobile: z
        .string()
        .min(1, "شماره موبایل الزامی است.")
        .regex(/^09\d{9}$/, "شماره موبایل باید با 09 شروع شده و ۱۱ رقم باشد."),
    phone: z
        .string()
        .max(15, "حداکثر ۱۵ کاراکتر.")
        .refine((v) => v === "" || /^0\d{10}$/.test(v), "فرمت تلفن ثابت صحیح نیست."),
    emergency_phone: z
        .string()
        .max(15, "حداکثر ۱۵ کاراکتر.")
        .refine((v) => v === "" || /^0\d{10}$/.test(v), "فرمت تلفن صحیح نیست."),
    address: z.object({}),
} as const;
