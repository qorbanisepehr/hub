import { useParams } from "@tanstack/react-router";

import { useAuditLogDetail } from "@/features/audit/hooks";
import { toPersianDate } from "@/lib/date-format";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { ViewSkeleton } from "@/components/layout";
import { ErrorPage } from "@/components/layout";
import { PageLayout } from "@/components/layout";
import { PageHeader } from "@/components/layout";
import { BackButton } from "@/components/layout";
import { AuditDiffView } from "@/features/audit/components/audit-diff-view";
import {
    AUDIT_CATEGORY_LABELS,
    AUDIT_CATEGORY_VARIANTS,
    AUDIT_EVENT_LABELS,
    AUDIT_ACTOR_TYPE_LABELS,
} from "@/features/audit/constants";

export function AuditLogDetailPage() {
    const { logId } = useParams({ from: "/protected/audit/$logId" });
    const auditLogId = Number(logId);

    const {
        data: response,
        isLoading,
        isError,
    } = useAuditLogDetail(auditLogId);

    if (isLoading) {
        return <ViewSkeleton leftRows={6} columns={1} />;
    }

    if (isError || !response?.data) {
        return (
            <ErrorPage
                title="لاگ یافت نشد"
                homeTo="/audit"
                homeLabel="بازگشت به لاگ فعالیت"
            />
        );
    }

    const eventData = response.data;

    const eventLabel = AUDIT_EVENT_LABELS[eventData.event] ?? eventData.event;
    const categoryLabel =
        AUDIT_CATEGORY_LABELS[eventData.category] ?? eventData.category;

    return (
        <PageLayout>
            <PageHeader
                title="جزئیات رویداد"
                description={`${eventLabel} — ${categoryLabel}`}
            >
                <BackButton to="/audit" />
            </PageHeader>

            <div className="grid gap-6">
                <Card>
                    <CardHeader>
                        <CardTitle className="text-lg">اطلاعات کلی</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <p className="text-sm text-muted-foreground">
                                رویداد
                            </p>
                            <p className="font-medium">{eventLabel}</p>
                        </div>
                        <div>
                            <p className="text-sm text-muted-foreground">
                                دسته‌بندی
                            </p>
                            <Badge
                                variant={
                                    AUDIT_CATEGORY_VARIANTS[eventData.category]
                                }
                            >
                                {categoryLabel}
                            </Badge>
                        </div>
                        <div>
                            <p className="text-sm text-muted-foreground">
                                تاریخ
                            </p>
                            <p className="font-medium">
                                {toPersianDate(eventData.created_at)}
                            </p>
                        </div>
                        <div>
                            <p className="text-sm text-muted-foreground">
                                شناسه رویداد
                            </p>
                            <p className="font-mono text-xs">
                                {eventData.event_id}
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-lg">عامل</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <p className="text-sm text-muted-foreground">نوع</p>
                            <p className="font-medium">
                                {AUDIT_ACTOR_TYPE_LABELS[
                                    eventData.actor.type
                                ] ?? eventData.actor.type}
                            </p>
                        </div>
                        {eventData.actor.id && (
                            <div>
                                <p className="text-sm text-muted-foreground">
                                    شناسه کاربر
                                </p>
                                <p className="font-medium">
                                    #{eventData.actor.id}
                                </p>
                            </div>
                        )}
                        {eventData.actor.role?.name && (
                            <div>
                                <p className="text-sm text-muted-foreground">
                                    نقش
                                </p>
                                <p className="font-medium">
                                    {eventData.actor.role.name}
                                </p>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {eventData.description && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-lg">توضیحات</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p>{eventData.description}</p>
                        </CardContent>
                    </Card>
                )}

                {(eventData.changes?.old || eventData.changes?.new) && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-lg">تغییرات</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <AuditDiffView
                                old={eventData.changes.old}
                                new={eventData.changes.new}
                            />
                        </CardContent>
                    </Card>
                )}

                {eventData.request && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-lg">درخواست</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-2">
                            {eventData.request.method && (
                                <div>
                                    <p className="text-sm text-muted-foreground">
                                        متد
                                    </p>
                                    <Badge variant="outline">
                                        {eventData.request.method}
                                    </Badge>
                                </div>
                            )}
                            {eventData.request.url && (
                                <div>
                                    <p className="text-sm text-muted-foreground">
                                        آدرس
                                    </p>
                                    <p className="font-mono text-xs break-all">
                                        {eventData.request.url}
                                    </p>
                                </div>
                            )}
                            {eventData.request.ip_address && (
                                <div>
                                    <p className="text-sm text-muted-foreground">
                                        IP
                                    </p>
                                    <p className="font-mono text-xs">
                                        {eventData.request.ip_address}
                                    </p>
                                </div>
                            )}
                            {eventData.request.request_id && (
                                <div>
                                    <p className="text-sm text-muted-foreground">
                                        شناسه درخواست
                                    </p>
                                    <p className="font-mono text-xs">
                                        {eventData.request.request_id}
                                    </p>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                )}
            </div>
        </PageLayout>
    );
}
