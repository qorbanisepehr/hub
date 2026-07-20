import { useCallback, useState } from "react";
import { Link } from "@tanstack/react-router";
import { useForm, useStore } from "@tanstack/react-form";
import { useQueryClient } from "@tanstack/react-query";
import { z } from "zod";
import { toast } from "sonner";
import { IconChecks, IconLoader2, IconLock } from "@tabler/icons-react";

import { Button } from "@/components/ui/button";
import { getApiError } from "@/lib/error-utils";
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    CardDescription,
} from "@/components/ui/card";
import { useAuth } from "@/features/auth/useAuth";
import { authKeys } from "@/lib/query-keys";
import {
    updateProfile,
    uploadAvatar,
    deleteAvatar,
    changePassword,
} from "@/features/auth/api";
import { AvatarUpload } from "@/components/shared/avatar-upload";
import { FormTextField } from "@/components/shared/form-fields";
import { PasswordField } from "@/features/auth/components/password-field";
import { ErrorBanner } from "@/components/shared/error-banner";
import { UnsavedChangesDialog } from "@/components/shared/unsaved-changes-dialog";
import { PageLayout } from "@/components/shared/page-layout";
import { PageHeader } from "@/components/shared/page-header";
import { PageSkeleton } from "@/components/shared/page-skeleton";
import { ErrorPage } from "@/components/shared/error-page";

const profileSchema = z.object({
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
});

const passwordSchema = z
    .object({
        current_password: z.string().min(1, "رمز عبور فعلی الزامی است"),
        password: z.string().min(8, "حداقل ۸ کاراکتر"),
        password_confirmation: z.string(),
    })
    .refine((data) => data.password === data.password_confirmation, {
        message: "تکرار رمز عبور مطابقت ندارد.",
        path: ["password_confirmation"],
    });

type ProfileFormValues = z.infer<typeof profileSchema>;
type PasswordFormValues = z.infer<typeof passwordSchema>;

export function ProfileEditPage() {
    const { user, isLoading } = useAuth();
    const queryClient = useQueryClient();
    const [error, setError] = useState<string | null>(null);
    const [isAvatarPending, setIsAvatarPending] = useState(false);

    const profileForm = useForm({
        defaultValues: {
            name: user?.name ?? "",
            email: user?.email ?? "",
            phone: user?.phone ?? "",
        } as ProfileFormValues,
        validators: {
            onSubmit: profileSchema,
        },
        onSubmit: async ({ value }) => {
            setError(null);
            try {
                await updateProfile(value);
                await queryClient.invalidateQueries({ queryKey: authKeys.me() });
                toast.success("پروفایل با موفقیت به‌روزرسانی شد.");
            } catch (err: unknown) {
                setError(getApiError(err));
            }
        },
    });

    const passwordForm = useForm({
        defaultValues: {
            current_password: "",
            password: "",
            password_confirmation: "",
        } as PasswordFormValues,
        validators: {
            onSubmit: passwordSchema,
        },
        onSubmit: async ({ value, formApi }) => {
            try {
                await changePassword(value);
                formApi.reset();
                toast.success("رمز عبور با موفقیت تغییر کرد.");
            } catch (err: unknown) {
                toast.error(getApiError(err));
            }
        },
    });

    const isProfileDirty = useStore(profileForm.store, (state) => state.isDirty);
    const isProfileSubmitting = useStore(profileForm.store, (state) => state.isSubmitting);
    const isPasswordDirty = useStore(passwordForm.store, (state) => state.isDirty);
    const isPasswordSubmitting = useStore(passwordForm.store, (state) => state.isSubmitting);

    const handleAvatarUpload = useCallback(
        async (file: File) => {
            setIsAvatarPending(true);
            try {
                await uploadAvatar(file);
                await queryClient.invalidateQueries({ queryKey: authKeys.me() });
                toast.success("عکس پروفایل با موفقیت آپلود شد.");
            } catch (err) {
                toast.error(getApiError(err));
            } finally {
                setIsAvatarPending(false);
            }
        },
        [queryClient],
    );

    const handleAvatarDelete = useCallback(async () => {
        setIsAvatarPending(true);
        try {
            await deleteAvatar();
            await queryClient.invalidateQueries({ queryKey: authKeys.me() });
            toast.success("عکس پروفایل حذف شد.");
        } catch (err) {
            toast.error(getApiError(err));
        } finally {
            setIsAvatarPending(false);
        }
    }, [queryClient]);

    if (isLoading) {
        return <PageSkeleton />;
    }

    if (!user) {
        return (
            <ErrorPage
                title="خطا در بارگذاری پروفایل"
                homeTo="/dashboard"
                homeLabel="بازگشت به داشبورد"
            />
        );
    }

    const isDirty = isProfileDirty || isPasswordDirty;

    return (
        <PageLayout>
            <UnsavedChangesDialog isDirty={isDirty} isSubmitting={isProfileSubmitting || isPasswordSubmitting} />
            <PageHeader title="ویرایش پروفایل" backTo="/profile">
                <Button
                    variant="outline"
                    nativeButton={false}
                    render={<Link to="/profile" />}
                >
                    بازگشت به پروفایل
                </Button>
            </PageHeader>

            <div className="grid gap-6 md:grid-cols-[280px_1fr]">
                <Card>
                    <CardHeader>
                        <CardTitle>عکس پروفایل</CardTitle>
                        <CardDescription>
                            عکسی که در پروفایل نمایش داده می‌شود.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <AvatarUpload
                            avatarUrl={user.avatar_url}
                            name={user.name}
                            isPending={isAvatarPending}
                            onUpload={handleAvatarUpload}
                            onDelete={handleAvatarDelete}
                        />
                    </CardContent>
                </Card>

                <div className="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>اطلاعات شخصی</CardTitle>
                            <CardDescription>
                                اطلاعات هویتی خود را به‌روزرسانی کنید.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    e.stopPropagation();
                                    profileForm.handleSubmit();
                                }}
                            >
                                    {error && <ErrorBanner message={error} />}

                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <profileForm.Field
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
                                        </profileForm.Field>

                                        <profileForm.Field
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
                                        </profileForm.Field>

                                        <profileForm.Field
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
                                        </profileForm.Field>
                                    </div>

                                    <div className="mt-8 flex items-center gap-3">
                                        <Button type="submit" disabled={isProfileSubmitting || !isProfileDirty}>
                                            {isProfileSubmitting ? (
                                                <>
                                                    <IconLoader2 className="size-4 animate-spin" />
                                                    در حال ذخیره...
                                                </>
                                            ) : (
                                                <>
                                                    <IconChecks className="size-4" />
                                                    ذخیره تغییرات
                                                </>
                                            )}
                                        </Button>
                                    </div>
                                </form>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <IconLock className="size-5" />
                                تغییر رمز عبور
                            </CardTitle>
                            <CardDescription>
                                رمز عبور خود را به‌روزرسانی کنید.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                                <form
                                    onSubmit={(e) => {
                                        e.preventDefault();
                                        e.stopPropagation();
                                        passwordForm.handleSubmit();
                                    }}
                                >
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <passwordForm.Field name="current_password">
                                            {(field) => (
                                                <PasswordField
                                                    field={field}
                                                    label="رمز عبور فعلی"
                                                    placeholder="رمز عبور فعلی"
                                                    autoComplete="current-password"
                                                />
                                            )}
                                        </passwordForm.Field>

                                        <div />

                                        <passwordForm.Field name="password">
                                            {(field) => (
                                                <PasswordField
                                                    field={field}
                                                    label="رمز عبور جدید"
                                                    placeholder="حداقل ۸ کاراکتر"
                                                    showStrength
                                                />
                                            )}
                                        </passwordForm.Field>

                                        <passwordForm.Field name="password_confirmation">
                                            {(field) => (
                                                <PasswordField
                                                    field={field}
                                                    label="تکرار رمز عبور جدید"
                                                    placeholder="تکرار رمز عبور"
                                                />
                                            )}
                                        </passwordForm.Field>
                                    </div>

                                    <div className="mt-8 flex items-center gap-3">
                                        <Button type="submit" disabled={isPasswordSubmitting || !isPasswordDirty}>
                                            {isPasswordSubmitting ? (
                                                <>
                                                    <IconLoader2 className="size-4 animate-spin" />
                                                    در حال ذخیره...
                                                </>
                                            ) : (
                                                <>
                                                    <IconChecks className="size-4" />
                                                    تغییر رمز عبور
                                                </>
                                            )}
                                        </Button>
                                    </div>
                                </form>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </PageLayout>
    );
}
