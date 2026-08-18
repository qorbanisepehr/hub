import type { ReactFormExtendedApi } from "@tanstack/react-form";

/**
 * Shared form API type alias for section component props.
 *
 * `ReactFormExtendedApi` is invariant in its generic parameters, which means
 * a concrete form instance (e.g., `useForm<WizardFormValues>({validators})`)
 * is never assignable to a generic alias of it — even with `any` params.
 * The old workaround was `as never` in every parent form.
 *
 * This alias replaces those per-feature duplicates (CvFormApi,
 * QuestionnaireFormApi, EmployeeFormApi) with a single definition.
 * Parent forms now cast with `form as SectionFormApi` instead of `as never`,
 * which is type-safe for the consumer (sections only use generic methods
 * like getFieldValue/setFieldValue that don't depend on the exact type params).
 */
export type SectionFormApi = ReactFormExtendedApi<any, any, any, any, any, any, any, any, any, any, any, any>;
