import { z } from "zod";

import { mobile, optionalEmail, optionalLandline, optionalMobileOrLandline } from "@/lib/field-rules";
import { requiredText, text } from "@/lib/zod-primitives";

export const addressSchema = z.object({
    // The CV form collects a slim address (province/city/neighborhood/postal),
    // so this schema mirrors the "simple" AddressForm rather than the full one.
    postal_code: text(10, "حداکثر ۱۰ کاراکتر."),
    province: text(50, "حداکثر ۵۰ کاراکتر."),
    city: text(50, "حداکثر ۵۰ کاراکتر."),
    neighborhood: text(100, "حداکثر ۱۰۰ کاراکتر."),
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
