import { useParams } from "@tanstack/react-router";
import { IconCalendarStats } from "@tabler/icons-react";

import { useBoardMeeting } from "../hooks";
import { toPersianDate } from "@/lib/date-format";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { EmptyState, ErrorPage, PageHeader, PageLayout } from "@/components/layout";
import { MeetingArchiveCard } from "../components/meeting-archive-card";
import { MeetingDocumentsCard } from "../components/meeting-documents-card";
import { MeetingTagsCard } from "../components/meeting-tags-card";

/**
 * Board meeting detail (Phase 4): meta block + the two minutes channels
 * (derived from letters, and the source's own board form when present) +
 * the tag editor (K3) and the documents card on the hub Document system (K4).
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
                description={
                    (meeting.number ?? "").trim() !== ""
                        ? meeting.number ?? undefined
                        : undefined
                }
                backTo="/board-meetings"
            />

            <div className="grid gap-6">
                <Card>
                    <CardHeader>
                        <CardTitle className="text-lg">اطلاعات جلسه</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <p className="text-muted-foreground text-sm">
                                تاریخ جلسه
                            </p>
                            <p className="font-medium">
                                {toPersianDate(meeting.date ?? meeting.pdate)}
                            </p>
                        </div>
                        <div>
                            <p className="text-muted-foreground text-sm">
                                شماره جلسه
                            </p>
                            <p className="font-medium" dir="rtl">
                                {(meeting.number ?? "").trim() !== ""
                                    ? meeting.number
                                    : "—"}
                            </p>
                        </div>
                        <div>
                            <p className="text-muted-foreground text-sm">
                                برچسب‌ها
                            </p>
                            <p className="font-medium">
                                {meeting.tags.length > 0
                                    ? meeting.tags
                                          .map((tag) => tag.name)
                                          .join("، ")
                                    : "—"}
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <MeetingDocumentsCard meetingId={String(meetingId)} />

                <MeetingArchiveCard filenames={meeting.documents ?? []} />

                <MeetingTagsCard
                    meetingId={String(meetingId)}
                    tags={meeting.tags}
                />

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
