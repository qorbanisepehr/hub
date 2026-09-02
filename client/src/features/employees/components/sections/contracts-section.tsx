import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
    FormDatePicker,
    FormRepeater,
} from "@/components/forms";
import type { EmployeeFormApi } from "@/features/employees/types";

type SectionProps = {
    form: EmployeeFormApi;
    uuid: string;
    /** Called after repeater add/edit/delete to persist the section */
    onPersist?: () => void;
};

/**
 * Contracts (قراردادها) section. Each row holds a contract start/end period.
 * The contract documents themselves live in the standalone documents step
 * under the `contract` category; per-row document placement is deferred.
 */
export function ContractsSection({ form, uuid, onPersist }: SectionProps) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>قراردادها</CardTitle>
            </CardHeader>

            <CardContent className="space-y-6">
                <form.Field name="contracts.contracts">
                    {(field) => (
                        <FormRepeater
                            defaultMode="card"
                            field={field}
                            label="قراردادها"
                            emptyMessage="هنوز دوره‌ای قراردادی اضافه نشده است."
                            onPersist={onPersist}
                            renderItem={(index) => (
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <form.Field
                                        name={`contracts.contracts.${index}.start_date`}
                                    >
                                        {(f) => (
                                            <FormDatePicker
                                                field={f}
                                                label="تاریخ شروع"
                                            />
                                        )}
                                    </form.Field>

                                    <form.Field
                                        name={`contracts.contracts.${index}.end_date`}
                                    >
                                        {(f) => (
                                            <FormDatePicker
                                                field={f}
                                                label="تاریخ پایان"
                                            />
                                        )}
                                    </form.Field>
                                </div>
                            )}
                        />
                    )}
                </form.Field>
            </CardContent>
        </Card>
    );
}