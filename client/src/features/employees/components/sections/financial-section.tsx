import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { FormRepeater, FormTextField } from "@/components/forms";
import type { TableColumn } from "@/components/forms";
import type { EmployeeFormApi } from "@/features/employees/types";

type SectionProps = {
    form: EmployeeFormApi;
    uuid: string;
    /** Called after repeater add/edit/delete to persist the section */
    onPersist?: () => void;
};

const ACCOUNT_COLUMNS: TableColumn[] = [
    { key: "bank_name", label: "بانک" },
    { key: "account_number", label: "شماره حساب" },
    { key: "card_number", label: "شماره کارت" },
];

/**
 * Financial (اطلاعات مالی) section. Repeatable bank accounts. The financial
 * documents live in the standalone documents step under the `financial`
 * categories.
 */
export function FinancialSection({ form, uuid, onPersist }: SectionProps) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>اطلاعات مالی</CardTitle>
            </CardHeader>

            <CardContent className="space-y-6">
                <form.Field name="financial.bank_accounts">
                    {(field) => (
                        <FormRepeater
                            defaultMode="table"
                            field={field}
                            label="حساب‌های بانکی"
                            columns={ACCOUNT_COLUMNS}
                            emptyMessage="هنوز حساب بانکی اضافه نشده است."
                            onPersist={onPersist}
                            getSummary={(item) => ({
                                bank_name: item.bank_name,
                                account_number: item.account_number,
                                card_number: item.card_number,
                            })}
                            renderItem={(index) => (
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <form.Field
                                        name={`financial.bank_accounts.${index}.bank_name`}
                                    >
                                        {(f) => (
                                            <FormTextField
                                                field={f}
                                                label="نام بانک"
                                            />
                                        )}
                                    </form.Field>

                                    <form.Field
                                        name={`financial.bank_accounts.${index}.account_number`}
                                    >
                                        {(f) => (
                                            <FormTextField
                                                field={f}
                                                label="شماره حساب"
                                                dir="ltr"
                                            />
                                        )}
                                    </form.Field>

                                    <form.Field
                                        name={`financial.bank_accounts.${index}.card_number`}
                                    >
                                        {(f) => (
                                            <FormTextField
                                                field={f}
                                                label="شماره کارت"
                                                dir="ltr"
                                            />
                                        )}
                                    </form.Field>

                                    <form.Field
                                        name={`financial.bank_accounts.${index}.shaba_number`}
                                    >
                                        {(f) => (
                                            <FormTextField
                                                field={f}
                                                label="شماره شبا"
                                                dir="ltr"
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
