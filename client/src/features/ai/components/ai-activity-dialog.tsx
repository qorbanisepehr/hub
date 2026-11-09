import { Link } from "@tanstack/react-router";
import {
    IconActivity,
    IconArrowUpRight,
    IconClock,
    IconCpu,
    IconSunrise,
} from "@tabler/icons-react";

import { Badge } from "@/components/ui/badge";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import { ScrollArea } from "@/components/ui/scroll-area";
import type { AgentActivity } from "@/lib/run-activity";
import { toPersianRelative } from "@/lib/date-format";
import type { AiAgentDto } from "../types";
import { AgentRunBadge } from "./agent-run-badge";

/**
 * What the AI is doing for this person right now, and how to get there.
 *
 * Settings is the one page where every agent is listed and none is a workspace,
 * so the honest thing to put beside a running one is a way to the screen it
 * belongs to: a meeting's analysis tab, or the tag vocabulary. Without that the
 * badge says "something is happening somewhere", which is the kind of status
 * nobody can act on.
 *
 * Nothing is fetched here. The activity comes from the live map the shell keeps,
 * and the agent rows come from the list the page already loaded — a dialog that
 * fired its own request would be a second answer to a question already answered.
 */
export function AiActivityDialog({
    open,
    onOpenChange,
    agents,
    activity,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    agents: AiAgentDto[];
    activity: Readonly<Record<string, AgentActivity>>;
}) {
    const running = agents
        .filter((agent) => activity[agent.key] !== undefined)
        .map((agent) => ({ agent, run: activity[agent.key] }));

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <IconActivity className="size-4" />
                        اجراهای در جریان
                    </DialogTitle>
                    <DialogDescription>
                        کارهای هوش مصنوعی که همین حالا با حساب شما در حال
                        اجرا هستند — از هر صفحه‌ای که شروع شده باشند.
                    </DialogDescription>
                </DialogHeader>

                {running.length === 0 ? (
                    <div className="flex flex-col items-center gap-2 py-8 text-center">
                        <span className="bg-muted text-muted-foreground grid size-10 place-items-center rounded-full">
                            <IconSunrise className="size-5" />
                        </span>
                        <p className="text-muted-foreground text-sm">
                            در این لحظه اجرایی در جریان نیست.
                        </p>
                    </div>
                ) : (
                    <ScrollArea className="max-h-96">
                        <ul className="grid gap-2 pe-2">
                            {running.map(({ agent, run }) => (
                                <li
                                    key={agent.key}
                                    className="grid gap-1.5 rounded-lg border p-3"
                                >
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <span className="flex items-center gap-2 text-sm font-medium">
                                            {agent.label ?? agent.key}
                                            {run.subject !== null && (
                                                <Badge variant="secondary">
                                                    {String(run.subject.id)}
                                                </Badge>
                                            )}
                                        </span>
                                        <AgentRunBadge activity={run} />
                                    </div>

                                    <div className="text-muted-foreground flex flex-wrap items-center gap-x-3 gap-y-1 text-xs">
                                        <span className="inline-flex items-center gap-1">
                                            <IconClock className="size-3.5" />
                                            از{" "}
                                            {toPersianRelative(run.startedAt)}
                                        </span>
                                        {run.model !== null && (
                                            <span
                                                dir="ltr"
                                                className="inline-flex items-center gap-1"
                                            >
                                                <IconCpu className="size-3.5" />
                                                {run.model}
                                            </span>
                                        )}
                                    </div>

                                    <ActivityLink agent={agent.key} run={run} />
                                </li>
                            ))}
                        </ul>
                    </ScrollArea>
                )}
            </DialogContent>
        </Dialog>
    );
}

/**
 * The way to the screen this run belongs to.
 *
 * Only the features whose address the client can build are linked. A run for a
 * feature with no such page — a summary batch member whose meeting is not on
 * this device's idea of the list — falls back to naming the workspace rather
 * than offering a link that would land on "not found".
 */
function ActivityLink({
    agent,
    run,
}: {
    agent: string;
    run: AgentActivity;
}) {
    if (agent === "board-meeting.summary" && run.subject !== null) {
        const meetingId = Number(run.subject.id);

        if (Number.isFinite(meetingId)) {
            return (
                <Link
                    to="/board-meetings/$meetingId"
                    params={{ meetingId: String(meetingId) }}
                    search={{ tab: "analysis" }}
                    className="text-primary inline-flex w-fit items-center gap-1 text-xs hover:underline"
                >
                    رفتن به تحلیل جلسه
                    <IconArrowUpRight className="size-3.5" />
                </Link>
            );
        }
    }

    if (agent === "tagging.consolidate") {
        return (
            <Link
                to="/settings/tags"
                className="text-primary inline-flex w-fit items-center gap-1 text-xs hover:underline"
            >
                رفتن به برچسب‌ها
                <IconArrowUpRight className="size-3.5" />
            </Link>
        );
    }

    return null;
}
