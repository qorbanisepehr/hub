import { defaultEmployment } from "@/features/questionnaire/schemas/employment.schema";

/**
 * Default (draft) values for the employee's employment section. The personnel
 * code is a real employee column (NOT NULL, unique), so it lives here on top
 * of the shared questionnaire employment fields.
 */
export function defaultEmployeeEmployment() {
    return {
        ...defaultEmployment(),
        personnel_code: "",
    };
}

/**
 * Build the employment section payload from the full form values. All
 * employment fields are real employee columns, so the section is passed
 * through as-is.
 */
export function toEmploymentPayload(values: {
    employment?: unknown;
}): Record<string, unknown> {
    return (values.employment as Record<string, unknown> | undefined) ?? {};
}
