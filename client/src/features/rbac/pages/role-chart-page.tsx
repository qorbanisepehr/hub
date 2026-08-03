import { IconHierarchy2 } from "@tabler/icons-react";
import { PageLayout } from "@/components/shared/page-layout";
import { useRoleChart } from "@/features/rbac/hooks/use-roles";
import { RoleOrgChart } from "@/features/rbac/components/org-chart/RoleOrgChart";

export function RoleChartPage() {
    const { data, isLoading, isError, refetch } = useRoleChart();

    return (
        <PageLayout>
            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">نقشه سازمانی نقش‌ها</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        ساختار سلسله‌مراتبی و روابط ماتریسی بین نقش‌ها
                    </p>
                </div>
                <div className="hidden items-center gap-2 text-sm text-muted-foreground md:flex">
                    <IconHierarchy2 className="size-5" />
                    <span>برای جزئیات روی هر نقش کلیک کنید</span>
                </div>
            </div>

            <div className="relative min-h-[600px] flex-1">
                <RoleOrgChart
                    roles={data ?? []}
                    isLoading={isLoading}
                    isError={isError}
                    onRetry={() => refetch()}
                />
            </div>
        </PageLayout>
    );
}
