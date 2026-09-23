import { useMemo, useState } from "react";
import { useQueryClient } from "@tanstack/react-query";
import {
    IconInbox,
    IconInboxOff,
    IconRefresh,
} from "@tabler/icons-react";

import { useUnifiedInbox } from "@/features/reporting/hooks";
import { EmptyState, ErrorSection, PageHeader, PageLayout } from "@/components/layout";
import { UserAvatar } from "@/components/shared/user-avatar";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import { DatePicker } from "@/components/ui/date-picker";
import { Label } from "@/components/ui/label";
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
import type { UnifiedInboxItemDto } from "../types";

const SKELETON_ROWS = ["a", "b", "c", "d", "e"];

/** Known source labels; unknown keys fall back to the raw key. */
const SOURCE_LABELS: Record<string, string> = {
    automation: "اتوماسیون اداری",
    rahkaran: "راهکاران",
    bizagi: "بیزگی",
};

const ALL_SOURCES = Object.keys(SOURCE_LABELS);

const STATUS_CONFIG: Record<string, { label: string; variant: "secondary" | "warning" | "success" }> = {
    unseen: { label: "دیده نشده", variant: "warning" },
    seen: { label: "دیده شده", variant: "secondary" },
    done: { label: "انجام شده", variant: "success" },
};

const CARTABLE_ACTION_LABELS: Record<string, string> = {
    action_required: "جهت اقدام",
    signature: "جهت امضا",
    information: "جهت اطلاع",
};

function sourceLabel(key: string): string {
    return SOURCE_LABELS[key] ?? key;
}

/**
 * Sender cell per the کارتابل sample: the shared UserAvatar + name, with
 * the organizational role as the secondary line when the sender was
 * resolved to an HR Hub user; external senders show the raw name with a
 * generic badge.
 */
function SenderCell({ item }: { item: UnifiedInboxItemDto }) {
    const { sender } = item;

    return (
        <div className="flex items-center gap-2 min-w-0">
            <UserAvatar
                name={sender.display_name}
                avatarUrl={sender.matched ? sender.avatar_url : null}
                size="sm"
            />
            <div className="grid min-w-0 leading-tight">
                <span className="truncate text-sm font-medium">
                    {sender.matched ? sender.display_name : sender.external_name}
                </span>
                {sender.matched && sender.org_role ? (
                    <span className="truncate text-xs text-muted-foreground">
                        {sender.org_role}
                    </span>
                ) : null}
                {!sender.matched ? (
                    <span className="text-xs text-muted-foreground">
                        کاربر خارجی
                    </span>
                ) : null}
            </div>
        </div>
    );
}

export function UnifiedInboxPage() {
    const queryClient = useQueryClient();

    // Open-ended by default (live کارتابل): filters narrow the view.
    const [from, setFrom] = useState<string | null>(null);
    const [to, setTo] = useState<string | null>(null);
    const [selectedSources, setSelectedSources] = useState<string[]>([]);
    const [viewMode, setViewMode] = useState<"flat" | "grouped">("flat");

    const params = useMemo(
        () => ({
            from: from ?? undefined,
            to: to ?? undefined,
            sources: selectedSources.length > 0 ? selectedSources : undefined,
        }),
        [from, to, selectedSources],
    );

    const { data, isLoading, isError, isFetching, refetch } =
        useUnifiedInbox(params);

    const items = useMemo(() => data?.data ?? [], [data]);

    const groups = useMemo(() => {
        if (viewMode !== "grouped") return [];

        const map = new Map<string, UnifiedInboxItemDto[]>();

        for (const item of items) {
            const list = map.get(item.source) ?? [];
            list.push(item);
            map.set(item.source, list);
        }

        return [...map.entries()];
    }, [items, viewMode]);

    // Keep the window valid after every pick (API 422s on inverted ranges).
    const handleFromChange = (value: string) => {
        setFrom(value);
        if (to && value > to) setTo(value);
    };

    const handleToChange = (value: string) => {
        setTo(value);
        if (from && value < from) setFrom(value);
    };

    const toggleSource = (key: string) => {
        setSelectedSources((prev) =>
            prev.includes(key) ? prev.filter((k) => k !== key) : [...prev, key],
        );
    };

    const refresh = () =>
        queryClient.invalidateQueries({ queryKey: reportingKeys.all });

    const hasWarnings = (data?.warnings.length ?? 0) > 0;
    const isEmpty = !isLoading && !isError && items.length === 0;

    return (
        <PageLayout>
            <PageHeader
                title="کارتابل"
                description="اقلام دریافتی از سامانه‌های خارجی در یک نمای واحد"
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
                    <CardTitle>فیلترها</CardTitle>
                    <CardDescription>
                        به‌صورت پیش‌فرض آخرین اقلام همه سامانه‌ها نمایش داده می‌شود
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div className="space-y-2">
                            <span className="text-sm text-muted-foreground">
                                از تاریخ (اختیاری)
                            </span>
                            <DatePicker
                                value={from ?? undefined}
                                onChange={handleFromChange}
                                disabled={isLoading}
                            />
                        </div>
                        <div className="space-y-2">
                            <span className="text-sm text-muted-foreground">
                                تا تاریخ (اختیاری)
                            </span>
                            <DatePicker
                                value={to ?? undefined}
                                onChange={handleToChange}
                                disabled={isLoading}
                            />
                        </div>
                    </div>

                    <div className="space-y-2">
                        <span className="text-sm text-muted-foreground">
                            سامانه‌ها
                        </span>
                        <div className="flex flex-wrap gap-4">
                            {ALL_SOURCES.map((key) => (
                                <Label
                                    key={key}
                                    className="flex items-center gap-2 text-sm font-normal"
                                >
                                    <Checkbox
                                        checked={selectedSources.includes(key)}
                                        onCheckedChange={() => toggleSource(key)}
                                    />
                                    {sourceLabel(key)}
                                </Label>
                            ))}
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <div className="space-y-1">
                            <CardTitle>اقلام کارتابل</CardTitle>
                            {data && (
                                <CardDescription>
                                    {data.pagination.total} قلم
                                    {data.pagination.has_more
                                        ? " (نمایش بخشی از اقلام)"
                                        : ""}
                                </CardDescription>
                            )}
                        </div>
                        <div className="flex items-center gap-2">
                            {hasWarnings && (
                                <Badge variant="warning">
                                    برخی سامانه‌ها در دسترس نیستند
                                </Badge>
                            )}
                            <div className="flex rounded-lg border p-0.5 text-sm">
                                <button
                                    type="button"
                                    onClick={() => setViewMode("flat")}
                                    className={
                                        viewMode === "flat"
                                            ? "rounded-md bg-muted px-2 py-1 font-medium"
                                            : "rounded-md px-2 py-1 text-muted-foreground"
                                    }
                                >
                                    همه
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setViewMode("grouped")}
                                    className={
                                        viewMode === "grouped"
                                            ? "rounded-md bg-muted px-2 py-1 font-medium"
                                            : "rounded-md px-2 py-1 text-muted-foreground"
                                    }
                                >
                                    گروه‌بندی بر اساس سامانه
                                </button>
                            </div>
                        </div>
                    </div>
                </CardHeader>
                <CardContent className="space-y-4">
                    {data &&
                        data.warnings.map((warning) => (
                            <Badge
                                key={`${warning.source ?? "source"}-${warning.code ?? "warning"}`}
                                variant="warning"
                            >
                                {warning.source
                                    ? `منبع ${sourceLabel(warning.source)} در دسترس نیست`
                                    : "هشدار منبع داده"}
                            </Badge>
                        ))}

                    {isLoading ? (
                        <div className="space-y-3 py-2">
                            {SKELETON_ROWS.map((row) => (
                                <Skeleton key={row} className="h-9 w-full" />
                            ))}
                        </div>
                    ) : isError ? (
                        <ErrorSection
                            description="دریافت اقلام کارتابل با خطا مواجه شد."
                            onRetry={() => refetch()}
                        />
                    ) : isEmpty ? (
                        <EmptyState
                            icon={IconInboxOff}
                            message="اقلامی برای نمایش وجود ندارد."
                        />
                    ) : viewMode === "flat" ? (
                        <CartableTable items={items} />
                    ) : (
                        <div className="space-y-6">
                            {groups.map(([sourceKey, groupItems]) => (
                                <div key={sourceKey} className="space-y-2">
                                    <div className="flex items-center gap-2">
                                        <Badge variant="outline">
                                            {sourceLabel(sourceKey)}
                                        </Badge>
                                        <span className="text-xs text-muted-foreground">
                                            {groupItems.length} قلم
                                        </span>
                                    </div>
                                    <CartableTable items={groupItems} />
                                </div>
                            ))}
                        </div>
                    )}
                </CardContent>
            </Card>
        </PageLayout>
    );
}

function CartableTable({ items }: { items: UnifiedInboxItemDto[] }) {
    if (items.length === 0) {
        return (
            <div className="flex items-center gap-2 rounded-lg border border-dashed p-4 text-sm text-muted-foreground">
                <IconInbox className="size-4" />
                قلمی در این گروه نیست.
            </div>
        );
    }

    return (
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>فرستنده</TableHead>
                    <TableHead>شماره مدرک</TableHead>
                    <TableHead>عنوان</TableHead>
                    <TableHead>نوع مدرک</TableHead>
                    <TableHead>تاریخ دریافت</TableHead>
                    <TableHead>کارتابل</TableHead>
                    <TableHead>وضعیت</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {items.map((item) => {
                    const status = STATUS_CONFIG[item.status] ?? {
                        label: item.status,
                        variant: "secondary" as const,
                    };

                    return (
                        <TableRow key={item.external_reference}>
                            <TableCell>
                                <SenderCell item={item} />
                            </TableCell>
                            <TableCell dir="ltr" className="text-sm">
                                {item.document_number}
                            </TableCell>
                            <TableCell className="max-w-64 truncate text-sm">
                                {item.title}
                            </TableCell>
                            <TableCell className="text-sm text-muted-foreground">
                                {item.document_type}
                            </TableCell>
                            <TableCell className="text-sm whitespace-nowrap">
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
    );
}
