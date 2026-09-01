import type { SubmitValidationResult } from "@/lib/submit-validation";

export {
    buildSubmitSchema,
    buildSubmitValidator,
    type SubmitOptions,
    type SubmitFormData,
    buildPersonalInfoSchemas,
    type PersonalInfoOptions,
    type PersonalInfoSchemas,
    contactInfoFieldSchema,
    contactInfoFieldSchemas,
    addressSchema,
    additionalInfoFieldSchema,
    additionalInfoFieldSchemas,
    referenceSchema,
} from "./schemas";

export type { SubmitValidationResult };
