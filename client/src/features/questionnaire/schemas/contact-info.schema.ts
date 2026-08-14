import { z } from "zod";

import { email, landline, mobile, mobileOrLandline, postalCode } from "@/lib/field-rules";
import { requiredText, text } from "@/lib/zod-primitives";

export const addressSchema = z.object({
    postal_code: postalCode(),
    province: requiredText("استان الزامی است.", 50),
    city: requiredText("شهر الزامی است.", 50),
    neighborhood: text(100, "حداکثر ۱۰۰ کاراکتر."),
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
    address_neighborhood: text(100, "حداکثر ۱۰۰ کاراکتر."),
    address_address: requiredText("آدرس الزامی است.", 500),
} as const;

/**
 * Default (draft) values for the contact info section, shared by the
 * questionnaire, CV, and employee profile forms.
 */
export function defaultContactInfo() {
    return {
        phone: "",
        emergency_phone: "",
        address: {
            postal_code: "",
            province: "",
            city: "",
            neighborhood: "",
            address: "",
            plaque: "",
            floor: "",
            unit: "",
        },
    };
}

/**
 * Build the contact info section payload from the full form values. The
 * JSONB section is spread first so the top-level email/mobile columns win:
 * the JSONB copy is stale and must never overwrite what the user just typed.
 */
export function toContactInfoPayload(values: {
    email?: string;
    mobile?: string;
    contact_info?: unknown;
}): Record<string, unknown> {
    const contactInfo =
        (values.contact_info as Record<string, unknown> | undefined) ?? {};
    return {
        ...contactInfo,
        email: values.email ?? "",
        mobile: values.mobile ?? "",
    };
}
