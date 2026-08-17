import { useState } from "react";
import {
    IconBan,
    IconCheck,
    IconCircleCheck,
    IconDownload,
    IconHierarchy2,
    IconUserCheck,
    IconUserOff,
    IconUsers,
} from "@tabler/icons-react";
import { PageLayout } from "@/components/layout";
import { useRoleChart } from "@/features/rbac/hooks/use-roles";
import { RoleOrgChart } from "@/features/rbac/components/org-chart";
import { Button } from "@/components/ui/button";
import type {
    ChartStatusFilter,
    ChartUserFilter,
    ChartViewMode,
} from "@/features/rbac/types";
import { ExportChartDialog } from "../components/org-chart/export-chart-dialog";

export function RoleChartPage() {
    const { data, isLoading, isError, refetch } = useRoleChart();
    const [viewMode, setViewMode] = useState<ChartViewMode>("users");
    const [userFilter, setUserFilter] = useState<ChartUserFilter>("all");
    const [statusFilter, setStatusFilter] = useState<ChartStatusFilter>("all");
    const [exportOpen, setExportOpen] = useState(false);

    return (
        <PageLayout>
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">
                        نقشه سازمانی نقش‌ها
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        ساختار سلسله‌مراتبی و روابط ماتریسی بین نقش‌ها
                    </p>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <div className="flex items-center gap-1 rounded-lg border bg-background p-1 shadow-sm">
                        <Button
                            variant={viewMode === "users" ? "default" : "ghost"}
                            size="sm"
                            onClick={() => setViewMode("users")}
                            className="gap-1.5"
                        >
                            <IconUsers className="size-3.5" />
                            کاربران
                        </Button>
                        <Button
                            variant={viewMode === "roles" ? "default" : "ghost"}
                            size="sm"
                            onClick={() => setViewMode("roles")}
                            className="gap-1.5"
                        >
                            <IconHierarchy2 className="size-3.5" />
                            فقط نقش‌ها
                        </Button>
                    </div>
                    <div className="flex items-center gap-1 rounded-lg border bg-background p-1 shadow-sm">
                        {(
                            [
                                {
                                    value: "all",
                                    label: "همه",
                                    icon: <IconUsers className="size-3.5" />,
                                },
                                {
                                    value: "with",
                                    label: "دارای کاربر",
                                    icon: (
                                        <IconUserCheck className="size-3.5" />
                                    ),
                                },
                                {
                                    value: "without",
                                    label: "بدون کاربر",
                                    icon: <IconUserOff className="size-3.5" />,
                                },
                            ] as const
                        ).map((option) => (
                            <Button
                                key={option.value}
                                variant={
                                    userFilter === option.value
                                        ? "default"
                                        : "ghost"
                                }
                                size="sm"
                                onClick={() => setUserFilter(option.value)}
                                className="gap-1.5"
                            >
                                {option.icon}
                                {option.label}
                            </Button>
                        ))}
                    </div>
                    <div className="flex items-center gap-1 rounded-lg border bg-background p-1 shadow-sm">
                        {(
                            [
                                {
                                    value: "all",
                                    label: "همه",
                                    icon: <IconCheck className="size-3.5" />,
                                },
                                {
                                    value: "active",
                                    label: "فعال",
                                    icon: (
                                        <IconCircleCheck className="size-3.5" />
                                    ),
                                },
                                {
                                    value: "inactive",
                                    label: "غیرفعال",
                                    icon: <IconBan className="size-3.5" />,
                                },
                            ] as const
                        ).map((option) => (
                            <Button
                                key={option.value}
                                variant={
                                    statusFilter === option.value
                                        ? "default"
                                        : "ghost"
                                }
                                size="sm"
                                onClick={() => setStatusFilter(option.value)}
                                className="gap-1.5"
                            >
                                {option.icon}
                                {option.label}
                            </Button>
                        ))}
                    </div>
                    <Button
                        variant="outline"
                        onClick={() => setExportOpen(true)}
                    >
                        <IconDownload className="size-4" />
                        خروجی
                    </Button>
                </div>
            </div>

            <div className="relative min-h-150 flex-1">
                <RoleOrgChart
                    roles={data ?? []}
                    isLoading={isLoading}
                    isError={isError}
                    onRetry={() => refetch()}
                    viewMode={viewMode}
                    userFilter={userFilter}
                    statusFilter={statusFilter}
                />
                <ExportChartDialog
                    open={exportOpen}
                    onOpenChange={setExportOpen}
                />
            </div>
        </PageLayout>
    );
}
