import { z } from "zod";

export const requiredString = z.string().min(1, "این فیلد الزامی است.");

export const addressSchema = z.object({
    postal_code: z.string().min(1, "کد پستی الزامی است.").max(10, "حداکثر ۱۰ کاراکتر."),
    province: z.string().min(1, "استان الزامی است.").max(50, "حداکثر ۵۰ کاراکتر."),
    city: z.string().min(1, "شهر الزامی است.").max(50, "حداکثر ۵۰ کاراکتر."),
    address: z.string().min(1, "آدرس الزامی است.").max(500, "حداکثر ۵۰۰ کاراکتر."),
    plaque: z.string().max(10, "حداکثر ۱۰ کاراکتر.").optional(),
    floor: z.string().max(10, "حداکثر ۱۰ کاراکتر.").optional(),
    unit: z.string().max(10, "حداکثر ۱۰ کاراکتر.").optional(),
});

export type AddressFormData = z.infer<typeof addressSchema>;

export const contactInfoFieldSchema = z.object({
    phone: requiredString.max(15, "حداکثر ۱۵ کاراکتر."),
    emergency_phone: requiredString.max(15, "حداکثر ۱۵ کاراکتر."),
    address: addressSchema,
});

export type ContactInfoFormData = z.infer<typeof contactInfoFieldSchema>;

/**
 * Per-field schemas for use with TanStack Form validators.
 */
export const fieldSchemas = {
    email: z
        .string()
        .min(1, "ایمیل الزامی است.")
        .email("فرمت ایمیل صحیح نیست.")
        .max(255, "حداکثر ۲۵۵ کاراکتر."),
    mobile: z
        .string()
        .min(1, "شماره موبایل الزامی است.")
        .regex(/^09\d{9}$/, "شماره موبایل باید با 09 شروع شده و ۱۱ رقم باشد."),
    phone: z.string().min(1, "تلفن ثابت الزامی است.").max(15, "حداکثر ۱۵ کاراکتر."),
    emergency_phone: z.string().min(1, "تلفن اضطراری الزامی است.").max(15, "حداکثر ۱۵ کاراکتر."),
    address: z.object({}),
    address_postal_code: z.string().min(1, "کد پستی الزامی است.").max(10, "حداکثر ۱۰ کاراکتر."),
    address_province: z.string().min(1, "استان الزامی است.").max(50, "حداکثر ۵۰ کاراکتر."),
    address_city: z.string().min(1, "شهر الزامی است.").max(50, "حداکثر ۵۰ کاراکتر."),
    address_address: z.string().min(1, "آدرس الزامی است.").max(500, "حداکثر ۵۰۰ کاراکتر."),
} as const;
