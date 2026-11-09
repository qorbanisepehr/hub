import { useEffect, useState } from "react";
import { IconClock, IconLoader2 } from "@tabler/icons-react";

import { Badge } from "@/components/ui/badge";
import type { AgentActivity } from "@/lib/run-activity";

/**
 * «در حال اجرا» beside an agent that is running, with how long it has been.
 *
 * It ticks locally rather than re-rendering on a server round trip: the number
 * is for deciding whether to wait, and a per-second request would be absurd for
 * that. The starting point is the event that first reported the run, so a page
 * opened mid-run says the same thing as the page that started it only when the
 * former has heard an event — which is why both are shown with «از … پیش» rather
 * than as a count that would read as zero.
 */
export function AgentRunBadge({
    activity,
    className,
}: {
    activity: AgentActivity;
    className?: string;
}) {
    const queued = activity.state === "queued";

    return (
        <Badge
            variant={queued ? "outline" : "info"}
            className={className}
            title={
                activity.model !== null
                    ? `مدل: ${activity.model}`
                    : "مدل اجرا ثبت نشده است"
            }
        >
            {queued ? (
                <IconClock className="size-3" />
            ) : (
                <IconLoader2 className="size-3 animate-spin" />
            )}
            {queued ? "در صف اجرا" : "در حال اجرا"}
            <Elapsed since={activity.startedAt} />
        </Badge>
    );
}

/** `۰:۱۲` / `۱:۰۵:۰۳`, ticking each second while mounted. */
function Elapsed({ since }: { since: string }) {
    const [now, setNow] = useState(() => Date.now());

    useEffect(() => {
        const timer = setInterval(() => setNow(Date.now()), 1000);

        return () => clearInterval(timer);
    }, []);

    const seconds = Math.max(
        0,
        Math.round((now - new Date(since).getTime()) / 1000),
    );

    return (
        <span dir="ltr" className="font-mono tabular-nums">
            {formatElapsed(seconds)}
        </span>
    );
}

function formatElapsed(totalSeconds: number): string {
    const hours = Math.floor(totalSeconds / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = totalSeconds % 60;

    return hours > 0
        ? `${hours}:${pad(minutes)}:${pad(seconds)}`
        : `${minutes}:${pad(seconds)}`;
}

function pad(value: number): string {
    return String(value).padStart(2, "0");
}
