import { useState } from "react";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { Link } from "@tanstack/react-router";
import { toast } from "sonner";
import {
    IconLink,
    IconLoader2,
    IconSettings,
    IconUser,
} from "@tabler/icons-react";

import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import { InfoRow } from "@/components/shared/info-row";
import { updateEmployee } from "@/features/employees/api";
import { UserSearchSelect } from "@/features/rbac/components/user-search-select";
import { employeeKeys } from "@/lib/query-keys";
import { getApiError } from "@/lib/error-utils";
import type { Employee } from "@/features/employees/types";
import type { UserListItem } from "@/features/rbac/types";

/**
 * Manages the system user linked to an employee. Used on both the profile edit
 * form (کاربر سیستمی مرتبط tab) and the read-only profile view, so the same
 * select/create/unlink behaviour is available in both places.
 */
export function LinkedUserSection({ employee }: { employee: Employee }) {
    const queryClient = useQueryClient();
    const [editing, setEditing] = useState(false);
    const [selected, setSelected] = useState<UserListItem | null>(null);

    const updateMutation = useMutation({
        mutationFn: (userId: number | null) =>
            updateEmployee(employee.id, { user_id: userId }),
        onSuccess: () => {
            queryClient.invalidateQueries({
                queryKey: employeeKeys.detail(employee.id),
            });
            toast.success("کاربر مرتبط به‌روزرسانی شد");
            setEditing(false);
            setSelected(null);
        },
        onError: (error: unknown) => {
            toast.error(getApiError(error));
        },
    });

    const user = employee.user;

    const handleSave = () => {
        if (selected) {
            updateMutation.mutate(selected.id);
        }
    };

    const handleUnlink = () => {
        updateMutation.mutate(null);
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <IconUser className="size-5" />
                    کاربر سیستمی مرتبط
                </CardTitle>
                <CardDescription>
                    اطلاعات حساب کاربری متصل به این کارمند
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
                {user && !editing ? (
                    <>
                        <div className="grid gap-6 md:grid-cols-2">
                            <div className="divide-y">
                                <InfoRow label="نام" value={user.name} />
                                <InfoRow
                                    label="ایمیل"
                                    value={
                                        <span dir="ltr">{user.email}</span>
                                    }
                                />
                                <InfoRow
                                    label="تلفن"
                                    value={user.phone ?? "—"}
                                />
                            </div>
                            <div className="divide-y">
                                <InfoRow
                                    label="نام کاربری"
                                    value={
                                        <span dir="ltr">
                                            {user.username ?? "—"}
                                        </span>
                                    }
                                />
                                <InfoRow
                                    label="نقش فعال"
                                    value={
                                        user.active_role ? (
                                            <Badge variant="secondary">
                                                {user.active_role.display_name}
                                            </Badge>
                                        ) : (
                                            "—"
                                        )
                                    }
                                />
                            </div>
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setEditing(true)}
                            >
                                تغییر کاربر
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                nativeButton={false}
                                render={
                                    <Link
                                        to="/users/$userId"
                                        params={{ userId: String(user.id) }}
                                    />
                                }
                            >
                                <IconUser className="size-4" />
                                مشاهده کاربر
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                nativeButton={false}
                                render={
                                    <Link
                                        to="/users/$userId/roles"
                                        params={{ userId: String(user.id) }}
                                    />
                                }
                            >
                                <IconSettings className="size-4" />
                                مدیریت نقش‌ها
                            </Button>
                            <div className="flex-1" />
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={handleUnlink}
                                disabled={updateMutation.isPending}
                            >
                                {updateMutation.isPending && (
                                    <IconLoader2 className="size-4 animate-spin" />
                                )}
                                عدم اتصال
                            </Button>
                        </div>
                    </>
                ) : (
                    <>
                        {!user && (
                            <p className="text-sm text-muted-foreground">
                                کاربر سیستمی متصل نیست. از این بخش می‌توانید
                                کاربری را انتخاب یا کاربر جدیدی بسازید.
                            </p>
                        )}
                        <div className="max-w-md">
                            <UserSearchSelect
                                value={
                                    selected?.id ??
                                    (editing ? user?.id : undefined)
                                }
                                onChange={setSelected}
                                hasEmployee={editing ? undefined : false}
                                placeholder="انتخاب کاربر..."
                            />
                        </div>
                        <div className="flex items-center gap-2">
                            <Button
                                onClick={handleSave}
                                disabled={!selected || updateMutation.isPending}
                            >
                                {updateMutation.isPending ? (
                                    <IconLoader2 className="size-4 animate-spin" />
                                ) : (
                                    <IconLink className="size-4" />
                                )}
                                اتصال کاربر
                            </Button>
                            {editing && (
                                <Button
                                    variant="outline"
                                    onClick={() => {
                                        setEditing(false);
                                        setSelected(null);
                                    }}
                                >
                                    انصراف
                                </Button>
                            )}
                        </div>
                    </>
                )}
            </CardContent>
        </Card>
    );
}
