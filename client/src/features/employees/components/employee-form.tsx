import { useForm } from "@tanstack/react-form";
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
    genderLabels,
    maritalLabels,
    educationLabels,
    employmentLabels,
    statusLabels,
} from "@/features/employees/constants";
import { Link } from "@tanstack/react-router";
import { FormTextField, FormSelectField } from "@/components/shared/form-fields";
import { ErrorBanner } from "@/components/shared/error-banner";
import { UserSearchSelect } from "@/features/rbac/components/user-search-select";

const dateRegex = /^\d{4}-\d{2}-\d{2}$/;

function validateNationalId(id: string): boolean {
    if (!/^\d{10}$/.test(id)) return false;

    const digits = id.split("").map(Number);
    const checksum = digits.pop()!;
    const sum = digits.reduce((acc, d, i) => acc + d * (10 - i), 0);
    const remainder = sum % 11;

    return remainder < 2 ? checksum === remainder : checksum === 11 - remainder;
}

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
    gender: z
        .enum(["male", "female"], {
            message: "جنسیت الزامی است",
        })
        .or(z.literal("")),
    birth_date: z
        .string()
        .regex(dateRegex, "فرمت تاریخ نامعتبر است (YYYY-MM-DD)")
        .or(z.literal("")),
    id_number: z
        .string()
        .refine(
            (val) => val === "" || validateNationalId(val),
            "کد ملی نامعتبر است",
        ),
    marital_status: z
        .enum(["single", "married"], {
            message: "وضعیت تاهل نامعتبر است",
        })
        .or(z.literal("")),
    education_level: z
        .enum(["diploma", "associate", "bachelor", "master", "doctorate"], {
            message: "سطح تحصیلات نامعتبر است",
        })
        .or(z.literal("")),
    education_field: z.string().trim().max(255, "حداکثر ۲۵۵ کاراکتر"),
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
    user_id: z
        .number()
        .nullable()
        .or(z.undefined()),
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
    const form = useForm({
        defaultValues: {
            personnel_code: "",
            first_name: "",
            last_name: "",
            gender: "",
            birth_date: "",
            id_number: "",
            marital_status: "",
            education_level: "",
            education_field: "",
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

    return (
        <Card>
            <CardHeader>
                <CardTitle>اطلاعات فردی</CardTitle>
                <CardDescription>
                    اطلاعات هویتی و پرسنلی کارمند را وارد کنید
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
                                    .enum(["male", "female"], {
                                        message: "جنسیت الزامی است",
                                    })
                                    .or(z.literal("")),
                            }}
                        >
                            {(field) => (
                                <FormSelectField
                                    field={field}
                                    label="جنسیت"
                                    options={[
                                        { value: "male", label: "مرد" },
                                        { value: "female", label: "زن" },
                                    ]}
                                />
                            )}
                        </form.Field>

                        <form.Field
                            name="birth_date"
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
                                            تاریخ تولد
                                        </FieldLabel>
                                        <DatePicker
                                            value={field.state.value}
                                            onChange={(val) =>
                                                field.handleChange(val)
                                            }
                                            placeholder="تاریخ تولد"
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
                            name="id_number"
                            validators={{
                                onBlur: z
                                    .string()
                                    .refine(
                                        (val) =>
                                            val === "" ||
                                            validateNationalId(val),
                                        "کد ملی نامعتبر است",
                                    ),
                            }}
                        >
                            {(field) => (
                                <FormTextField
                                    field={field}
                                    label="کد ملی"
                                    placeholder="۱۲۳۴۵۶۷۸۹۰"
                                    dir="ltr"
                                />
                            )}
                        </form.Field>

                        <form.Field
                            name="marital_status"
                            validators={{
                                onBlur: z
                                    .enum(["single", "married"], {
                                        message: "وضعیت تاهل نامعتبر است",
                                    })
                                    .or(z.literal("")),
                            }}
                        >
                            {(field) => (
                                <FormSelectField
                                    field={field}
                                    label="وضعیت تاهل"
                                    options={[
                                        { value: "single", label: "مجرد" },
                                        { value: "married", label: "متاهل" },
                                    ]}
                                />
                            )}
                        </form.Field>
                    </div>

                    <div className="mt-8">
                        <h3 className="text-base font-medium mb-4">
                            اطلاعات شغلی
                        </h3>
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
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
                                            message:
                                                "نوع استخدام نامعتبر است",
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

                        <form.Field
                            name="education_level"
                            validators={{
                                onBlur: z
                                    .enum(
                                        [
                                            "diploma",
                                            "associate",
                                            "bachelor",
                                            "master",
                                            "doctorate",
                                        ],
                                        {
                                            message:
                                                "سطح تحصیلات نامعتبر است",
                                        },
                                    )
                                    .or(z.literal("")),
                            }}
                        >
                            {(field) => (
                                <FormSelectField
                                    field={field}
                                    label="سطح تحصیلات"
                                    options={Object.entries(educationLabels).map(
                                        ([value, label]) => ({
                                            value,
                                            label,
                                        }),
                                    )}
                                />
                            )}
                        </form.Field>

                        <form.Field
                            name="education_field"
                            validators={{
                                onBlur: z
                                    .string()
                                    .max(255, "حداکثر ۲۵۵ کاراکتر"),
                            }}
                        >
                            {(field) => (
                                <FormTextField
                                    field={field}
                                    label="رشته تحصیلی"
                                    placeholder="مهندسی کامپیوتر"
                                />
                            )}
                        </form.Field>
                        </div>
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
                                            value={typeof field.state.value === "number" ? field.state.value : null}
                                            onChange={(user) =>
                                                field.handleChange(user?.id ?? null)
                                            }
                                            placeholder="انتخاب کاربر..."
                                        />
                                    </Field>
                                );
                            }}
                        </form.Field>
                    </div>

                    {error && <ErrorBanner message={error} />}

                    <div className="mt-8 flex items-center gap-3">
                        <Button type="submit" disabled={isPending}>
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
