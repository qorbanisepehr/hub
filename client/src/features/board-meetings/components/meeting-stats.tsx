import type { ElementType } from "react";
import {
    IconChecks,
    IconFileText,
    IconListCheck,
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
 * Two figures earn their place by being *derived* rather than copied:
 *
 *  - approvals split into "with a scan" and "without", because that is the
 *    question a registry actually raises and the tab count cannot answer it;
 *  - the minutes' length, because an empty or half-imported OCR scan looks
 *    identical to a short one until you have read it.
 */
export function MeetingStats({
    meeting,
    isLoading,
}: {
    meeting: BoardMeetingDetailResponse;
    isLoading: boolean;
}) {
    if (isLoading) {
        return (
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                {Array.from({ length: 6 }, (_, index) => (
                    <Skeleton key={index} className="h-16 w-full" />
                ))}
            </div>
        );
    }

    const withScan = meeting.approvals.filter(
        (approval) => approval.document_id !== null,
    ).length;
    const withoutScan = meeting.approvals.length - withScan;

    const minutesLength = meeting.minutes?.trim().length ?? 0;

    const tiles: Array<{
        label: string;
        value: string;
        hint?: string;
        icon: ElementType;
        tone?: "muted" | "success";
    }> = [
        {
            label: "مصوبات",
            value: meeting.approvals.length.toLocaleString("fa-IR"),
            hint:
                withoutScan > 0
                    ? `${withoutScan.toLocaleString("fa-IR")} بدون سند`
                    : "همه با سند",
            icon: IconListCheck,
            tone: withoutScan > 0 ? "muted" : "success",
        },
        {
            label: "اسناد",
            value: meeting.approval_count.toLocaleString("fa-IR"),
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
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
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
