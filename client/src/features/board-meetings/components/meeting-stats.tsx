import type { ElementType } from "react";
import {
    IconChecks,
    IconFileText,
    IconRobot,
    IconTextScanAi,
    IconUsers,
} from "@tabler/icons-react";

import { Skeleton } from "@/components/ui/skeleton";
import { cn } from "@/lib/utils";
import { toPersianDate } from "@/lib/date-format";
import type { BoardMeetingDetailResponse } from "../types";
import { hasContent } from "../summary-read";

/**
 * The meeting at a glance, before any tab is opened.
 *
 * Every figure here is already on the page — tab counts, the summary badge, the
 * identity bar. What was missing was a single place that answers "what am I
 * looking at", so a reader opened a tab to find out whether a tab was worth
 * opening. The row is the difference between the list telling you about a
 * meeting and the meeting telling you about itself.
 *
 * One figure earns its place by being *derived* rather than copied: the
 * documents tile. The count is not part of the meeting payload (a meeting's
 * مصوبات numbers no longer sit on the row, vault idea 567) — it comes from the
 * documents query, so the tile sums what the tab holds. مصوبات and اسناد were
 * one thing even before the split: every مصوبه is a document in the hub, so the
 * two are a single tile.
 */
export function MeetingStats({
    meeting,
    documentCount,
    isLoading,
}: {
    meeting: BoardMeetingDetailResponse;
    documentCount: number;
    isLoading: boolean;
}) {
    if (isLoading) {
        return (
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                {Array.from({ length: 5 }, (_, index) => (
                    <Skeleton key={index} className="h-16 w-full" />
                ))}
            </div>
        );
    }

    const minutesLength = meeting.minutes?.trim().length ?? 0;

    const tiles: Array<{
        label: string;
        value: string;
        hint?: string;
        icon: ElementType;
        tone?: "muted" | "success";
    }> = [
        {
            label: "اسناد و مصوبات",
            value: documentCount.toLocaleString("fa-IR"),
            hint: "سند پیوست",
            icon: IconFileText,
        },
        {
            label: "حاضرین",
            value: meeting.attendee_count.toLocaleString("fa-IR"),
            icon: IconUsers,
        },
        {
            label: "دستور جلسه",
            value: meeting.agenda.length.toLocaleString("fa-IR"),
            icon: IconChecks,
        },
        {
            label: "خلاصهٔ هوشمند",
            value: hasContent(meeting.summary)
                ? "دارد"
                : meeting.summary === null
                  ? "تولید نشده"
                  : "بدون خلاصه",
            icon: IconRobot,
            tone: hasContent(meeting.summary) ? "success" : "muted",
        },
        {
            label: "صورتجلسه",
            value:
                minutesLength > 0
                    ? `${minutesLength.toLocaleString("fa-IR")} کاراکتر`
                    : "خالی",
            hint: toPersianDate(meeting.held_on),
            icon: IconTextScanAi,
            tone: minutesLength === 0 ? "muted" : undefined,
        },
    ];

    return (
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
            {tiles.map((tile) => {
                const Icon = tile.icon;

                return (
                    <div
                        key={tile.label}
                        className="bg-card flex flex-col gap-1 rounded-lg border px-3 py-2.5"
                    >
                        <span className="text-muted-foreground flex items-center gap-1.5 text-xs">
                            <Icon />
                            {tile.label}
                        </span>
                        <span
                            className={cn(
                                "text-lg leading-tight font-medium",
                                tile.tone === "muted" &&
                                    "text-muted-foreground",
                            )}
                        >
                            {tile.value}
                        </span>
                        {tile.hint && (
                            <span className="text-muted-foreground text-[11px]">
                                {tile.hint}
                            </span>
                        )}
                    </div>
                );
            })}
        </div>
    );
}