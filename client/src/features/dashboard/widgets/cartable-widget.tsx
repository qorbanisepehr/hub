import { useState } from "react";
import { Link } from "@tanstack/react-router";
import {
    IconArrowLeft,
    IconChevronLeft,
    IconChevronRight,
    IconInbox,
} from "@tabler/icons-react";

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs";
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
    SOURCE_LABELS,
    STATUS_CONFIG,
    sourceLabel,
} from "@/features/reporting/constants";
import { SenderCell } from "@/features/reporting/sender-cell";
import type { UnifiedInboxItemDto } from "@/features/reporting/types";

/** Rows the dashboard card shows per page (the full page owns real options). */
const WIDGET_PER_PAGE = 5;

/** «همه» + one pill per known source, mirroring SOURCE_LABELS. */
const SOURCE_PILLS = [
    { value: "all", label: "همه" },
    ...Object.entries(SOURCE_LABELS).map(([value, label]) => ({
        value,
        label,
    })),
];

/**
 * Compact cartable card: the latest items, permission-gated. State is local
 * (the dashboard has no URL) — a سامانه pill and a lightweight pager feed
 * the same standard list contract the full cartable page speaks; deep
 * interaction stays there via «همه اقلام».
 */
export function CartableWidget() {
    const canView = usePermission(PERMISSIONS.REPORT_UNIFIED_INBOX_VIEW);

    const [source, setSource] = useState("all");
    const [page, setPage] = useState(1);

    const { data, isLoading, isError } = useUnifiedInbox({
        page,
        per_page: WIDGET_PER_PAGE,
        ...(source !== "all" && { source }),
    });

    if (!canView) return null;

    const items = data?.data ?? [];
    const meta = data?.meta;
    const lastPage = meta?.last_page ?? 1;

    const handleSourceChange = (value: string) => {
        setSource(value);
        setPage(1);
    };

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
                <div className="border-b px-4 pb-3 pt-1">
                    <Tabs value={source} onValueChange={handleSourceChange}>
                        <TabsList>
                            {SOURCE_PILLS.map((pill) => (
                                <TabsTrigger
                                    key={pill.value}
                                    value={pill.value}
                                    className="px-3 text-xs"
                                >
                                    {pill.label}
                                </TabsTrigger>
                            ))}
                        </TabsList>
                    </Tabs>
                </div>

                {isLoading ? (
                    <div className="space-y-3 p-4">
                        <Skeleton className="h-9 w-full" />
                        <Skeleton className="h-9 w-full" />
                        <Skeleton className="h-9 w-full" />
                    </div>
                ) : isError ? (
                    <EmptyState
                        icon={IconInbox}
                        message="دریافت اقلام ناموفق بود"
                    />
                ) : items.length === 0 ? (
                    <EmptyState
                        icon={IconInbox}
                        message={
                            source === "all"
                                ? "اقلامی برای نمایش وجود ندارد"
                                : "قلمی از این سامانه یافت نشد"
                        }
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

                {meta !== undefined && (
                    <div className="flex items-center justify-between border-t px-4 py-2">
                        <p className="text-xs text-muted-foreground">
                            صفحه {page.toLocaleString("fa-IR")} از{" "}
                            {Math.max(lastPage, 1).toLocaleString("fa-IR")}
                        </p>
                        <div className="flex items-center gap-1">
                            <Button
                                variant="outline"
                                className="size-7 p-0"
                                onClick={() => setPage((p) => Math.max(1, p - 1))}
                                disabled={page <= 1 || isLoading}
                            >
                                <span className="sr-only">صفحه قبل</span>
                                <IconChevronRight className="size-4 ltr:-scale-x-100" />
                            </Button>
                            <Button
                                variant="outline"
                                className="size-7 p-0"
                                onClick={() =>
                                    setPage((p) => Math.min(lastPage, p + 1))
                                }
                                disabled={page >= lastPage || isLoading}
                            >
                                <span className="sr-only">صفحه بعد</span>
                                <IconChevronLeft className="size-4 ltr:-scale-x-100" />
                            </Button>
                        </div>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
