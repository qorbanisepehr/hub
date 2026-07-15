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
import { ErrorBanner } from "@/components/shared/error-banner";
import { FormTextField } from "@/components/shared/form-fields";

const baseUserSchema = z.object({
    name: z
        .string()
        .trim()
        .min(1, "نام الزامی است")
        .max(255, "حداکثر ۲۵۵ کاراکتر"),
    email: z
        .string()
        .trim()
        .min(1, "ایمیل الزامی است")
        .email("ایمیل نامعتبر است"),
    phone: z
        .string()
        .trim()
        .max(20, "حداکثر ۲۰ کاراکتر")
        .or(z.literal("")),
    username: z
        .string()
        .trim()
        .max(100, "حداکثر ۱۰۰ کاراکتر")
        .or(z.literal("")),
    password: z
        .string()
        .min(8, "حداقل ۸ کاراکتر")
        .or(z.literal(""))
        .or(z.undefined()),
    password_confirmation: z.string().or(z.literal("")).or(z.undefined()),
});

export const userSchema = baseUserSchema.refine(
    (data) =>
        data.password === "" ||
        data.password === data.password_confirmation,
    {
        message: "رمز عبور و تکرار آن مطابقت ندارند",
        path: ["password_confirmation"],
    },
);

export const createUserSchema = baseUserSchema
    .refine((data) => data.password !== "" && data.password !== undefined, {
        message: "رمز عبور الزامی است",
        path: ["password"],
    })
    .refine(
        (data) =>
            data.password === "" ||
            data.password === data.password_confirmation,
        {
            message: "رمز عبور و تکرار آن مطابقت ندارند",
            path: ["password_confirmation"],
        },
    );

export type UserFormValues = z.infer<typeof userSchema>;

interface UserFormProps {
    defaultValues?: Partial<UserFormValues>;
    onSubmit: (values: UserFormValues) => void;
    isPending?: boolean;
    error?: string | null;
    submitLabel?: string;
    title?: string;
    description?: string;
    showPasswordField?: boolean;
    passwordRequired?: boolean;
    passwordSectionLabel?: string;
    passwordSectionDescription?: string;
}

export function UserForm({
    defaultValues,
    onSubmit,
    isPending = false,
    error = null,
    submitLabel = "ذخیره",
    title = "اطلاعات کاربر",
    description = "اطلاعات هویتی کاربر",
    showPasswordField = true,
    passwordRequired = false,
    passwordSectionLabel = "رمز عبور",
    passwordSectionDescription,
}: UserFormProps) {
    const form = useForm({
        defaultValues: {
            name: "",
            email: "",
            phone: "",
            username: "",
            password: "",
            password_confirmation: "",
            ...defaultValues,
        } as UserFormValues,
        validators: {
            onSubmit: passwordRequired ? createUserSchema : userSchema,
        },
        onSubmit: async ({ value }) => {
            onSubmit(value);
        },
    });

    return (
        <Card>
            <CardHeader>
                <CardTitle>{title}</CardTitle>
                <CardDescription>{description}</CardDescription>
            </CardHeader>
            <CardContent>
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        form.handleSubmit();
                    }}
                >
                    {error && <ErrorBanner message={error} />}

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <form.Field
                            name="name"
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
                                    placeholder="نام کاربر"
                                />
                            )}
                        </form.Field>

                        <form.Field
                            name="email"
                            validators={{
                                onBlur: z
                                    .string()
                                    .min(1, "ایمیل الزامی است")
                                    .email("ایمیل نامعتبر است"),
                            }}
                        >
                            {(field) => (
                                <FormTextField
                                    field={field}
                                    label="ایمیل"
                                    placeholder="user@example.com"
                                    dir="ltr"
                                />
                            )}
                        </form.Field>

                        <form.Field
                            name="phone"
                            validators={{
                                onBlur: z
                                    .string()
                                    .max(20, "حداکثر ۲۰ کاراکتر"),
                            }}
                        >
                            {(field) => (
                                <FormTextField
                                    field={field}
                                    label="تلفن"
                                    placeholder="۰۹۱۲۱۲۳۴۵۶۷"
                                    dir="ltr"
                                />
                            )}
                        </form.Field>

                        <form.Field
                            name="username"
                            validators={{
                                onBlur: z
                                    .string()
                                    .max(100, "حداکثر ۱۰۰ کاراکتر"),
                            }}
                        >
                            {(field) => (
                                <FormTextField
                                    field={field}
                                    label="نام کاربری"
                                    placeholder="username"
                                    dir="ltr"
                                />
                            )}
                        </form.Field>
                    </div>

                    {showPasswordField && (
                        <div className="mt-8">
                            <h3 className="text-base font-medium mb-4">
                                {passwordSectionLabel}
                            </h3>
                            {passwordSectionDescription && (
                                <p className="text-sm text-muted-foreground mb-4">
                                    {passwordSectionDescription}
                                </p>
                            )}
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <form.Field name="password">
                                    {(field) => (
                                        <FormTextField
                                            field={field}
                                            label="رمز عبور"
                                            type="password"
                                            placeholder="حداقل ۸ کاراکتر"
                                            dir="ltr"
                                            autoComplete="new-password"
                                        />
                                    )}
                                </form.Field>

                                <form.Field name="password_confirmation">
                                    {(field) => (
                                        <FormTextField
                                            field={field}
                                            label="تکرار رمز عبور"
                                            type="password"
                                            placeholder="تکرار رمز عبور"
                                            dir="ltr"
                                            autoComplete="new-password"
                                        />
                                    )}
                                </form.Field>
                            </div>
                        </div>
                    )}

                    <div className="mt-8 flex items-center gap-3">
                        <Button type="submit" disabled={isPending}>
                            {isPending ? (
                                <>
                                    <IconLoader2 className="size-4 animate-spin" />
                                    در حال ذخیره...
                                </>
                            ) : (
                                <>
                                    <IconChecks className="size-4" />
                                    {submitLabel}
                                </>
                            )}
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}
