import { useCallback, useState } from "react";
import { Link } from "@tanstack/react-router";
import { useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { IconPencil } from "@tabler/icons-react";

import { Button } from "@/components/ui/button";
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    CardDescription,
} from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { useAuth } from "@/features/auth/useAuth";
import { ME_KEY } from "@/features/auth/constants";
import { uploadAvatar, deleteAvatar } from "@/features/auth/api";
import { getApiError } from "@/lib/error-utils";
import { AvatarUpload } from "@/components/shared/avatar-upload";
import { RoleSwitcher } from "@/features/auth/components/role-switcher";
import { InfoRow } from "@/components/shared/info-row";
import { PageLayout } from "@/components/shared/page-layout";
import { ErrorPage } from "@/components/shared/error-page";
import { PageHeader } from "@/components/shared/page-header";
import { PageSkeleton } from "@/components/shared/page-skeleton";

export function ProfilePage() {
    const { user, isLoading } = useAuth();
    const queryClient = useQueryClient();
    const [isAvatarPending, setIsAvatarPending] = useState(false);

    const handleAvatarUpload = useCallback(
        async (file: File) => {
            setIsAvatarPending(true);
            try {
                await uploadAvatar(file);
                await queryClient.invalidateQueries({ queryKey: ME_KEY });
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
            await queryClient.invalidateQueries({ queryKey: ME_KEY });
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

    return (
        <PageLayout>
            <PageHeader title="حساب کاربری" description="مشاهده و مدیریت اطلاعات پروفایل">
                <Button
                    variant="outline"
                    nativeButton={false}
                    render={<Link to="/profile/edit" />}
                >
                    <IconPencil className="size-4" />
                    ویرایش پروفایل
                </Button>
            </PageHeader>

            <div className="grid gap-6 md:grid-cols-[280px_1fr]">
                <div className="space-y-6">
                    <Card>
                        <CardContent className="pt-6">
                            <AvatarUpload
                                avatarUrl={user.avatar_url}
                                name={user.name}
                                isPending={isAvatarPending}
                                onUpload={handleAvatarUpload}
                                onDelete={handleAvatarDelete}
                            />
                        </CardContent>
                    </Card>

                    <RoleSwitcher />
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>اطلاعات شخصی</CardTitle>
                        <CardDescription>اطلاعات هویتی حساب کاربری</CardDescription>
                    </CardHeader>
                    <CardContent className="divide-y">
                        <InfoRow label="نام" value={user.name} />
                        <InfoRow
                            label="ایمیل"
                            value={<span dir="ltr">{user.email}</span>}
                        />
                        <InfoRow
                            label="تلفن"
                            value={user.phone ? <span dir="ltr">{user.phone}</span> : "—"}
                        />
                        <InfoRow label="نام کاربری" value={user.username ?? "—"} />
                        <InfoRow
                            label="وضعیت"
                            value={
                                <Badge variant={user.is_active ? "default" : "secondary"}>
                                    {user.is_active ? "فعال" : "غیرفعال"}
                                </Badge>
                            }
                        />
                    </CardContent>
                </Card>
            </div>
        </PageLayout>
    );
}
