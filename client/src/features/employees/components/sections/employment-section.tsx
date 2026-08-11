import { z } from "zod";

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
    FormSelectField,
    FormDatePicker,
    FormTextField,
} from "@/components/shared/form-fields";
import { zodFieldValidators } from "@/lib/validation-helpers";
import {
    employmentLabels,
    statusLabels,
} from "@/features/employees/constants";
import type { EmployeeFormApi } from "@/features/employees/types";

type SectionProps = {
    form: EmployeeFormApi;
};

const dateRegex = /^\d{4}-\d{2}-\d{2}$/;

const personnelCodeValidator = z
    .string()
    .max(50, "کد پرسنلی حداکثر ۵۰ کاراکتر است")
    .or(z.literal(""));

const employmentTypeValidator = z
    .enum(["official", "contractual", "project-based"], {
        message: "نوع استخدام نامعتبر است",
    })
    .or(z.literal(""));

const employmentStatusValidator = z
    .enum(["active", "inactive", "suspended"], {
        message: "وضعیت اشتغال نامعتبر است",
    })
    .or(z.literal(""));

const hireDateValidator = z
    .string()
    .regex(dateRegex, "فرمت تاریخ نامعتبر است (YYYY-MM-DD)")
    .or(z.literal(""));

/**
 * Employee employment section: employment type, hire date and employment status
 * are stored on the employee's real columns (mapped by EmployeeService), so the
 * section payload is submitted as-is and persisted outside the JSONB column.
 */
export function EmploymentSection({ form }: SectionProps) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>اطلاعات شغلی</CardTitle>
            </CardHeader>
            <CardContent className="space-y-6">
                <form.Field
                    name="employment.personnel_code"
                    validators={zodFieldValidators(personnelCodeValidator)}
                >
                    {(field) => (
                        <FormTextField
                            field={field}
                            label="کد پرسنلی"
                            placeholder="کد پرسنلی"
                            dir="ltr"
                        />
                    )}
                </form.Field>

                <form.Field
                    name="employment.employment_type"
                    validators={zodFieldValidators(employmentTypeValidator)}
                >
                    {(field) => (
                        <FormSelectField
                            field={field}
                            label="نوع استخدام"
                            placeholder="انتخاب کنید"
                            options={Object.entries(employmentLabels).map(
                                ([value, label]) => ({ value, label }),
                            )}
                        />
                    )}
                </form.Field>

                <form.Field
                    name="employment.hire_date"
                    validators={zodFieldValidators(hireDateValidator)}
                >
                    {(field) => (
                        <FormDatePicker
                            field={field}
                            label="تاریخ استخدام"
                            placeholder="تاریخ استخدام"
                        />
                    )}
                </form.Field>

                <form.Field
                    name="employment.employment_status"
                    validators={zodFieldValidators(employmentStatusValidator)}
                >
                    {(field) => (
                        <FormSelectField
                            field={field}
                            label="وضعیت اشتغال"
                            placeholder="انتخاب کنید"
                            options={Object.entries(statusLabels).map(
                                ([value, label]) => ({ value, label }),
                            )}
                        />
                    )}
                </form.Field>
            </CardContent>
        </Card>
    );
}
