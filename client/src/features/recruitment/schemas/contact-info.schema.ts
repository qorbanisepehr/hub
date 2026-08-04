import { z } from "zod";

import { requiredText, text } from "@/lib/zod-primitives";

export const addressSchema = z.object({
    postal_code: requiredText("کد پستی الزامی است.", 10),
    province: requiredText("استان الزامی است.", 50),
    city: requiredText("شهر الزامی است.", 50),
    address: requiredText("آدرس الزامی است.", 500),
    plaque: text(10, "حداکثر ۱۰ کاراکتر."),
    floor: text(10, "حداکثر ۱۰ کاراکتر."),
    unit: text(10, "حداکثر ۱۰ کاراکتر."),
});

export type AddressFormData = z.infer<typeof addressSchema>;

export const contactInfoFieldSchema = z.object({
    phone: requiredText("تلفن ثابت الزامی است.", 15).refine(
        (v) => /^0\d{10}$/.test(v),
        "فرمت تلفن ثابت صحیح نیست.",
    ),
    emergency_phone: requiredText("تلفن اضطراری الزامی است.", 15).refine(
        (v) => /^(09\d{9}|0\d{10})$/.test(v),
        "شماره تماس اضطراری باید یک شماره موبایل یا تلفن ثابت معتبر باشد.",
    ),
    address: addressSchema,
});

export type ContactInfoFormData = z.infer<typeof contactInfoFieldSchema>;

/**
 * Per-field schemas for use with TanStack Form validators.
 */
export const fieldSchemas = {
    email: requiredText("ایمیل الزامی است.", 255).refine(
        (v) => z.string().email().safeParse(v).success,
        "فرمت ایمیل صحیح نیست.",
    ),
    mobile: requiredText("شماره موبایل الزامی است.", 15).refine(
        (v) => /^09\d{9}$/.test(v),
        "شماره موبایل باید با 09 شروع شده و ۱۱ رقم باشد.",
    ),
    phone: requiredText("تلفن ثابت الزامی است.", 15).refine(
        (v) => /^0\d{10}$/.test(v),
        "فرمت تلفن ثابت صحیح نیست.",
    ),
    emergency_phone: requiredText("تلفن اضطراری الزامی است.", 15).refine(
        (v) => /^(09\d{9}|0\d{10})$/.test(v),
        "شماره تماس اضطراری باید یک شماره موبایل یا تلفن ثابت معتبر باشد.",
    ),
    address: z.object({}),
    address_postal_code: requiredText("کد پستی الزامی است.", 10),
    address_province: requiredText("استان الزامی است.", 50),
    address_city: requiredText("شهر الزامی است.", 50),
    address_address: requiredText("آدرس الزامی است.", 500),
} as const;
