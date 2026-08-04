import { z } from "zod";

import { mobile, optionalEmail, optionalLandline, optionalMobileOrLandline } from "@/lib/field-rules";
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
    phone: optionalLandline(),
    emergency_phone: optionalMobileOrLandline(),
    address: addressSchema.optional(),
});

export type ContactInfoFormData = z.infer<typeof contactInfoFieldSchema>;

export const fieldSchemas = {
    // Email stays optional on a CV: empty is valid, a non-empty value must be
    // a well-formed email (and later OTP-verified before submit).
    email: optionalEmail(),
    mobile: mobile(),
    phone: optionalLandline(),
    emergency_phone: optionalMobileOrLandline(),
    address: z.object({}),
} as const;
