import { useState } from "react";
import { IconSettings } from "@tabler/icons-react";
import { toast } from "sonner";

import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Switch } from "@/components/ui/switch";
import { Badge } from "@/components/ui/badge";
import { useRetentionPolicies } from "@/features/audit/hooks";
import { AUDIT_CATEGORY_LABELS } from "@/features/audit/constants";
import { PageLayout, PageHeader, ErrorSection, PageSkeleton } from "@/components/layout";

export function AuditRetentionPage() {
    const { data: response, isLoading, isError, error } = useRetentionPolicies();
    const policies = response?.data ?? [];

    if (isLoading) {
        return <PageSkeleton rows={6} />;
    }

    if (isError) {
        return (
            <PageLayout>
                <PageHeader
                    title="سیاست نگهداری"
                    description="مدیریت مدت زمان نگهداری رویدادها"
                    icon={IconSettings}
                />
                <ErrorSection error={error} />
            </PageLayout>
        );
    }

    return (
        <PageLayout>
            <PageHeader
                title="سیاست نگهداری"
                description="مدیریت مدت زمان نگهداری رویدادها"
                icon={IconSettings}
            />

            <div className="grid gap-4">
                {policies.map((policy) => (
                    <Card key={policy.id}>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-base font-medium">
                                {policy.name}
                            </CardTitle>
                            <div className="flex items-center gap-2">
                                {policy.category && (
                                    <Badge variant="outline">
                                        {AUDIT_CATEGORY_LABELS[policy.category] ?? policy.category}
                                    </Badge>
                                )}
                                <Switch
                                    checked={policy.is_active}
                                    disabled
                                />
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div className="grid gap-2 text-sm text-muted-foreground sm:grid-cols-3">
                                <div>
                                    <span className="font-medium text-foreground">نگهداری: </span>
                                    {policy.retention_days} روز
                                </div>
                                {policy.archive_after_days && (
                                    <div>
                                        <span className="font-medium text-foreground">آرشیو: </span>
                                        {policy.archive_after_days} روز
                                    </div>
                                )}
                                {policy.event && (
                                    <div>
                                        <span className="font-medium text-foreground">رویداد: </span>
                                        {policy.event}
                                    </div>
                                )}
                            </div>
                        </CardContent>
                    </Card>
                ))}

                {policies.length === 0 && (
                    <Card>
                        <CardContent className="py-8 text-center text-muted-foreground">
                            هیچ سیاست نگهداری تعریف نشده است
                        </CardContent>
                    </Card>
                )}
            </div>
        </PageLayout>
    );
}
