import { Link } from "@tanstack/react-router";
import { IconArrowLeft, IconInbox } from "@tabler/icons-react";

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/components/ui/table";
import { toPersianDate, toPersianTime } from "@/lib/date-format";
import { PERMISSIONS } from "@/lib/permissions";
import { usePermission } from "@/features/auth";
import { Skeleton } from "@/components/ui/skeleton";
import { EmptyState } from "@/components/layout";
import { useUnifiedInbox } from "@/features/reporting/hooks";
import {
    CARTABLE_ACTION_LABELS,
    STATUS_CONFIG,
    sourceLabel,
} from "@/features/reporting/constants";
import { SenderCell } from "@/features/reporting/sender-cell";
import type { UnifiedInboxItemDto } from "@/features/reporting/types";

/** Compact cartable card: the latest items, permission-gated. */
export function CartableWidget() {
    const canView = usePermission(PERMISSIONS.REPORT_UNIFIED_INBOX_VIEW);

    // Dashboard card: a fixed first page of the live work list (no URL
    // state here — the full page owns interactive filters/pagination).
    const { data, isLoading } = useUnifiedInbox({
        page: 1,
        per_page: 5,
    });

    if (!canView) return null;

    const items = data?.data ?? [];

    return (
        <Card>
            <CardHeader className="flex-row items-center justify-between">
                <CardTitle className="flex items-center gap-2">
                    <IconInbox className="size-5" />
                    کارتابل
                </CardTitle>
                <Link
                    to="/reports/unified-inbox"
                    className="flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                >
                    همه اقلام
                    <IconArrowLeft className="size-4" />
                </Link>
            </CardHeader>
            <CardContent className="p-0">
                {isLoading ? (
                    <div className="space-y-3 p-4">
                        <Skeleton className="h-9 w-full" />
                        <Skeleton className="h-9 w-full" />
                        <Skeleton className="h-9 w-full" />
                    </div>
                ) : items.length === 0 ? (
                    <EmptyState
                        icon={IconInbox}
                        message="اقلامی برای نمایش وجود ندارد"
                    />
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>سامانه</TableHead>
                                <TableHead>فرستنده</TableHead>
                                <TableHead>عنوان</TableHead>
                                <TableHead>تاریخ دریافت</TableHead>
                                <TableHead>کارتابل</TableHead>
                                <TableHead>وضعیت</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {items.map((item: UnifiedInboxItemDto) => {
                                const status =
                                    STATUS_CONFIG[item.status] ?? {
                                        label: item.status,
                                        variant: "secondary" as const,
                                    };

                                return (
                                    <TableRow key={item.external_reference}>
                                        <TableCell>
                                            <Badge variant="outline">
                                                {sourceLabel(item.source)}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            <SenderCell item={item} />
                                        </TableCell>
                                        <TableCell className="max-w-48 truncate text-sm">
                                            {item.title}
                                        </TableCell>
                                        <TableCell className="text-sm whitespace-nowrap text-muted-foreground">
                                            {toPersianDate(item.received_at)}{" "}
                                            {toPersianTime(item.received_at)}
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant="outline">
                                                {CARTABLE_ACTION_LABELS[item.cartable_action] ??
                                                    item.cartable_action}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant={status.variant}>
                                                {status.label}
                                            </Badge>
                                        </TableCell>
                                    </TableRow>
                                );
                            })}
                        </TableBody>
                    </Table>
                )}
            </CardContent>
        </Card>
    );
}
