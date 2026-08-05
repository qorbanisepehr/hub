import type { ReactNode } from "react";

import { cn } from "@/lib/utils";
import { IconCalendar } from "@tabler/icons-react";

export interface TimelineItem {
    title: ReactNode;
    time?: ReactNode;
    description?: ReactNode;
    icon?: ReactNode;
    iconClassName?: string;
}

interface TimelineProps {
    items: TimelineItem[];
    className?: string;
    contentClassName?: string;
}

/**
 * Vertical timeline in the ReUI style: icon nodes connected by a line with a
 * content panel (title / timestamp / description) beside each node.
 */
export function Timeline({
    items,
    className,
    contentClassName,
}: TimelineProps) {
    return (
        <ol className={cn("relative", className)}>
            {items.map((item, index) => {
                const isLast = index === items.length - 1;

                return (
                    <li
                        key={index}
                        className="group/timeline-item relative flex flex-1 flex-col gap-0.5 not-last:pb-6 ms-10"
                        data-slot="timeline-item"
                    >
                        <div className="" data-slot="timeline-header">
                            <div
                                aria-hidden="true"
                                className="-translate-x-1/2 absolute self-start bg-primary/80 group-last/timeline-item:hidden w-0.5 -inset-s-7 h-[calc(100%-1.5rem-0.25rem)] translate-y-6.5"
                                data-slot="timeline-separator"
                            ></div>
                            <div className="">
                                <h3
                                    className="font-medium text-sm mt-0.5"
                                    data-slot="timeline-title"
                                >
                                    {item.title}
                                </h3>
                                {item.time && (
                                    <div className="flex gap-0.5 ms-1 mt-1 text-xs text-muted-foreground">
                                        <IconCalendar className="size-3" />
                                        <p className="">{item.time}</p>
                                    </div>
                                )}
                            </div>
                            <div
                                aria-hidden="true"
                                className="-translate-x-1/2 absolute rounded-full border-2 top-0 border-primary bg-primary text-primary-foreground flex size-6 items-center justify-center -inset-s-12.5"
                                data-slot="timeline-indicator"
                            >
                                {item.icon}
                            </div>
                        </div>

                        <div className={cn("min-w-0 flex-1", contentClassName)}>
                            {item.description && (
                                <div className="mt-1.5 text-sm text-muted-foreground">
                                    {item.description}
                                </div>
                            )}
                        </div>
                    </li>
                );
            })}
        </ol>
    );
}
