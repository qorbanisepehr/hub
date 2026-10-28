import { useState } from "react";
import { Link } from "@tanstack/react-router";
import { IconArrowLeft, IconCalendarStats } from "@tabler/icons-react";

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/components/ui/table";
import { Skeleton } from "@/components/ui/skeleton";
import { EmptyState } from "@/components/layout";
import { toPersianDate, toPersianTime } from "@/lib/date-format";
import { PERMISSIONS } from "@/lib/permissions";
import { usePermission } from "@/features/auth";
import { useAttendanceReport } from "@/features/reporting/hooks";

/** Local-time `yyyy-MM-dd` (matches what the API accepts). */
function localIso(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, "0");
    const day = String(date.getDate()).padStart(2, "0");
    return `${year}-${month}-${day}`;
}

/**
 * Dashboard card: a fixed last-7-days window, newest first. No URL state
 * here — the full report page owns interactive filters/pagination.
 *
 * Computed once per mount rather than per render: `new Date()` is an impure
 * read that React Compiler refuses to run during render, and recomputing it
 * would also let the window slide if the card re-rendered at midnight.
 */
function useLastSevenDays(): { dateFrom: string; dateTo: string } {
    const [range] = useState(() => {
        const to = new Date();
        const from = new Date();
        from.setDate(from.getDate() - 6);

        return { dateFrom: localIso(from), dateTo: localIso(to) };
    });

    return range;
}

/** Attendance card: the actor's last 7 days, permission-gated. */
export function AttendanceWidget() {
    const canView = usePermission(PERMISSIONS.REPORT_ATTENDANCE_VIEW);

    const { dateFrom, dateTo } = useLastSevenDays();

    const { data, isLoading } = useAttendanceReport({
        date_from: dateFrom,
        date_to: dateTo,
        page: 1,
        per_page: 5,
        order: "desc",
    });

    if (!canView) return null;

    const records = data?.data ?? [];
    const noLinkedEmployee =
        !isLoading &&
        data !== undefined &&
        data.metadata.reason === "no_linked_employee";

    return (
        <Card>
            <CardHeader className="flex-row items-center justify-between">
                <CardTitle className="flex items-center gap-2">
                    <IconCalendarStats className="size-5" />
                    حضور و غیاب
                </CardTitle>
                <Link
                    to="/reports/attendance"
                    className="flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                >
                    گزارش کامل
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
                ) : noLinkedEmployee ? (
                    <EmptyState
                        icon={IconCalendarStats}
                        message="به حساب شما پروفایل کارمندی متصل نیست"
                    />
                ) : records.length === 0 ? (
                    <EmptyState
                        icon={IconCalendarStats}
                        message="برای این بازه سابقه‌ای ثبت نشده است"
                    />
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>تاریخ</TableHead>
                                <TableHead>ورود</TableHead>
                                <TableHead>خروج</TableHead>
                                <TableHead>کارکرد</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {records.map((record) => (
                                <TableRow key={record.date}>
                                    <TableCell className="text-sm font-medium">
                                        {toPersianDate(record.date)}
                                    </TableCell>
                                    <TableCell className="text-sm">
                                        {toPersianTime(record.entry_at)}
                                    </TableCell>
                                    <TableCell className="text-sm">
                                        {toPersianTime(record.exit_at)}
                                    </TableCell>
                                    <TableCell className="text-sm tabular-nums">
                                        {record.worked_minutes === null
                                            ? "—"
                                            : `${Math.floor(record.worked_minutes / 60)}:${String(record.worked_minutes % 60).padStart(2, "0")}`}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </CardContent>
        </Card>
    );
}
