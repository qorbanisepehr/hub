import { useForm, useStore } from "@tanstack/react-form";
import { z } from "zod";
import { IconChecks, IconLoader2 } from "@tabler/icons-react";

import { Button } from "@/components/ui/button";
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    CardDescription,
} from "@/components/ui/card";
import { Field, FieldError, FieldLabel } from "@/components/ui/field";
import { DatePicker } from "@/components/ui/date-picker";
import {
    employmentLabels,
    statusLabels,
} from "@/features/employees/constants";
import { Link } from "@tanstack/react-router";
import { FormTextField, FormSelectField } from "@/components/shared/form-fields";
import { ErrorBanner } from "@/components/shared/error-banner";
import { UnsavedChangesDialog } from "@/components/shared/unsaved-changes-dialog";
import { UserSearchSelect } from "@/features/rbac/components/user-search-select";
import { useFormOptionsByGroup } from "@/features/form-options/hooks/use-form-options";

const dateRegex = /^\d{4}-\d{2}-\d{2}$/;

const employeeSchema = z.object({
    personnel_code: z
        .string()
        .trim()
        .min(1, "کد پرسنلی الزامی است")
        .max(50, "حداکثر ۵۰ کاراکتر"),
    first_name: z
        .string()
        .trim()
        .min(1, "نام الزامی است")
        .max(255, "حداکثر ۲۵۵ کاراکتر"),
    last_name: z
        .string()
        .trim()
        .min(1, "نام خانوادگی الزامی است")
        .max(255, "حداکثر ۲۵۵ کاراکتر"),
    gender: z.string().trim().min(1, "جنسیت الزامی است"),
    employment_type: z
        .enum(["official", "contractual", "project-based"], {
            message: "نوع استخدام نامعتبر است",
        })
        .or(z.literal("")),
    hire_date: z
        .string()
        .regex(dateRegex, "فرمت تاریخ نامعتبر است (YYYY-MM-DD)")
        .or(z.literal("")),
    employment_status: z
        .enum(["active", "inactive", "suspended"], {
            message: "وضعیت اشتغال نامعتبر است",
        })
        .or(z.literal("")),
    user_id: z.number().nullable().or(z.undefined()),
});

type EmployeeFormValues = z.infer<typeof employeeSchema>;

type Props = {
    defaultValues?: Partial<EmployeeFormValues>;
    onSubmit: (values: EmployeeFormValues) => void;
    isPending?: boolean;
    error?: string | null;
    submitLabel?: string;
};

export function EmployeeForm({
    defaultValues,
    onSubmit,
    isPending,
    error,
    submitLabel = "ثبت کارمند",
}: Props) {
    const genderOptions = useFormOptionsByGroup("gender");
    const genderSelectOptions = (genderOptions.data ?? []).map((option) => ({
        value: option.label,
        label: option.label,
    }));

    const form = useForm({
        defaultValues: {
            personnel_code: "",
            first_name: "",
            last_name: "",
            gender: "",
            employment_type: "",
            hire_date: "",
            employment_status: "",
            user_id: null,
            ...defaultValues,
        } as EmployeeFormValues,
        validators: {
            onSubmit: employeeSchema,
        },
        onSubmit: async ({ value }) => {
            onSubmit(value);
        },
    });

    const isDirty = useStore(form.store, (state) => state.isDirty);

    return (
        <Card>
            <UnsavedChangesDialog isDirty={isDirty} isSubmitting={isPending} />
            <CardHeader>
                <CardTitle>اطلاعات اولیه</CardTitle>
                <CardDescription>
                    مشخصات پایه کارمند را ثبت کنید؛ سایر اطلاعات در بخش پروفایل
                    کارمند تکمیل می‌شود
                </CardDescription>
            </CardHeader>
            <CardContent>
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        form.handleSubmit();
                    }}
                >
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <form.Field
                            name="personnel_code"
                            validators={{
                                onBlur: z
                                    .string()
                                    .min(1, "کد پرسنلی الزامی است")
                                    .max(50, "حداکثر ۵۰ کاراکتر"),
                            }}
                        >
                            {(field) => (
                                <FormTextField
                                    field={field}
                                    label="کد پرسنلی"
                                    placeholder="۰۰۰۰۱"
                                    dir="ltr"
                                />
                            )}
                        </form.Field>

                        <form.Field
                            name="first_name"
                            validators={{
                                onBlur: z
                                    .string()
                                    .min(1, "نام الزامی است")
                                    .max(255, "حداکثر ۲۵۵ کاراکتر"),
                            }}
                        >
                            {(field) => (
                                <FormTextField
                                    field={field}
                                    label="نام"
                                    placeholder="محمدرضا"
                                />
                            )}
                        </form.Field>

                        <form.Field
                            name="last_name"
                            validators={{
                                onBlur: z
                                    .string()
                                    .min(1, "نام خانوادگی الزامی است")
                                    .max(255, "حداکثر ۲۵۵ کاراکتر"),
                            }}
                        >
                            {(field) => (
                                <FormTextField
                                    field={field}
                                    label="نام خانوادگی"
                                    placeholder="احمدی"
                                />
                            )}
                        </form.Field>

                        <form.Field
                            name="gender"
                            validators={{
                                onBlur: z
                                    .string()
                                    .min(1, "جنسیت الزامی است"),
                            }}
                        >
                            {(field) => (
                                <FormSelectField
                                    field={field}
                                    label="جنسیت"
                                    options={genderSelectOptions}
                                />
                            )}
                        </form.Field>

                        <form.Field
                            name="employment_type"
                            validators={{
                                onBlur: z
                                    .enum(
                                        [
                                            "official",
                                            "contractual",
                                            "project-based",
                                        ],
                                        {
                                            message: "نوع استخدام نامعتبر است",
                                        },
                                    )
                                    .or(z.literal("")),
                            }}
                        >
                            {(field) => (
                                <FormSelectField
                                    field={field}
                                    label="نوع استخدام"
                                    options={Object.entries(employmentLabels).map(
                                        ([value, label]) => ({
                                            value,
                                            label,
                                        }),
                                    )}
                                />
                            )}
                        </form.Field>

                        <form.Field
                            name="hire_date"
                            validators={{
                                onBlur: z
                                    .string()
                                    .regex(
                                        dateRegex,
                                        "فرمت تاریخ نامعتبر است (YYYY-MM-DD)",
                                    )
                                    .or(z.literal("")),
                            }}
                        >
                            {(field) => {
                                const isInvalid =
                                    field.state.meta.isTouched &&
                                    !field.state.meta.isValid;
                                return (
                                    <Field data-invalid={isInvalid}>
                                        <FieldLabel htmlFor={field.name}>
                                            تاریخ استخدام
                                        </FieldLabel>
                                        <DatePicker
                                            value={field.state.value}
                                            onChange={(val) =>
                                                field.handleChange(val)
                                            }
                                            placeholder="تاریخ استخدام"
                                        />
                                        {isInvalid && (
                                            <FieldError
                                                errors={
                                                    field.state.meta.errors
                                                }
                                            />
                                        )}
                                    </Field>
                                );
                            }}
                        </form.Field>

                        <form.Field
                            name="employment_status"
                            validators={{
                                onBlur: z
                                    .enum(
                                        ["active", "inactive", "suspended"],
                                        {
                                            message:
                                                "وضعیت اشتغال نامعتبر است",
                                        },
                                    )
                                    .or(z.literal("")),
                            }}
                        >
                            {(field) => (
                                <FormSelectField
                                    field={field}
                                    label="وضعیت اشتغال"
                                    options={Object.entries(statusLabels).map(
                                        ([value, label]) => ({
                                            value,
                                            label,
                                        }),
                                    )}
                                />
                            )}
                        </form.Field>
                    </div>

                    <div className="mt-8">
                        <h3 className="text-base font-medium mb-4">
                            کاربر مرتبط
                        </h3>
                        <p className="text-sm text-muted-foreground mb-4">
                            کاربر سیستمی مرتبط با این کارمند را انتخاب کنید
                        </p>
                        <form.Field name="user_id">
                            {(field) => {
                                return (
                                    <Field>
                                        <FieldLabel>کاربر</FieldLabel>
                                        <UserSearchSelect
                                            value={
                                                typeof field.state.value ===
                                                "number"
                                                    ? field.state.value
                                                    : null
                                            }
                                            onChange={(user) =>
                                                field.handleChange(
                                                    user?.id ?? null,
                                                )
                                            }
                                            placeholder="انتخاب کاربر..."
                                            hasEmployee={false}
                                        />
                                    </Field>
                                );
                            }}
                        </form.Field>
                    </div>

                    {error && <ErrorBanner message={error} />}

                    <div className="mt-8 flex items-center gap-3">
                        <Button type="submit" disabled={isPending || !isDirty}>
                            {isPending ? (
                                <>
                                    <IconLoader2 className="size-4 animate-spin" />
                                    در حال ثبت...
                                </>
                            ) : (
                                <>
                                    <IconChecks className="size-4" />
                                    {submitLabel}
                                </>
                            )}
                        </Button>
                        <Button
                            variant="outline"
                            nativeButton={false}
                            render={<Link to=".." />}
                        >
                            انصراف
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}
