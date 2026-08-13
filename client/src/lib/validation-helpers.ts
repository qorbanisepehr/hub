import type { z } from "zod";

/**
 * Replace zod's English default messages (e.g. "Invalid input: expected
 * string, received null", "Too small: …") with a Persian fallback, while
 * keeping any custom Persian message already attached to the issue.
 */
export function zodIssueMessage(issue: z.ZodIssue): string {
    const message = issue.message;
    if (issue.code === "invalid_type") {
        return "مقدار واردشده برای این فیلد معتبر نیست.";
    }
    if (issue.code === "too_small" || issue.code === "too_big") {
        if (/^Too (small|big):/.test(message)) {
            return "مقدار واردشده معتبر نیست.";
        }
        return message;
    }
    if (issue.code === "invalid_format" || issue.code === "invalid_value") {
        if (/^Invalid/.test(message)) {
            return "فرمت مقدار واردشده صحیح نیست.";
        }
        return message;
    }
    return message;
}

export function zodFieldValidator<T>(schema: z.ZodType<T>) {
    return ({ value }: { value: unknown }) => {
        const result = schema.safeParse(value == null ? "" : value);
        if (result.success) return undefined;
        return {
            message: zodIssueMessage(result.error.issues[0]) ?? "خطای اعتبارسنجی",
        };
    };
}

/** Returns onChange and onBlur validators. Errors only show after the user leaves the field (onBlur). */
export function zodFieldValidators<T>(schema: z.ZodType<T>) {
    return { onChange: zodFieldValidator(schema), onBlur: zodFieldValidator(schema) };
}

export type FieldErrors = Record<string, string[]>;

/** A wizard step that owns a group of form fields, used to group errors by section. */
export type ValidationSection = {
    key: string;
    label: string;
    /** Field prefixes (dot paths) that belong to this section. */
    match: string[];
};

/** Convert a Zod issue path array into the TanStack field name convention (dots + indexes). */
export function zodPathToFieldName(path: readonly (string | number | symbol)[]): string {
    return path.map((segment) => String(segment)).join(".");
}

/** Build a fieldName → messages map from a failed Zod validation result. */
export function zodFieldErrors(error: z.ZodError | null | undefined): FieldErrors {
    if (!error) return {};
    const fieldErrors: FieldErrors = {};
    for (const issue of error.issues) {
        const fieldName = zodPathToFieldName(issue.path);
        (fieldErrors[fieldName] ??= []).push(zodIssueMessage(issue));
    }
    return fieldErrors;
}

export function fieldBelongsToSection(fieldName: string, section: ValidationSection): boolean {
    return section.match.some(
        (prefix) => fieldName === prefix || fieldName.startsWith(`${prefix}.`),
    );
}

export type SectionErrorItem = {
    fieldName: string;
    messages: string[];
};

export type SectionErrorsGroup = {
    key: string;
    label: string;
    items: SectionErrorItem[];
};

/** Collect the errors of a single section, grouped per field, keeping order. */
export function getSectionFieldErrors(
    fieldErrors: FieldErrors,
    section: ValidationSection,
): SectionErrorItem[] {
    const items: SectionErrorItem[] = [];
    for (const [fieldName, messages] of Object.entries(fieldErrors)) {
        if (fieldBelongsToSection(fieldName, section)) {
            items.push({ fieldName, messages: [...messages] });
        }
    }
    return items;
}

/** Count the messages that belong to the given section. */
export function countSectionFieldErrors(fieldErrors: FieldErrors, section: ValidationSection): number {
    return getSectionFieldErrors(fieldErrors, section).reduce(
        (acc, item) => acc + item.messages.length,
        0,
    );
}

/** Group field errors by wizard section, keeping only sections that have messages. */
export function groupFieldErrorsBySection(
    fieldErrors: FieldErrors,
    sections: ValidationSection[],
): SectionErrorsGroup[] {
    return sections
        .map((section) => ({
            key: section.key,
            label: section.label,
            items: getSectionFieldErrors(fieldErrors, section),
        }))
        .filter((group) => group.items.length > 0);
}

export type DocumentRequirement = {
    slug: string;
    label: string;
    required?: boolean;
    max?: number;
};

/**
 * Validate uploaded documents against per-category requirements. Mirrors the
 * backend enforcement (CvService/QuestionnaireService documentRequirements).
 */
export function validateDocumentRequirements(
    documents: Array<{ category: { slug: string } | null }>,
    requirements: DocumentRequirement[],
): string[] {
    const messages: string[] = [];
    for (const requirement of requirements) {
        const count = documents.filter((d) => d.category?.slug === requirement.slug).length;
        if (requirement.required && count === 0) {
            messages.push(`«${requirement.label}» الزامی است و بارگذاری نشده است.`);
        }
        if (requirement.max !== undefined && count > requirement.max) {
            messages.push(`حداکثر ${requirement.max} فایل برای «${requirement.label}» مجاز است.`);
        }
    }
    return messages;
}

/**
 * After injecting section errors, bring the first invalid field into view so
 * the user immediately sees which field needs fixing. Runs after a frame so
 * the injected errors have had time to render as `data-invalid`.
 */
export function scrollToFirstInvalidField(): void {
    window.requestAnimationFrame(() => {
        const wrapper = document.querySelector('[data-invalid="true"]');
        if (!wrapper) return;
        wrapper.scrollIntoView({ behavior: "smooth", block: "center" });
        const control = wrapper.querySelector<HTMLElement>("input, select, textarea");
        control?.focus();
    });
}
