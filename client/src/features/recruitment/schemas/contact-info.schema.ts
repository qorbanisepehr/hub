import { z } from "zod";

import { email, landline, mobile, mobileOrLandline, postalCode } from "@/lib/field-rules";
import { requiredText, text } from "@/lib/zod-primitives";

export const addressSchema = z.object({
    postal_code: postalCode(),
    province: requiredText("استان الزامی است.", 50),
    city: requiredText("شهر الزامی است.", 50),
    address: requiredText("آدرس الزامی است.", 500),
    plaque: text(10, "حداکثر ۱۰ کاراکتر."),
    floor: text(10, "حداکثر ۱۰ کاراکتر."),
    unit: text(10, "حداکثر ۱۰ کاراکتر."),
});

export type AddressFormData = z.infer<typeof addressSchema>;

export const contactInfoFieldSchema = z.object({
    phone: landline(),
    emergency_phone: mobileOrLandline(),
    address: addressSchema,
});

export type ContactInfoFormData = z.infer<typeof contactInfoFieldSchema>;

/**
 * Per-field schemas for use with TanStack Form validators.
 */
export const fieldSchemas = {
    email: email(),
    mobile: mobile(),
    phone: landline(),
    emergency_phone: mobileOrLandline(),
    address: z.object({}),
    address_postal_code: postalCode(),
    address_province: requiredText("استان الزامی است.", 50),
    address_city: requiredText("شهر الزامی است.", 50),
    address_address: requiredText("آدرس الزامی است.", 500),
} as const;
