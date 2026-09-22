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
/** Least-common-denominator shape of section/wizard form values bags. */
export type SectionFormValues = Record<string, unknown>;

/* oxlint-disable no-explicit-any -- `ReactFormExtendedApi` has 12 required
 * invariant generics and no defaults, so this project-sanctioned escape hatch
 * is the one place `any` is the honest spelling (see docblocks below). New
 * cross-cutting hooks should prefer the structural `FormMetaWriter`. */
export type SectionFormApi = ReactFormExtendedApi<any, any, any, any, any, any, any, any, any, any, any, any>;

/**
 * Minimal structural surface of a TanStack form for cross-cutting hooks that
 * only read the current values and write field meta (`useWizardSubmit`,
 * `useInjectedFieldErrors`).
 *
 * A concrete `ReactFormExtendedApi<ConcreteValues, ...>` instance IS
 * structurally assignable to this alias (unlike the full `SectionFormApi`,
 * whose generic methods are invariant in the form-values parameter), so hooks
 * typed with it accept real forms at call sites without casts and without
 * `any`. Narrow it further instead of widening back to `any`.
 */
export type FormMetaWriter = Pick<SectionFormApi, "setFieldMeta"> & {
    state: { values: unknown };
};
