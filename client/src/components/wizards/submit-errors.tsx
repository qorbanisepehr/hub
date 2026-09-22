import { ErrorBanner } from "@/components/layout/error-banner";

/**
 * Back-compat alias: submit-error lists now render through the merged
 * {@link ErrorBanner} (D4). Prefer importing ErrorBanner directly in new code.
 */
export function SubmitErrors({ errors }: { errors: string[] }) {
    return <ErrorBanner errors={errors} />;
}
