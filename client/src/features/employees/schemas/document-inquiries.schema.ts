import { z } from "zod";

const inquiryEntrySchema = z.object({
    status: z.string().or(z.literal("")).default(""),
    note: z.string().max(1000).or(z.literal("")).default(""),
});

export const documentInquiriesFieldSchema = z.object({
    inquiries: z.object({
        /** Keyed by the education row index (�edu-{index}� placement). */
        education: z.record(z.string(), inquiryEntrySchema),
        criminal_record: inquiryEntrySchema,
        social_insurance: inquiryEntrySchema,
        sana_verification: inquiryEntrySchema,
    }),
});

export type InquiryEntry = z.infer<typeof inquiryEntrySchema>;
export type DocumentInquiriesFormData = z.infer<
    typeof documentInquiriesFieldSchema
>;

/**
 * Submit-time schema: the section is entirely optional for profile submission
 * (HR may submit before inquiries resolve); provided values must still be
 * structurally valid.
 */
export const documentInquiriesSubmitSchema = documentInquiriesFieldSchema;

/**
 * Status every inquiry node starts from (بدون استعلام) — also used as the
 * select placeholder when a legacy row has no status yet.
 */
export const INQUIRY_STATUS_DEFAULT = "no_inquiry";

/** Default (draft) values for the document inquiries section. */
export function defaultDocumentInquiries() {
    return {
        inquiries: {
            education: {},
            criminal_record: { status: INQUIRY_STATUS_DEFAULT, note: "" },
            social_insurance: { status: INQUIRY_STATUS_DEFAULT, note: "" },
            sana_verification: { status: INQUIRY_STATUS_DEFAULT, note: "" },
        },
    };
}

/**
 * Build the document inquiries section payload from the full form values. The
 * section passes through as-is (no real columns).
 */
export function toDocumentInquiriesPayload(values: {
    document_inquiries?: unknown;
}): Record<string, unknown> {
    return (
        (values.document_inquiries as Record<string, unknown> | undefined) ??
        {}
    );
}
