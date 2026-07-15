import { IconPalette } from "@tabler/icons-react";

import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Skeleton } from "@/components/ui/skeleton";
import { usePermissions } from "@/features/rbac/hooks/use-permissions";
import { PageLayout } from "@/components/shared/page-layout";
import { EmptyState } from "@/components/shared/empty-state";
import { ErrorSection } from "@/components/shared/error-section";

function PermissionsSkeleton() {
    return (
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            {Array.from({ length: 6 }).map((_, i) => (
                <Skeleton key={i} className="h-40 w-full" />
            ))}
        </div>
    );
}

export function PermissionsPage() {
    const { data, isLoading, isError } = usePermissions();

    return (
        <PageLayout>
            <div>
                <h1 className="text-2xl font-bold tracking-tight">
                    تنظیمات
                </h1>
                <p className="text-sm text-muted-foreground mt-1">
                    نمای گروه‌ها و مجوزهای سیستم
                </p>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <IconPalette className="size-5" />
                        گروه‌های مجوز
                    </CardTitle>
                    {data && (
                        <CardDescription>
                            مجموع {data.length} گروه مجوز
                        </CardDescription>
                    )}
                </CardHeader>
                <CardContent>
                    {isLoading ? (
                        <PermissionsSkeleton />
                    ) : isError ? (
                        <ErrorSection icon={IconPalette} />
                    ) : !data?.length ? (
                        <EmptyState icon={IconPalette} message="هیچ گروه مجوزی یافت نشد" />
                    ) : (
                        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                            {data.map((group) => (
                                <Card key={group.id}>
                                    <CardHeader className="pb-3">
                                        <CardTitle className="text-base">
                                            {group.name}
                                        </CardTitle>
                                        <CardDescription dir="ltr">
                                            {group.slug}
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="flex flex-wrap gap-1">
                                            {group.permissions?.map((perm) => (
                                                <Badge key={perm.id} variant="secondary" className="text-xs">
                                                    {perm.display_name}
                                                </Badge>
                                            ))}
                                        </div>
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                    )}
                </CardContent>
            </Card>
        </PageLayout>
    );
}
