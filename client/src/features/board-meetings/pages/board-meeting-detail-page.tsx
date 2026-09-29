import { useParams } from "@tanstack/react-router";
import { IconCalendarStats, IconFileText } from "@tabler/icons-react";

import { useBoardMeeting } from "../hooks";
import { toPersianDate } from "@/lib/date-format";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { EmptyState, ErrorPage, PageHeader, PageLayout } from "@/components/layout";
import { MeetingDocumentsCard } from "../components/meeting-documents-card";
import { MeetingTagsCard } from "../components/meeting-tags-card";
import type { BoardMeetingApprovalDto } from "../types";

/**
 * The registry numbers this meeting passed, in registry order. The PDFs
 * themselves live in the hub Document system (the card below), so this is
 * the registry's own record: number, title, and whether its document has
 * been imported yet.
 */
function MeetingApprovals({
    approvals,
}: {
    approvals: BoardMeetingApprovalDto[];
}) {
    if (approvals.length === 0) {
        return <span className="text-sm text-muted-foreground">—</span>;
    }

    return (
        <ul className="divide-y rounded-md border">
            {approvals.map((approval) => (
                <li
                    key={approval.number}
                    className="flex flex-wrap items-center gap-2 px-3 py-2"
                >
                    <span dir="ltr" className="text-sm font-medium">
                        {approval.number}
                    </span>
                    {approval.title && (
                        <span className="text-muted-foreground text-xs">
                            {approval.title}
                        </span>
                    )}
                    {approval.document_id !== null && (
                        <Badge variant="outline" className="ms-auto">
                            <IconFileText className="size-3" />
                            سند
                        </Badge>
                    )}
                </li>
            ))}
        </ul>
    );
}

/**
 * Board meeting detail: the meeting's own metadata, the approvals it
 * passed, the structured agenda + attendees, the tag editor, the
 * Document-system card, and the minutes.
 *
 * Read-only by design — the registry columns belong to the source and the
 * CRUD surface arrives in a later increment. A meeting a person has
 * already edited is marked as such, because that is what stops the next
 * sync from touching it.
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
                title={`جلسه ${toPersianDate(meeting.held_on)}`}
                description={meeting.title ?? meeting.registry_label}
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
                            <p
                                className="font-medium"
                                title={meeting.held_on ?? undefined}
                            >
                                {toPersianDate(meeting.held_on)}
                            </p>
                        </div>
                        <div>
                            <p className="text-muted-foreground text-sm">
                                شمارهٔ جلسه
                            </p>
                            <p className="font-medium" dir="ltr">
                                {meeting.registry_label}
                            </p>
                        </div>
                        <div>
                            <p className="text-muted-foreground text-sm">
                                منبع
                            </p>
                            <p className="flex flex-wrap items-center gap-2 font-medium">
                                <span dir="ltr">{meeting.origin}</span>
                                {meeting.source_locked ? (
                                    <Badge variant="outline">
                                        ویرایش‌شده توسط کاربر
                                    </Badge>
                                ) : (
                                    <Badge variant="secondary">
                                        همگام با منبع
                                    </Badge>
                                )}
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
                        <div className="sm:col-span-2">
                            <p className="text-muted-foreground text-sm">
                                مصوبات
                            </p>
                            <div className="mt-1">
                                <MeetingApprovals approvals={meeting.approvals} />
                            </div>
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
                                {agenda.map((item) => (
                                    <li key={item.position} className="text-sm leading-7">
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
                                // oxlint-disable-next-line react/no-array-index-key -- attendance entries have no stable identity
                                <Badge key={index} variant="outline">
                                    {attendee.name}
                                </Badge>
                            ))}
                        </CardContent>
                    </Card>
                )}

                <MeetingDocumentsCard meetingId={String(meetingId)} />

                <MeetingTagsCard
                    meetingId={String(meetingId)}
                    tags={meeting.tags}
                />

                <Card>
                    <CardHeader>
                        <CardTitle className="text-lg">صورتجلسه</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {meeting.minutes ? (
                            <p className="text-sm leading-7 whitespace-pre-line">
                                {meeting.minutes}
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
