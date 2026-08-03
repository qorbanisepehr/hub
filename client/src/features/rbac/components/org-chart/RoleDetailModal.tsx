import { useEffect, useState } from "react";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Separator } from "@/components/ui/separator";
import { cn } from "@/lib/utils";
import {
    IconBuilding,
    IconInfoCircle,
    IconMasksTheater,
    IconNetwork,
    IconSchool,
    IconUserCheck,
    IconUsers,
} from "@tabler/icons-react";
import type { RoleChartRole } from "@/features/rbac/types";
import { MATRIX_MANAGER_TYPES } from "@/features/rbac/constants";

function getInitials(name: string): string {
    const parts = name.trim().split(/\s+/);
    return parts.slice(0, 2).map((part) => part[0] ?? "").join("");
}

function UserAvatar({ user, size = "sm" }: { user: { name: string; avatar_url: string | null }; size?: "sm" | "md" | "lg" }) {
    const sizeClass = size === "sm" ? "size-8" : size === "lg" ? "size-16" : "size-10";
    const textClass = size === "sm" ? "text-[9px]" : size === "lg" ? "text-sm" : "text-xs";
    return (
        <div className={cn("relative shrink-0 overflow-hidden rounded-full ring-2 ring-background shadow-sm", sizeClass)}>
            {user.avatar_url ? (
                <img src={user.avatar_url} alt={user.name} className="size-full object-cover" />
            ) : (
                <div className={cn("flex size-full items-center justify-center bg-muted font-bold text-muted-foreground", textClass)}>
                    {getInitials(user.name)}
                </div>
            )}
        </div>
    );
}

export function RoleDetailModal({
    role,
    roles,
    open,
    onOpenChange,
}: {
    role: RoleChartRole | null;
    roles: RoleChartRole[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const [tab, setTab] = useState("info");

    useEffect(() => {
        if (open) {
            setTab("info");
        }
    }, [open]);

    if (!role) {
        return null;
    }

    const parent = role.parent_id != null ? roles.find((r) => r.id === role.parent_id) ?? null : null;
    const managedRoles = roles.filter((r) => (r.matrix_manager_roles ?? []).some((m) => m.id === role.id));
    const matrixManagers = role.matrix_manager_roles ?? [];
    const directChildren = role.children ?? [];

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-md">
                <div dir="rtl" className="space-y-0">
                    <DialogHeader className="pb-0">
                        <div className="flex flex-col items-center gap-3 pt-2">
                            <div className="flex size-16 items-center justify-center rounded-full bg-primary/10 text-primary ring-1 ring-border">
                                <IconMasksTheater className="size-8" />
                            </div>
                            <div className="space-y-1 text-center">
                                <DialogTitle className="text-lg font-bold">
                                    {role.display_name}
                                </DialogTitle>
                                <DialogDescription dir="ltr" className="text-right text-center text-sm font-medium">
                                    {role.name}
                                </DialogDescription>
                                <span
                                    className={cn(
                                        "mt-1 inline-flex items-center rounded-md border px-2.5 py-0.5 text-[11px] font-semibold",
                                        role.is_active
                                            ? "border-primary/20 bg-primary/10 text-primary"
                                            : "border-border bg-muted text-muted-foreground",
                                    )}
                                >
                                    {role.is_active ? "فعال" : "غیرفعال"}
                                </span>
                            </div>
                        </div>
                    </DialogHeader>

                    <div className="mt-4">
                        <Tabs value={tab} onValueChange={setTab}>
                            <TabsList className="grid w-full grid-cols-3">
                                <TabsTrigger value="info" className="gap-1.5 text-xs">
                                    <IconInfoCircle className="size-3.5" />
                                    اطلاعات
                                </TabsTrigger>
                                <TabsTrigger value="managers" className="gap-1.5 text-xs">
                                    <IconUserCheck className="size-3.5" />
                                    مدیران ماتریسی
                                </TabsTrigger>
                                <TabsTrigger value="related" className="gap-1.5 text-xs">
                                    <IconNetwork className="size-3.5" />
                                    نقش‌های مرتبط
                                </TabsTrigger>
                            </TabsList>

                            <TabsContent value="info">
                                <div className="space-y-3 pt-2">
                                    <div className="rounded-lg border bg-muted/50 p-3">
                                        <div className="flex items-start gap-2">
                                            <IconSchool className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                                            <p className="text-sm leading-relaxed text-muted-foreground">
                                                {role.description || "توضیحاتی ثبت نشده است."}
                                            </p>
                                        </div>
                                    </div>
                                    <Separator />
                                    <div className="space-y-2.5">
                                        <div className="flex items-center gap-2.5 text-sm">
                                            <div className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                                                <IconBuilding className="size-4 text-primary" />
                                            </div>
                                            <div>
                                                <p className="text-[10px] text-muted-foreground">نقش والد</p>
                                                <span className="font-medium text-card-foreground">
                                                    {parent?.display_name ?? "ندارد"}
                                                </span>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-2.5 text-sm">
                                            <div className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                                                <IconUsers className="size-4 text-primary" />
                                            </div>
                                            <div>
                                                <p className="text-[10px] text-muted-foreground">اعضا</p>
                                                <span className="font-medium text-card-foreground">
                                                    {role.user_count} کاربر
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    {role.users.length > 0 && (
                                        <>
                                            <Separator />
                                            <div className="space-y-2">
                                                {role.users.slice(0, 6).map((user) => (
                                                    <div
                                                        key={user.id}
                                                        className="flex items-center gap-3 rounded-xl border bg-card p-2.5"
                                                    >
                                                        <UserAvatar user={user} size="sm" />
                                                        <div className="min-w-0 flex-1">
                                                            <p className="truncate text-sm font-semibold text-card-foreground">
                                                                {user.name}
                                                            </p>
                                                        </div>
                                                    </div>
                                                ))}
                                                {role.users.length > 6 && (
                                                    <p className="text-center text-xs text-muted-foreground">
                                                        و {role.users.length - 6} کاربر دیگر
                                                    </p>
                                                )}
                                            </div>
                                        </>
                                    )}
                                </div>
                            </TabsContent>

                            <TabsContent value="managers">
                                <div className="max-h-[280px] space-y-2 overflow-y-auto pt-2">
                                    {matrixManagers.length === 0 && (
                                        <div className="py-8 text-center">
                                            <IconUserCheck className="mx-auto mb-2 size-8 text-muted-foreground/40" />
                                            <p className="text-sm text-muted-foreground">
                                                این نقش مدیر ماتریسی ندارد.
                                            </p>
                                        </div>
                                    )}
                                    {matrixManagers.map((manager) => (
                                        <div
                                            key={`${manager.id}-${manager.manager_type}`}
                                            className="flex items-center gap-3 rounded-xl border bg-card p-2.5 transition-colors hover:bg-muted/50"
                                        >
                                            <div className="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                                                <IconMasksTheater className="size-4" />
                                            </div>
                                            <div className="min-w-0 flex-1">
                                                <p className="truncate text-sm font-semibold text-card-foreground">
                                                    {manager.display_name}
                                                </p>
                                            </div>
                                            <span
                                                className={cn(
                                                    "inline-flex shrink-0 items-center rounded-md border px-2 py-0.5 text-[10px] font-semibold",
                                                    "border-primary/20 bg-primary/10 text-primary",
                                                )}
                                            >
                                                {MATRIX_MANAGER_TYPES[manager.manager_type] ?? manager.manager_type}
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            </TabsContent>

                            <TabsContent value="related">
                                <div className="max-h-[280px] space-y-4 overflow-y-auto pt-2">
                                    <div className="space-y-2">
                                        <p className="text-xs font-semibold text-muted-foreground">
                                            زیرمجموعه‌ها ({directChildren.length})
                                        </p>
                                        {directChildren.length === 0 && (
                                            <p className="py-4 text-center text-sm text-muted-foreground">
                                                این نقش زیرمجموعه‌ای ندارد.
                                            </p>
                                        )}
                                        {directChildren.map((child) => (
                                            <div
                                                key={child.id}
                                                className="flex items-center gap-3 rounded-xl border bg-card p-2.5"
                                            >
                                                <div className="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                                                    <IconMasksTheater className="size-4" />
                                                </div>
                                                <p className="truncate text-sm font-semibold text-card-foreground">
                                                    {child.display_name}
                                                </p>
                                            </div>
                                        ))}
                                    </div>

                                    <Separator />

                                    <div className="space-y-2">
                                        <p className="text-xs font-semibold text-muted-foreground">
                                            زیرمجموعه‌های ماتریسی ({managedRoles.length})
                                        </p>
                                        {managedRoles.length === 0 && (
                                            <p className="py-4 text-center text-sm text-muted-foreground">
                                                این نقش بر نقش دیگری مدیریت ماتریسی ندارد.
                                            </p>
                                        )}
                                        {managedRoles.map((managed) => (
                                            <div
                                                key={managed.id}
                                                className="flex items-center gap-3 rounded-xl border bg-card p-2.5"
                                            >
                                                <div className="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                                                    <IconNetwork className="size-4" />
                                                </div>
                                                <div className="min-w-0 flex-1">
                                                    <p className="truncate text-sm font-semibold text-card-foreground">
                                                        {managed.display_name}
                                                    </p>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            </TabsContent>
                        </Tabs>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}
