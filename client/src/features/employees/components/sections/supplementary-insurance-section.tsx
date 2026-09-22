import { useSelector } from "@tanstack/react-form";

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Field, FieldLabel } from "@/components/ui/field";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import {
    FormRepeater,
    FormSelectField,
    FormTextarea,
} from "@/components/forms";
import type { TableColumn } from "@/components/forms";
import { useFormOptionsByGroup } from "@/features/form-options/hooks/use-form-options";
import type { EmployeeFormApi } from "@/features/employees/types";
import { toPersianDate } from "@/lib/date-format";

type SectionProps = {
    form: EmployeeFormApi;
    uuid: string;
    /** Called after repeater add/edit/delete to persist the section */
    onPersist?: () => void;
};

type DependentRow = {
    relationship_type?: string;
    first_name?: string;
    last_name?: string;
    birth_date?: string;
};

type InsuranceDependentRow = {
    first_name?: string;
    last_name?: string;
    relationship?: string;
    note?: string;
};

const DEPENDENT_COLUMNS: TableColumn[] = [
    { key: "first_name", label: "نام" },
    { key: "last_name", label: "نام خانوادگی" },
    { key: "relationship", label: "نسبت" },
];

/**
 * Supplementary insurance (بیمه تکمیلی) section. The selected bank account is
 * chosen from the accounts entered in the financial section; each covered
 * person is picked from the dependents section (their identity — first/last
 * name and relationship — is copied into the row) with an optional توضیحات
 * field for extra data. Already-covered dependents stay visible but cannot
 * be picked twice. The supplementary-insurance-form document lives in the
 * standalone documents step.
 */
export function SupplementaryInsuranceSection({
    form,
    onPersist,
}: SectionProps) {
    const accounts = useSelector(
        form.store,
        (state) =>
            ((state.values.financial as Record<string, unknown> | undefined)
                ?.bank_accounts ?? []) as {
                bank_name?: string;
                account_number?: string;
            }[],
    );

    const dependents = useSelector(
        form.store,
        (state) =>
            ((state.values.dependents as Record<string, unknown> | undefined)
                ?.dependents ?? []) as DependentRow[],
    );

    const accountOptions = accounts
        .filter((account) => Boolean(account.account_number))
        .map((account) => ({
            value: String(account.account_number),
            label: `${account.bank_name ?? ""} — ${account.account_number}`.trim(),
        }));

    const { data: relationshipOptions } =
        useFormOptionsByGroup("relationship_type");

    const relationshipLabel = (value: unknown) =>
        relationshipOptions?.find((option) => option.value === value)?.label ??
        String(value ?? "");

    /** One select option per بستگان row. */
    const dependentOptions = dependents
        .map((dependent, index) => {
            const name =
                `${String(dependent.first_name ?? "")} ${String(dependent.last_name ?? "")}`.trim();
            if (name === "") return null;

            return {
                value: String(index),
                label: `${name}${dependent.birth_date ? ` (${toPersianDate(dependent.birth_date)})` : ""}`,
            };
        })
        .filter(
            (option): option is { value: string; label: string } =>
                option !== null,
        );

    /** Match an insurance row back to its dependent select value (empty when unmatched). */
    const rowDependentValue = (row: InsuranceDependentRow): string => {
        const index = dependents.findIndex(
            (dependent) =>
                String(dependent.first_name ?? "") !== "" &&
                String(dependent.first_name ?? "") ===
                    String(row.first_name ?? "") &&
                String(dependent.last_name ?? "") ===
                    String(row.last_name ?? ""),
        );
        return index === -1 ? "" : String(index);
    };

    /** Copy the chosen dependent's identity fields into the insurance row. */
    const pickDependent = (index: number, dependentIndex: string) => {
        const dependent = dependents[Number(dependentIndex)];
        if (!dependent) return;

        form.setFieldValue(
            `supplementary_insurance.insurance_dependents.${index}.first_name`,
            String(dependent.first_name ?? ""),
            { dontUpdateMeta: true },
        );
        form.setFieldValue(
            `supplementary_insurance.insurance_dependents.${index}.last_name`,
            String(dependent.last_name ?? ""),
            { dontUpdateMeta: true },
        );
        form.setFieldValue(
            `supplementary_insurance.insurance_dependents.${index}.relationship`,
            String(dependent.relationship_type ?? ""),
            { dontUpdateMeta: true },
        );
    };

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
                            defaultMode="table"
                            field={field}
                            label="افراد تحت پوشش بیمه تکمیلی"
                            columns={DEPENDENT_COLUMNS}
                            emptyMessage="هنوز فردی تحت پوشش اضافه نشده است."
                            onPersist={onPersist}
                            getSummary={(item) => ({
                                first_name: item.first_name,
                                last_name: item.last_name,
                                relationship: relationshipLabel(
                                    item.relationship,
                                ),
                            })}
                            renderItem={(index) => {
                                const rows = (field.state.value ??
                                    []) as InsuranceDependentRow[];
                                const row = rows[index] ?? {};
                                const selectedValue = rowDependentValue(row);

                                // Every dependent stays visible; ones covered
                                // by OTHER rows are disabled so no duplicates.
                                const coveredElsewhere = new Set(
                                    rows
                                        .flatMap((otherRow, otherIndex) =>
                                            otherIndex === index
                                                ? []
                                                : [rowDependentValue(otherRow)],
                                        )
                                        .filter((value) => value !== ""),
                                );

                                return (
                                    <div className="space-y-4">
                                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                            <Field>
                                                <FieldLabel>
                                                    انتخاب از بستگان
                                                </FieldLabel>
                                                <Select
                                                    value={
                                                        selectedValue || null
                                                    }
                                                    items={dependentOptions.map(
                                                        (option) => ({
                                                            value: option.value,
                                                            label: coveredElsewhere.has(
                                                                option.value,
                                                            )
                                                                ? `${option.label} (قبلاً انتخاب شده)`
                                                                : option.label,
                                                        }),
                                                    )}
                                                    itemToStringLabel={(
                                                        value,
                                                    ) =>
                                                        dependentOptions.find(
                                                            (option) =>
                                                                option.value ===
                                                                value,
                                                        )?.label ?? ""
                                                    }
                                                    onValueChange={(val) =>
                                                        pickDependent(
                                                            index,
                                                            String(val ?? ""),
                                                        )
                                                    }
                                                    disabled={
                                                        dependentOptions.length ===
                                                        0
                                                    }
                                                >
                                                    <SelectTrigger
                                                        className="w-full"
                                                        disabled={
                                                            dependentOptions.length ===
                                                            0
                                                        }
                                                    >
                                                        <SelectValue
                                                            placeholder={
                                                                dependentOptions.length ===
                                                                0
                                                                    ? "ابتدا در بخش بستگان وابسته اضافه کنید"
                                                                    : "انتخاب کنید"
                                                            }
                                                        />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {dependentOptions.map(
                                                            (option) => {
                                                                const disabled =
                                                                    coveredElsewhere.has(
                                                                        option.value,
                                                                    ) &&
                                                                    selectedValue !==
                                                                        option.value;

                                                                return (
                                                                    <SelectItem
                                                                        key={
                                                                            option.value
                                                                        }
                                                                        value={
                                                                            option.value
                                                                        }
                                                                        disabled={
                                                                            disabled
                                                                        }
                                                                    >
                                                                        {
                                                                            option.label
                                                                        }
                                                                        {coveredElsewhere.has(
                                                                            option.value,
                                                                        ) &&
                                                                            selectedValue !==
                                                                                option.value &&
                                                                            " (قبلاً انتخاب شده)"}
                                                                    </SelectItem>
                                                                );
                                                            },
                                                        )}
                                                    </SelectContent>
                                                </Select>
                                            </Field>

                                            <form.Field
                                                name={`supplementary_insurance.insurance_dependents.${index}.note`}
                                            >
                                                {(f) => (
                                                    <FormTextarea
                                                        field={f}
                                                        label="توضیحات"
                                                        rows={2}
                                                    />
                                                )}
                                            </form.Field>
                                        </div>
                                    </div>
                                );
                            }}
                        />
                    )}
                </form.Field>
            </CardContent>
        </Card>
    );
}
