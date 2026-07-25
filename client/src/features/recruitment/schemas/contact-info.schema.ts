import { z } from "zod";

export const requiredString = z.string().min(1, "این فیلد الزامی است.");

export const addressSchema = z.object({
    postal_code: requiredString.max(10, "حداکثر ۱۰ کاراکتر."),
    province: requiredString.max(50, "حداکثر ۵۰ کاراکتر."),
    city: requiredString.max(50, "حداکثر ۵۰ کاراکتر."),
    address: requiredString.max(500, "حداکثر ۵۰۰ کاراکتر."),
    plaque: z.string().max(10).optional(),
    floor: z.string().max(10).optional(),
    unit: z.string().max(10).optional(),
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
    phone: requiredString.max(15),
    emergency_phone: requiredString.max(15),
    address: z.object({}),
    address_postal_code: requiredString.max(10),
    address_province: requiredString.max(50),
    address_city: requiredString.max(50),
    address_address: requiredString.max(500),
} as const;
