import { IconAlertTriangle } from "@tabler/icons-react";

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { describeField } from "@/lib/field-labels";
import type { SectionErrorsGroup } from "@/lib/validation-helpers";

type ValidationStep = {
    id: number;
    key: string;
    label: string;
};

type FormValidationSummaryProps = {
    groups: SectionErrorsGroup[];
    docMessages?: string[];
    onNavigateToStep?: (step: number) => void;
    steps?: readonly ValidationStep[];
};

export function FormValidationSummary({
    groups,
    docMessages = [],
    onNavigateToStep,
    steps,
}: FormValidationSummaryProps) {
    const total =
        groups.reduce(
            (acc, group) =>
                acc + group.items.reduce((sum, item) => sum + item.messages.length, 0),
            0,
        ) + docMessages.length;

    if (total === 0) return null;

    return (
        <Card className="border-destructive/40 bg-destructive/5">
            <CardHeader className="flex flex-row items-center justify-between gap-2">
                <CardTitle className="flex items-center gap-2 text-base">
                    <IconAlertTriangle className="size-4 text-destructive" />
                    بررسی اعتبار
                </CardTitle>
                <span className="text-sm font-medium text-destructive">
                    {total} مورد نیاز به اصلاح
                </span>
            </CardHeader>
            <CardContent className="space-y-4">
                {groups.map((group) => (
                    <div key={group.key} className="space-y-1">
                        <div className="flex items-center justify-between">
                            <p className="text-sm font-medium">{group.label}</p>
                            {steps && onNavigateToStep && (
                                <button
                                    type="button"
                                    className="text-xs text-primary hover:underline"
                                    onClick={() => {
                                        const step = steps.find(
                                            (s) => s.key === group.key,
                                        );
                                        if (step) {
                                            onNavigateToStep(step.id);
                                        }
                                    }}
                                >
                                    ویرایش
                                </button>
                            )}
                        </div>
                        <ul className="space-y-1 list-disc ms-5 text-sm text-destructive">
                            {group.items.map((item, i) => (
                                <li key={i} className="text-pretty">
                                    <span className="font-medium">
                                        {describeField(item.fieldName)}:
                                    </span>{" "}
                                    {item.messages.join("، ")}
                                </li>
                            ))}
                        </ul>
                    </div>
                ))}

                {docMessages.length > 0 && (
                    <div className="space-y-1">
                        <div className="flex items-center justify-between">
                            <p className="text-sm font-medium">بارگذاری مدارک</p>
                            {steps && onNavigateToStep && (
                                <button
                                    type="button"
                                    className="text-xs text-primary hover:underline"
                                    onClick={() => {
                                        const step = steps.find(
                                            (s) => s.key === "documents",
                                        );
                                        if (step) {
                                            onNavigateToStep(step.id);
                                        }
                                    }}
                                >
                                    ویرایش
                                </button>
                            )}
                        </div>
                        <ul className="space-y-1 list-disc ms-5 text-sm text-destructive">
                            {docMessages.map((message, i) => (
                                <li key={i}>{message}</li>
                            ))}
                        </ul>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
