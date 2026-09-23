import { useState } from "react";
import { useQueryClient } from "@tanstack/react-query";
import {
    IconCalendarOff,
    IconCalendarStats,
    IconRefresh,
} from "@tabler/icons-react";

import { useAttendanceReport } from "@/features/reporting/hooks";
import {
    EmptyState,
    ErrorSection,
    PageHeader,
    PageLayout,
} from "@/components/layout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import { DatePicker } from "@/components/ui/date-picker";
import { Skeleton } from "@/components/ui/skeleton";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/components/ui/table";
import { toPersianDate, toPersianTime } from "@/lib/date-format";
import { reportingKeys } from "@/lib/query-keys";
import type { AttendanceRecordDto } from "../types";

const FRESHNESS_LABELS: Record<string, string> = {
    live: "زنده",
    cached: "کش‌شده",
    snapshot: "اسنپ‌شات",
    synchronized: "همگام‌شده",
};

const SKELETON_ROWS = ["a", "b", "c", "d", "e"];

const faNumber = new Intl.NumberFormat("fa-IR");

/** Local-time `yyyy-MM-dd` (matches what DatePicker emits). */
function localIso(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, "0");
    const day = String(date.getDate()).padStart(2, "0");
    return `${year}-${month}-${day}`;
}

/** Default window: the last 7 days (the API caps the range at 92 days). */
function defaultFrom(): string {
    const date = new Date();
    date.setDate(date.getDate() - 6);
    return localIso(date);
}

function formatWorkedMinutes(minutes: number | null): string {
    if (minutes === null) return "—";
    const hours = Math.floor(minutes / 60);
    const rest = minutes % 60;
    if (hours === 0) return `${faNumber.format(rest)} دقیقه`;
    if (rest === 0) return `${faNumber.format(hours)} ساعت`;
    return `${faNumber.format(hours)} ساعت و ${faNumber.format(rest)} دقیقه`;
}

function AttendanceRows({ records }: { records: AttendanceRecordDto[] }) {
    return (
        <TableBody>
            {records.map((record) => (
                <TableRow key={record.date}>
                    <TableCell className="font-medium">
                        {toPersianDate(record.date)}
                    </TableCell>
                    <TableCell>{toPersianTime(record.entry_at)}</TableCell>
                    <TableCell>{toPersianTime(record.exit_at)}</TableCell>
                    <TableCell className="tabular-nums">
                        {formatWorkedMinutes(record.worked_minutes)}
                    </TableCell>
                </TableRow>
            ))}
        </TableBody>
    );
}

export function AttendanceReportPage() {
    const queryClient = useQueryClient();

    const [from, setFrom] = useState(defaultFrom);
    const [to, setTo] = useState(() => localIso(new Date()));

    const { data, isLoading, isError, isFetching, refetch } =
        useAttendanceReport({ from, to });

    // Keep the window valid after every pick so the query never fires an
    // inverted range (the API would 422 on from > to).
    const handleFromChange = (value: string) => {
        setFrom(value);
        if (value > to) setTo(value);
    };

    const handleToChange = (value: string) => {
        setTo(value);
        if (value < from) setFrom(value);
    };

    const refresh = () =>
        queryClient.invalidateQueries({ queryKey: reportingKeys.all });

    const noLinkedEmployee =
        !isLoading &&
        !isError &&
        data !== undefined &&
        data.metadata.reason === "no_linked_employee";

    const records = data?.data ?? [];
    const isEmpty = !isLoading && !isError && !noLinkedEmployee && records.length === 0;

    return (
        <PageLayout>
            <PageHeader
                title="حضور و غیاب"
                description="گزارش ورود و خروج شما از سیستم منبع خارجی"
            >
                <Button
                    variant="outline"
                    size="sm"
                    onClick={refresh}
                    disabled={isFetching}
                >
                    <IconRefresh className="size-4" />
                    تازه‌سازی
                </Button>
            </PageHeader>

            <Card>
                <CardHeader>
                    <CardTitle>بازه گزارش</CardTitle>
                    <CardDescription>حداکثر بازه مجاز ۹۲ روز است</CardDescription>
                </CardHeader>
                <CardContent className="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div className="space-y-2">
                        <span className="text-sm text-muted-foreground">
                            از تاریخ
                        </span>
                        <DatePicker
                            value={from}
                            onChange={handleFromChange}
                            disabled={isLoading}
                        />
                    </div>
                    <div className="space-y-2">
                        <span className="text-sm text-muted-foreground">
                            تا تاریخ
                        </span>
                        <DatePicker
                            value={to}
                            onChange={handleToChange}
                            disabled={isLoading}
                        />
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <div className="space-y-1">
                            <CardTitle>سابقه حضور</CardTitle>
                            {data && (
                                <CardDescription>
                                    آخرین به‌روزرسانی:{" "}
                                    {toPersianDate(data.generated_at)}{" "}
                                    {toPersianTime(data.generated_at)}
                                </CardDescription>
                            )}
                        </div>
                        {data && (
                            <Badge
                                variant={
                                    data.freshness === "live"
                                        ? "success"
                                        : "secondary"
                                }
                            >
                                <IconCalendarStats className="size-3" />
                                {FRESHNESS_LABELS[data.freshness] ??
                                    data.freshness}
                            </Badge>
                        )}
                    </div>
                </CardHeader>
                <CardContent className="space-y-4">
                    {data && data.warnings.length > 0 && (
                        <div className="flex flex-wrap gap-2">
                            {data.warnings.map((warning) => (
                                <Badge
                                    key={`${warning.source ?? "source"}-${warning.code ?? "warning"}`}
                                    variant="warning"
                                >
                                    {warning.source
                                        ? `منبع ${warning.source} در دسترس نیست`
                                        : "هشدار منبع داده"}
                                </Badge>
                            ))}
                        </div>
                    )}

                    {isLoading ? (
                        <div className="space-y-3 py-2">
                            {SKELETON_ROWS.map((row) => (
                                <Skeleton key={row} className="h-9 w-full" />
                            ))}
                        </div>
                    ) : isError ? (
                        <ErrorSection
                            description="دریافت داده از سیستم منبع خارجی با خطا مواجه شد."
                            onRetry={() => refetch()}
                        />
                    ) : noLinkedEmployee ? (
                        <EmptyState
                            icon={IconCalendarOff}
                            message="به حساب کاربری شما پروفایل کارمندی متصل نیست."
                        />
                    ) : isEmpty ? (
                        <EmptyState
                            icon={IconCalendarStats}
                            message="برای این بازه سابقه‌ای ثبت نشده است."
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
                            <AttendanceRows records={records} />
                        </Table>
                    )}
                </CardContent>
            </Card>
        </PageLayout>
    );
}
