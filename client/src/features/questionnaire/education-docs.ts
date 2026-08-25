import type { RowDocCategory } from "@/features/documents/docs-feedback";

import { DOC_CATEGORY_SLUGS } from "./constants";

/** Categories each education row requires, with display labels. */
export const EDUCATION_ROW_DOC_CATEGORIES: RowDocCategory[] = [
    { slug: DOC_CATEGORY_SLUGS.ACADEMIC_DEGREE, label: "مدرک تحصیلی" },
];

/** Row heading used in feedback messages: «سابقه تحصیلی 1». */
export function educationRowLabel(_row: unknown, index: number): string {
    return `سابقه تحصیلی ${index + 1}`;
}
