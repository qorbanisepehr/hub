import { useParams } from "@tanstack/react-router";
import { IconCalendarStats } from "@tabler/icons-react";

import { useBoardMeeting } from "../hooks";
import { toPersianDate } from "@/lib/date-format";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { EmptyState } from "@/components/layout";
import { ErrorPage, PageHeader } from "@/components/layout";
import { PageLayout } from "@/components/layout";

/**
 * Board meeting detail (Phase 3): meta block + the two minutes channels
 * (derived from letters, and the source's own board form when present) +
 * tags. Phase 4 adds documents and print through the Document system.
 */
export function BoardMeetingDetailPage() {
    const { meetingId } = useParams({ from: "/protected/board-meetings/$meetingId" });

    const { data: meeting, isLoading, isError } = useBoardMeeting(meetingId);

    if (isLoading) {
        return (
            <PageLayout>
                <div className="space-y-4">
                    <Skeleton className="h-10 w-64" />
                    <Skeleton className="h-40 w-full" />
                    <Skeleton className="h-60 w-full" />
                </div>
            </PageLayout>
        );
    }

    if (isError || !meeting) {
        return (
            <ErrorPage
                title="جلسه یافت نشد"
                homeTo="/board-meetings"
                homeLabel="بازگشت به جلسات"
            />
        );
    }

    const minutesFromLetters = meeting.minutes_from_letters ?? [];
    const minutesFromHeyat = meeting.minutes_from_heyat_modire ?? [];

    return (
        <PageLayout>
            <PageHeader
                title={`جلسه ${toPersianDate(meeting.date ?? meeting.pdate)}`}
                description={meeting.number ?? undefined}
                backTo="/board-meetings"
            />

            <div className="grid gap-6">
                <Card>
                    <CardHeader>
                        <CardTitle className="text-lg">اطلاعات جلسه</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <p className="text-sm text-muted-foreground">
                                تاریخ جلسه
                            </p>
                            <p className="font-medium">
                                {toPersianDate(meeting.date ?? meeting.pdate)}
                            </p>
                        </div>
                        <div>
                            <p className="text-sm text-muted-foreground">
                                شماره جلسه
                            </p>
                            <p className="font-medium" dir="rtl">
                                {meeting.number ?? "—"}
                            </p>
                        </div>
                        <div>
                            <p className="text-sm text-muted-foreground">
                                برچسب‌ها
                            </p>
                            <div className="flex flex-wrap gap-1 pt-1">
                                {meeting.tags.length > 0 ? (
                                    meeting.tags.map((tag) => (
                                        <Badge key={tag.slug} variant="outline">
                                            {tag.name}
                                        </Badge>
                                    ))
                                ) : (
                                    <span className="text-sm">—</span>
                                )}
                            </div>
                        </div>
                        <div>
                            <p className="text-sm text-muted-foreground">مدارک</p>
                            <p className="font-medium">
                                {meeting.documents.length > 0
                                    ? meeting.documents.length.toLocaleString("fa-IR")
                                    : "—"}
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-lg">
                            صورتجلسه (استخراج از نامه‌ها)
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {minutesFromLetters.length > 0 ? (
                            <ol className="list-decimal space-y-3 ps-5">
                                {minutesFromLetters.map((minute, index) => (
                                    // oxlint-disable-next-line react/no-array-index-key -- static minute paragraphs have no stable data identity
                                    <li key={index} className="text-sm leading-7">
                                        {minute}
                                    </li>
                                ))}
                            </ol>
                        ) : (
                            <EmptyState
                                icon={IconCalendarStats}
                                message="صورتجلسه‌ای از نامه‌ها استخراج نشده است"
                            />
                        )}
                    </CardContent>
                </Card>

                {minutesFromHeyat.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-lg">
                                شرح جلسه (فرم هیئت مدیره)
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {minutesFromHeyat.map((text, index) => (
                                // oxlint-disable-next-line react/no-array-index-key -- static session paragraphs have no stable data identity
                                <p key={index} className="text-sm leading-7">
                                    {text}
                                </p>
                            ))}
                        </CardContent>
                    </Card>
                )}
            </div>
        </PageLayout>
    );
}
