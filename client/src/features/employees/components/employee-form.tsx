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
import { Input } from "@/components/ui/input";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { DatePicker } from "@/components/ui/date-picker";
import {
    genderLabels,
    maritalLabels,
    educationLabels,
    employmentLabels,
    statusLabels,
} from "@/features/employees/constants";
import { Link } from "@tanstack/react-router";

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
                            {(field) => {
                                const isInvalid =
                                    field.state.meta.isTouched &&
                                    !field.state.meta.isValid;
                                return (
                                    <Field data-invalid={isInvalid}>
                                        <FieldLabel htmlFor={field.name}>
                                            کد پرسنلی
                                        </FieldLabel>
                                        <Input
                                            id={field.name}
                                            name={field.name}
                                            value={field.state.value}
                                            onBlur={field.handleBlur}
                                            onChange={(e) =>
                                                field.handleChange(
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="۰۰۰۰۱"
                                            dir="ltr"
                                        />
                                        {isInvalid && (
                                            <FieldError
                                                errors={field.state.meta.errors}
                                            />
                                        )}
                                    </Field>
                                );
                            }}
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
                            {(field) => {
                                const isInvalid =
                                    field.state.meta.isTouched &&
                                    !field.state.meta.isValid;
                                return (
                                    <Field data-invalid={isInvalid}>
                                        <FieldLabel htmlFor={field.name}>
                                            نام
                                        </FieldLabel>
                                        <Input
                                            id={field.name}
                                            name={field.name}
                                            value={field.state.value}
                                            onBlur={field.handleBlur}
                                            onChange={(e) =>
                                                field.handleChange(
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="محمدرضا"
                                        />
                                        {isInvalid && (
                                            <FieldError
                                                errors={field.state.meta.errors}
                                            />
                                        )}
                                    </Field>
                                );
                            }}
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
                            {(field) => {
                                const isInvalid =
                                    field.state.meta.isTouched &&
                                    !field.state.meta.isValid;
                                return (
                                    <Field data-invalid={isInvalid}>
                                        <FieldLabel htmlFor={field.name}>
                                            نام خانوادگی
                                        </FieldLabel>
                                        <Input
                                            id={field.name}
                                            name={field.name}
                                            value={field.state.value}
                                            onBlur={field.handleBlur}
                                            onChange={(e) =>
                                                field.handleChange(
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="احمدی"
                                        />
                                        {isInvalid && (
                                            <FieldError
                                                errors={field.state.meta.errors}
                                            />
                                        )}
                                    </Field>
                                );
                            }}
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
                            {(field) => {
                                const isInvalid =
                                    field.state.meta.isTouched &&
                                    !field.state.meta.isValid;
                                return (
                                    <Field data-invalid={isInvalid}>
                                        <FieldLabel htmlFor={field.name}>
                                            جنسیت
                                        </FieldLabel>
                                        <Select
                                            value={field.state.value || null}
                                            onValueChange={(val) =>
                                                field.handleChange(val ?? "")
                                            }
                                            itemToStringLabel={(val) =>
                                                val
                                                    ? (genderLabels[
                                                          val as string
                                                      ] ?? val)
                                                    : ""
                                            }
                                        >
                                            <SelectTrigger id={field.name}>
                                                <SelectValue placeholder="انتخاب کنید" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="male">
                                                    مرد
                                                </SelectItem>
                                                <SelectItem value="female">
                                                    زن
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        {isInvalid && (
                                            <FieldError
                                                errors={field.state.meta.errors}
                                            />
                                        )}
                                    </Field>
                                );
                            }}
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
                                                errors={field.state.meta.errors}
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
                            {(field) => {
                                const isInvalid =
                                    field.state.meta.isTouched &&
                                    !field.state.meta.isValid;
                                return (
                                    <Field data-invalid={isInvalid}>
                                        <FieldLabel htmlFor={field.name}>
                                            کد ملی
                                        </FieldLabel>
                                        <Input
                                            id={field.name}
                                            name={field.name}
                                            value={field.state.value}
                                            onBlur={field.handleBlur}
                                            onChange={(e) =>
                                                field.handleChange(
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="۱۲۳۴۵۶۷۸۹۰"
                                            dir="ltr"
                                        />
                                        {isInvalid && (
                                            <FieldError
                                                errors={field.state.meta.errors}
                                            />
                                        )}
                                    </Field>
                                );
                            }}
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
                            {(field) => {
                                const isInvalid =
                                    field.state.meta.isTouched &&
                                    !field.state.meta.isValid;
                                return (
                                    <Field data-invalid={isInvalid}>
                                        <FieldLabel htmlFor={field.name}>
                                            وضعیت تاهل
                                        </FieldLabel>
                                        <Select
                                            value={field.state.value || null}
                                            onValueChange={(val) =>
                                                field.handleChange(val ?? "")
                                            }
                                            itemToStringLabel={(val) =>
                                                val
                                                    ? (maritalLabels[
                                                          val as string
                                                      ] ?? val)
                                                    : ""
                                            }
                                        >
                                            <SelectTrigger id={field.name}>
                                                <SelectValue placeholder="انتخاب کنید" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="single">
                                                    مجرد
                                                </SelectItem>
                                                <SelectItem value="married">
                                                    متاهل
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        {isInvalid && (
                                            <FieldError
                                                errors={field.state.meta.errors}
                                            />
                                        )}
                                    </Field>
                                );
                            }}
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
                                {(field) => {
                                    const isInvalid =
                                        field.state.meta.isTouched &&
                                        !field.state.meta.isValid;
                                    return (
                                        <Field data-invalid={isInvalid}>
                                            <FieldLabel htmlFor={field.name}>
                                                نوع استخدام
                                            </FieldLabel>
                                            <Select
                                                value={
                                                    field.state.value || null
                                                }
                                                onValueChange={(val) =>
                                                    field.handleChange(
                                                        val ?? "",
                                                    )
                                                }
                                                itemToStringLabel={(val) =>
                                                    val
                                                        ? (employmentLabels[
                                                              val as string
                                                          ] ?? val)
                                                        : ""
                                                }
                                            >
                                                <SelectTrigger id={field.name}>
                                                    <SelectValue placeholder="انتخاب کنید" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="official">
                                                        رسمی
                                                    </SelectItem>
                                                    <SelectItem value="contractual">
                                                        قراردادی
                                                    </SelectItem>
                                                    <SelectItem value="project-based">
                                                        پروژه‌ای
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
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
                                {(field) => {
                                    const isInvalid =
                                        field.state.meta.isTouched &&
                                        !field.state.meta.isValid;
                                    return (
                                        <Field data-invalid={isInvalid}>
                                            <FieldLabel htmlFor={field.name}>
                                                وضعیت اشتغال
                                            </FieldLabel>
                                            <Select
                                                value={
                                                    field.state.value || null
                                                }
                                                onValueChange={(val) =>
                                                    field.handleChange(
                                                        val ?? "",
                                                    )
                                                }
                                                itemToStringLabel={(val) =>
                                                    val
                                                        ? (statusLabels[
                                                              val as string
                                                          ] ?? val)
                                                        : ""
                                                }
                                            >
                                                <SelectTrigger id={field.name}>
                                                    <SelectValue placeholder="انتخاب کنید" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="active">
                                                        فعال
                                                    </SelectItem>
                                                    <SelectItem value="inactive">
                                                        غیرفعال
                                                    </SelectItem>
                                                    <SelectItem value="suspended">
                                                        تعلیق
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
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
                                {(field) => {
                                    const isInvalid =
                                        field.state.meta.isTouched &&
                                        !field.state.meta.isValid;
                                    return (
                                        <Field data-invalid={isInvalid}>
                                            <FieldLabel htmlFor={field.name}>
                                                سطح تحصیلات
                                            </FieldLabel>
                                            <Select
                                                value={
                                                    field.state.value || null
                                                }
                                                onValueChange={(val) =>
                                                    field.handleChange(
                                                        val ?? "",
                                                    )
                                                }
                                                itemToStringLabel={(val) =>
                                                    val
                                                        ? (educationLabels[
                                                              val as string
                                                          ] ?? val)
                                                        : ""
                                                }
                                            >
                                                <SelectTrigger id={field.name}>
                                                    <SelectValue placeholder="انتخاب کنید" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="diploma">
                                                        دیپلم
                                                    </SelectItem>
                                                    <SelectItem value="associate">
                                                        فوق دیپلم
                                                    </SelectItem>
                                                    <SelectItem value="bachelor">
                                                        لیسانس
                                                    </SelectItem>
                                                    <SelectItem value="master">
                                                        فوق لیسانس
                                                    </SelectItem>
                                                    <SelectItem value="doctorate">
                                                        دکتری
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
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
                                name="education_field"
                                validators={{
                                    onBlur: z
                                        .string()
                                        .max(255, "حداکثر ۲۵۵ کاراکتر"),
                                }}
                            >
                                {(field) => {
                                    const isInvalid =
                                        field.state.meta.isTouched &&
                                        !field.state.meta.isValid;
                                    return (
                                        <Field data-invalid={isInvalid}>
                                            <FieldLabel htmlFor={field.name}>
                                                رشته تحصیلی
                                            </FieldLabel>
                                            <Input
                                                id={field.name}
                                                name={field.name}
                                                value={field.state.value}
                                                onBlur={field.handleBlur}
                                                onChange={(e) =>
                                                    field.handleChange(
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="مهندسی کامپیوتر"
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
                        </div>
                    </div>

                    {error && (
                        <div className="mt-6 rounded-lg bg-destructive/10 p-3 text-sm text-destructive">
                            {error}
                        </div>
                    )}

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
