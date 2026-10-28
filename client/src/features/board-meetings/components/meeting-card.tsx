import { Link } from "@tanstack/react-router";
import { IconCalendarStats, IconUsers } from "@tabler/icons-react";

import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { toPersianDate } from "@/lib/date-format";

import type { BoardMeetingDto } from "../types";

const MAX_VISIBLE_TAGS = 3;

/**
 * One meeting as a card — the «کارتی» view of the same rows the table
 * renders. The registry key is the identity (the human title is optional),
 * so it leads and the Gregorian date is shown beside it in the same
 * Persian formatting the table column uses. The whole card is the link to
 * the detail page; there is no second affordance that says the same thing.
 */
export function MeetingCard({ meeting }: { meeting: BoardMeetingDto }) {
    const visibleTags = meeting.tags.slice(0, MAX_VISIBLE_TAGS);
    const hiddenTagCount = meeting.tags.length - visibleTags.length;

    return (
        <Link
            to="/board-meetings/$meetingId"
            params={{ meetingId: String(meeting.id) }}
            className="block rounded-xl outline-none focus-visible:ring-3 focus-visible:ring-ring/50"
        >
            <Card className="h-full gap-3 transition-colors hover:border-ring">
                <CardHeader className="gap-1">
                    <CardTitle className="flex flex-wrap items-center justify-between gap-2 text-sm">
                        <span>{meeting.registry_label}</span>
                        <span className="inline-flex items-center gap-1 text-xs font-normal text-muted-foreground">
                            <IconCalendarStats className="size-3.5" />
                            {toPersianDate(meeting.held_on)}
                        </span>
                    </CardTitle>
                </CardHeader>
                <CardContent className="flex flex-1 flex-col gap-3">
                    {meeting.title ? (
                        <p className="line-clamp-2 text-sm text-muted-foreground">
                            {meeting.title}
                        </p>
                    ) : (
                        <p className="text-sm text-muted-foreground/60">
                            بدون عنوان
                        </p>
                    )}

                    <div className="mt-auto flex flex-wrap items-center gap-2">
                        <span className="inline-flex items-center gap-1 text-xs text-muted-foreground">
                            <IconUsers className="size-3.5" />
                            {meeting.attendee_count.toLocaleString("fa-IR")} حاضر
                        </span>
                        {visibleTags.map((tag) => (
                            <Badge key={tag.slug} variant="outline">
                                {tag.name}
                            </Badge>
                        ))}
                        {hiddenTagCount > 0 && (
                            <Badge variant="secondary">
                                {hiddenTagCount.toLocaleString("fa-IR")}+
                            </Badge>
                        )}
                    </div>
                </CardContent>
            </Card>
        </Link>
    );
}
