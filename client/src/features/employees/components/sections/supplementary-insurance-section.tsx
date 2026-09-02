import { useSelector } from "@tanstack/react-form";

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
    FormOptionSelectField,
    FormRepeater,
    FormSelectField,
    FormTextField,
} from "@/components/forms";
import type { EmployeeFormApi } from "@/features/employees/types";

type SectionProps = {
    form: EmployeeFormApi;
    uuid: string;
    /** Called after repeater add/edit/delete to persist the section */
    onPersist?: () => void;
};

type BankAccountRow = {
    bank_name?: string;
    account_number?: string;
};

/**
 * Supplementary insurance (بیمه تکمیلی) section. The selected bank account is
 * chosen from the accounts entered in the financial section. The
 * supplementary-insurance-form document lives in the standalone documents step.
 */
export function SupplementaryInsuranceSection({
    form,
    uuid,
    onPersist,
}: SectionProps) {
    const accounts = useSelector(
        form.store,
        (state) =>
            ((state.values.financial as Record<string, unknown> | undefined)
                ?.bank_accounts ?? []) as BankAccountRow[],
    );

    const accountOptions = accounts
        .filter((account) => Boolean(account.account_number))
        .map((account) => ({
            value: String(account.account_number),
            label: `${account.bank_name ?? ""} — ${account.account_number}`.trim(),
        }));

    return (
        <Card>
            <CardHeader>
                <CardTitle>بیمه تکمیلی</CardTitle>
            </CardHeader>

            <CardContent className="space-y-6">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <form.Field name="supplementary_insurance.selected_bank_account">
                        {(field) => (
                            <FormSelectField
                                field={field}
                                label="حساب بانکی بیمه"
                                options={accountOptions}
                                placeholder={
                                    accountOptions.length === 0
                                        ? "ابتدا در بخش اطلاعات مالی حساب بانکی اضافه کنید"
                                        : "انتخاب کنید"
                                }
                                disabled={accountOptions.length === 0}
                            />
                        )}
                    </form.Field>
                </div>

                <form.Field name="supplementary_insurance.insurance_dependents">
                    {(field) => (
                        <FormRepeater
                            defaultMode="card"
                            field={field}
                            label="افراد تحت پوشش بیمه تکمیلی"
                            emptyMessage="هنوز فردی تحت پوشش اضافه نشده است."
                            onPersist={onPersist}
                            renderItem={(index) => (
                                <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                                    <form.Field
                                        name={`supplementary_insurance.insurance_dependents.${index}.first_name`}
                                    >
                                        {(f) => (
                                            <FormTextField
                                                field={f}
                                                label="نام"
                                            />
                                        )}
                                    </form.Field>

                                    <form.Field
                                        name={`supplementary_insurance.insurance_dependents.${index}.last_name`}
                                    >
                                        {(f) => (
                                            <FormTextField
                                                field={f}
                                                label="نام خانوادگی"
                                            />
                                        )}
                                    </form.Field>

                                    <form.Field
                                        name={`supplementary_insurance.insurance_dependents.${index}.relationship`}
                                    >
                                        {(f) => (
                                            <FormOptionSelectField
                                                field={f}
                                                label="نسبت"
                                                group="relationship_type"
                                                placeholder="انتخاب کنید"
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