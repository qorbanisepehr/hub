import { z } from "zod";

import { requiredText, text } from "@/lib/zod-primitives";

export const addressSchema = z.object({
    postal_code: text(10, "حداکثر ۱۰ کاراکتر."),
    province: text(50, "حداکثر ۵۰ کاراکتر."),
    city: text(50, "حداکثر ۵۰ کاراکتر."),
    address: text(500, "حداکثر ۵۰۰ کاراکتر."),
    plaque: text(10, "حداکثر ۱۰ کاراکتر."),
    floor: text(10, "حداکثر ۱۰ کاراکتر."),
    unit: text(10, "حداکثر ۱۰ کاراکتر."),
});

export type AddressFormData = z.infer<typeof addressSchema>;

export const contactInfoFieldSchema = z.object({
    phone: text(15, "حداکثر ۱۵ کاراکتر.").refine(
        (v) => v === "" || /^0\d{10}$/.test(v),
        "فرمت تلفن ثابت صحیح نیست.",
    ),
    emergency_phone: text(15, "حداکثر ۱۵ کاراکتر.").refine(
        (v) => v === "" || /^(09\d{9}|0\d{10})$/.test(v),
        "شماره تماس اضطراری باید یک شماره موبایل یا تلفن ثابت معتبر باشد.",
    ),
    address: addressSchema.optional(),
});

export type ContactInfoFormData = z.infer<typeof contactInfoFieldSchema>;

function isValidEmail(val: string): boolean {
    return z.string().email().safeParse(val).success;
}

export const fieldSchemas = {
    // Email stays optional on a CV: empty is valid, a non-empty value must be
    // a well-formed email (and later OTP-verified before submit).
    email: text(255, "حداکثر ۲۵۵ کاراکتر.").refine(
        (v) => v.trim() === "" || isValidEmail(v),
        "فرمت ایمیل صحیح نیست.",
    ),
    mobile: requiredText("شماره موبایل الزامی است.", 15).refine(
        (v) => /^09\d{9}$/.test(v),
        "شماره موبایل باید با 09 شروع شده و ۱۱ رقم باشد.",
    ),
    phone: text(15, "حداکثر ۱۵ کاراکتر.").refine(
        (v) => v === "" || /^0\d{10}$/.test(v),
        "فرمت تلفن ثابت صحیح نیست.",
    ),
    emergency_phone: text(15, "حداکثر ۱۵ کاراکتر.").refine(
        (v) => v === "" || /^(09\d{9}|0\d{10})$/.test(v),
        "شماره تماس اضطراری باید یک شماره موبایل یا تلفن ثابت معتبر باشد.",
    ),
    address: z.object({}),
} as const;
