import type { ReactNode } from "react";

import {
    IconBell,
    IconCheck,
    IconSend,
    IconX,
} from "@tabler/icons-react";

import { Button } from "@/components/ui/button";
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from "@/components/ui/popover";
import { ResponsiveDialog } from "@/components/ui/responsive-dialog";
import { Timeline, type TimelineItem } from "@/components/shared/timeline";
import type { Cv, CvLifecycleEvent } from "@/features/cv/types";
import { toPersianDate } from "@/lib/date-format";

const EVENT_META: Record<
    CvLifecycleEvent["event"],
    { label: string; icon: ReactNode; iconClassName: string }
> = {
    submitted: {
        label: "ارسال رزومه",
        icon: <IconSend className="size-4" />,
        iconClassName: "text-primary",
    },
    approved: {
        label: "تأیید رزومه",
        icon: <IconCheck className="size-4" />,
        iconClassName: "text-success",
    },
    rejected: {
        label: "رد رزومه",
        icon: <IconX className="size-4" />,
        iconClassName: "text-destructive",
    },
};

function cvLifecycleItems(
    lifecycle: CvLifecycleEvent[] | null,
): TimelineItem[] {
    if (!lifecycle) return [];

    return lifecycle.map((event) => {
        const meta = EVENT_META[event.event];

        const description: string[] = [];
        if (event.event === "submitted") {
            description.push(`نسخه ${event.version}`);
        }
        if (event.by_user?.name) {
            const role = event.by_user.role ? ` (${event.by_user.role})` : "";
            description.push(`توسط ${event.by_user.name}${role}`);
        }
        if (event.reason) {
            description.push(`دلیل: ${event.reason}`);
        }

        return {
            title: meta.label,
            time: toPersianDate(event.at),
            description: description.length
                ? description.join(" · ")
                : undefined,
            icon: meta.icon,
            iconClassName: meta.iconClassName,
        };
    });
}

export function CvTimelineModal({
    cv,
    open,
    onOpenChange,
}: {
    cv: Cv;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const items = cvLifecycleItems(cv.lifecycle);

    return (
        <ResponsiveDialog
            open={open}
            onOpenChange={onOpenChange}
            title="چرخه حیات رزومه"
            description={items.length ? undefined : "سابقه‌ای ثبت نشده است"}
        >
            {items.length ? (
                <Timeline items={items} />
            ) : (
                <p className="text-sm text-muted-foreground">
                    سابقه‌ای ثبت نشده است
                </p>
            )}
        </ResponsiveDialog>
    );
}

export function CvFeedbackMenu({ cv }: { cv: Cv }) {
    const rejections = (cv.lifecycle ?? []).filter(
        (event) => event.event === "rejected",
    );

    return (
        <Popover>
            <PopoverTrigger
                render={
                    <Button variant="outline" size="icon" aria-label="بازخورد">
                        <span className="relative inline-flex">
                            <IconBell className="size-4" />
                            {rejections.length > 0 && (
                                <span className="absolute -top-0.5 -end-0.5 size-2 rounded-full bg-destructive" />
                            )}
                        </span>
                    </Button>
                }
            />
            <PopoverContent align="end" className="w-80">
                <div className="flex items-center gap-2 px-1 pt-1">
                    <IconBell className="size-4 text-muted-foreground" />
                    <p className="text-sm font-medium">بازخورد</p>
                </div>

                {rejections.length ? (
                    <div className="space-y-2.5">
                        {[...rejections].reverse().map((rejection, index) => (
                            <div
                                key={index}
                                className="rounded-md bg-muted/50 p-3"
                            >
                                <p className="text-sm font-medium text-destructive">
                                    رد رزومه
                                </p>
                                {rejection.reason && (
                                    <p className="mt-1 text-sm">
                                        {rejection.reason}
                                    </p>
                                )}
                                <p className="mt-2 text-xs text-muted-foreground">
                                    {toPersianDate(rejection.at)}
                                    {rejection.by_user?.name
                                        ? ` · ${rejection.by_user.name}`
                                        : ""}
                                </p>
                            </div>
                        ))}
                    </div>
                ) : (
                    <p className="text-sm text-muted-foreground">
                        بازخوردی ثبت نشده است
                    </p>
                )}
            </PopoverContent>
        </Popover>
    );
}
