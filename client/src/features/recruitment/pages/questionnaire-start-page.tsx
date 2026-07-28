import { useState } from "react";
import { useNavigate } from "@tanstack/react-router";
import { useMutation } from "@tanstack/react-query";
import { useForm } from "@tanstack/react-form";
import { z } from "zod";
import { toast } from "sonner";
import { IconLoader2, IconSend } from "@tabler/icons-react";

import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { FormTextField } from "@/components/shared/form-fields";
import { ErrorBanner } from "@/components/shared/error-banner";
import { initQuestionnaire } from "@/features/recruitment/api";
import { getApiError } from "@/lib/error-utils";

const initSchema = z.object({
    first_name: z
        .string()
        .trim()
        .min(1, "نام الزامی است")
        .max(100, "حداکثر ۱۰۰ کاراکتر"),
    last_name: z
        .string()
        .trim()
        .min(1, "نام خانوادگی الزامی است")
        .max(100, "حداکثر ۱۰۰ کاراکتر"),
    email: z
        .string()
        .trim()
        .min(1, "ایمیل الزامی است")
        .email("فرمت ایمیل نامعتبر است"),
    mobile: z
        .string()
        .trim()
        .min(1, "شماره موبایل الزامی است")
        .regex(
            /^(09\d{9}|\+989\d{9}|00989\d{9})$/,
            "فرمت شماره موبایل نامعتبر است (مثال: 09121234567)",
        )
        .max(15, "حداکثر ۱۵ کاراکتر"),
});

export function QuestionnaireStartPage() {
    const navigate = useNavigate();

    const mutation = useMutation({
        mutationFn: initQuestionnaire,
        onSuccess: (response) => {
            toast.success("پرسشنامه ایجاد شد.");
            navigate({
                to: "/questionnaire/$uuid",
                params: { uuid: response.data.data.uuid },
            });
        },
        onError: (err) => {
            toast.error(getApiError(err));
        },
    });

    const form = useForm({
        defaultValues: {
            first_name: "",
            last_name: "",
            email: "",
            mobile: "",
        },
        validators: { onSubmit: initSchema },
        onSubmit: async ({ value }) => {
            mutation.mutate(value);
        },
    });

    return (
        <div className="min-h-screen bg-background pt-16">
            <div className="mx-auto max-w-2xl px-4 py-12">
                <div className="text-center mb-8">
                    <h1 className="text-2xl font-bold">پرسشنامه استخدامی</h1>
                    <p className="text-muted-foreground mt-2">
                        لطفاً اطلاعات زیر را تکمیل کنید تا فرم پرسشنامه برای شما
                        ایجاد شود.
                    </p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>اطلاعات تماس</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                e.stopPropagation();
                                form.handleSubmit();
                            }}
                            className="space-y-4"
                        >
                            {mutation.error && (
                                <ErrorBanner
                                    message={
                                        getApiError(mutation.error) ??
                                        "خطای ناشناخته"
                                    }
                                />
                            )}

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <form.Field name="first_name">
                                    {(field) => (
                                        <FormTextField
                                            field={field}
                                            label="نام"
                                        />
                                    )}
                                </form.Field>

                                <form.Field name="last_name">
                                    {(field) => (
                                        <FormTextField
                                            field={field}
                                            label="نام خانوادگی"
                                        />
                                    )}
                                </form.Field>
                            </div>

                            <form.Field name="email">
                                {(field) => (
                                    <FormTextField
                                        field={field}
                                        label="ایمیل"
                                        dir="ltr"
                                    />
                                )}
                            </form.Field>

                            <form.Field name="mobile">
                                {(field) => (
                                    <FormTextField
                                        field={field}
                                        label="شماره موبایل"
                                        dir="ltr"
                                    />
                                )}
                            </form.Field>

                            <Button
                                type="submit"
                                className="w-full"
                                disabled={mutation.isPending}
                            >
                                {mutation.isPending ? (
                                    <IconLoader2 className="size-4 animate-spin ms-2" />
                                ) : (
                                    <IconSend className="size-4 ms-2" />
                                )}
                                شروع پرسشنامه
                            </Button>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}
