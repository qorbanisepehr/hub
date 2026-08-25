import { z } from "zod";

const inquiryEntrySchema = z.object({
    status: z.string().or(z.literal("")).default(""),
    note: z.string().max(1000).or(z.literal("")).default(""),
});

export const documentInquiriesFieldSchema = z.object({
    inquiries: z.object({
        /** Keyed by the education row index («edu-{index}» placement). */
        education: z.record(z.string(), inquiryEntrySchema),
        criminal_record: inquiryEntrySchema,
        social_insurance: inquiryEntrySchema,
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

/** Default (draft) values for the document inquiries section. */
export function defaultDocumentInquiries() {
    return {
        inquiries: {
            education: {},
            criminal_record: { status: "", note: "" },
            social_insurance: { status: "", note: "" },
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
