import { IconAlertTriangle } from "@tabler/icons-react";

type SubmitErrorsProps = {
    errors: string[];
};

export function SubmitErrors({ errors }: SubmitErrorsProps) {
    if (errors.length === 0) return null;

    return (
        <div className="flex items-start gap-3 rounded-lg bg-destructive/10 p-3 text-sm text-destructive">
            <IconAlertTriangle className="mt-0.5 size-4 shrink-0" />
            <div className="flex-1">
                {errors.length === 1 ? (
                    <p>{errors[0]}</p>
                ) : (
                    <ul className="space-y-1 list-disc ms-4">
                        {errors.map((err, i) => (
                            <li key={i}>{err}</li>
                        ))}
                    </ul>
                )}
            </div>
        </div>
    );
}
