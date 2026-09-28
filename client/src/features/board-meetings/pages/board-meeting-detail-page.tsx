import { useParams } from "@tanstack/react-router";
import { IconCalendarStats } from "@tabler/icons-react";

import { useBoardMeeting } from "../hooks";
import { toPersianDate } from "@/lib/date-format";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { EmptyState, ErrorPage, PageHeader, PageLayout } from "@/components/layout";
import { MeetingArchiveCard } from "../components/meeting-archive-card";
import { MeetingDocumentsCard } from "../components/meeting-documents-card";
import { MeetingTagsCard } from "../components/meeting-tags-card";

const STATUS_LABELS: Record<string, string> = {
    scheduled: "برگزار نشده",
    held: "برگزار شده",
    archived: "بایگانی",
};

/**
 * Board meeting detail (fresh shape, doc 060): meta block with status,
 * the single minutes narrative, structured agenda + attendees (JSONB
 * sub-structure), the tag editor (K3), documents card (K4) and the
 * read-only source archive listing.
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

    const agenda = meeting.agenda ?? [];
    const attendees = meeting.attendees ?? [];

    return (
        <PageLayout>
            <PageHeader
                title={`جلسه ${toPersianDate(meeting.date)}`}
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
                                {toPersianDate(meeting.date)}
                            </p>
                        </div>
                        <div>
                            <p className="text-muted-foreground text-sm">
                                وضعیت
                            </p>
                            <p className="font-medium">
                                <Badge variant="outline">
                                    {STATUS_LABELS[meeting.status] ?? meeting.status}
                                </Badge>
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

                {agenda.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-lg">دستور جلسه</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ol className="list-decimal space-y-2 ps-5">
                                {agenda.map((item, index) => (
                                    // oxlint-disable-next-line react/no-array-index-key -- static agenda entries have no stable identity
                                    <li key={index} className="text-sm leading-7">
                                        {item.title}
                                    </li>
                                ))}
                            </ol>
                        </CardContent>
                    </Card>
                )}

                {attendees.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-lg">حاضرین</CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-wrap gap-2">
                            {attendees.map((attendee, index) => (
                                // oxlint-disable-next-line react/no-array-index-key -- static attendee entries have no stable identity
                                <Badge key={index} variant="outline">
                                    {attendee.name}
                                </Badge>
                            ))}
                        </CardContent>
                    </Card>
                )}

                <MeetingDocumentsCard meetingId={String(meetingId)} />

                <MeetingArchiveCard filenames={meeting.documents ?? []} />

                <MeetingTagsCard
                    meetingId={String(meetingId)}
                    tags={meeting.tags}
                />

                <Card>
                    <CardHeader>
                        <CardTitle className="text-lg">صورتجلسه</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {meeting.minutes_text ? (
                            <p className="text-sm leading-7 whitespace-pre-line">
                                {meeting.minutes_text}
                            </p>
                        ) : (
                            <EmptyState
                                icon={IconCalendarStats}
                                message="صورتجلسه‌ای برای این جلسه ثبت نشده است"
                            />
                        )}
                    </CardContent>
                </Card>
            </div>
        </PageLayout>
    );
}
