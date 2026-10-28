import { useEffect, useRef } from "react";
import { IconChevronDown, IconCheck } from "@tabler/icons-react";

import { Button } from "@/components/ui/button";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { TabsList, TabsTrigger } from "@/components/ui/tabs";
import { cn } from "@/lib/utils";

export type SectionTabItem = {
    key: string;
    label: string;
    description?: string;
    icon?: React.ReactNode;
};

type SectionTabNavProps = {
    /** Rendered inside <Tabs orientation="vertical"> — provides the rail + mobile menu. */
    tabs: readonly SectionTabItem[];
    value: string;
    onValueChange: (key: string) => void;
    /** Clipped to the content top on tab change (scroll handling). */
    contentRef?: React.RefObject<HTMLDivElement | null>;
    className?: string;
};

/**
 * Responsive section navigation for long keyed tab lists (employee profile
 * view/edit). Desktop (lg+): a vertical rail aligned to the inline-start edge
 * (right in RTL). Mobile: a compact dropdown selector driven by the same
 * state — no second source of truth.
 *
 * Must wrap <Tabs orientation="vertical"> so the triggers register on the
 * Base UI tabs root; the rail itself renders TabsList/TabsTrigger.
 */
export function SectionTabNav({
    tabs,
    value,
    onValueChange,
    contentRef,
    className,
}: SectionTabNavProps) {
    const active = tabs.find((tab) => tab.key === value) ?? tabs[0];
    const activeTriggerRef = useRef<HTMLButtonElement | null>(null);

    // Keep the active rail item in view when the list overflows vertically.
    useEffect(() => {
        activeTriggerRef.current?.scrollIntoView({
            block: "nearest",
        });
        // Runs on tab change; the ref content is read imperatively.
        // oxlint-disable-next-line react/exhaustive-effect-dependencies -- trigger-only
    }, [value]);

    const selectTab = (key: string) => {
        if (key !== value) {
            onValueChange(key);
        }
        // New content may be shorter than the old one; scroll the page back to
        // the top of the tab content area after the switch so the user always
        // starts reading from the section start.
        requestAnimationFrame(() => {
            contentRef?.current?.scrollIntoView({
                behavior: "smooth",
                block: "start",
            });
        });
    };

    return (
        <nav
            aria-label="ناوبری بخش‌ها"
            className={cn(
                // Mobile: block-level, so the dropdown occupies its own row
                // above the panel. Desktop (lg+): fixed-width rail column.
                "w-full lg:flex lg:w-56 lg:shrink-0 lg:self-start",
                className,
            )}
        >
            {/* Desktop vertical rail */}
            <div className="hidden lg:block rounded-2xl border border-border/70 bg-card/70 p-2 shadow-sm backdrop-blur">
                <TabsList className="flex w-full flex-col items-stretch gap-1 bg-transparent p-0">
                    {tabs.map((tab) => (
                        <TabsTrigger
                            key={tab.key}
                            value={tab.key}
                            ref={
                                tab.key === active?.key
                                    ? activeTriggerRef
                                    : undefined
                            }
                            onSelect={() => selectTab(tab.key)}
                            className={cn(
                                "group h-auto justify-start gap-3 rounded-xl border border-transparent px-3 py-2.5 text-start transition-colors",
                                "text-muted-foreground hover:bg-muted/60 hover:text-foreground",
                                "data-[state=active]:border-primary/20 data-[state=active]:bg-primary/10 data-[state=active]:text-primary data-[state=active]:shadow-sm",
                            )}
                        >
                            {tab.icon ? (
                                <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-muted/70 text-foreground/80 transition-colors group-data-[state=active]:bg-primary/15 group-data-[state=active]:text-primary">
                                    {tab.icon}
                                </span>
                            ) : null}
                            <span className="min-w-0 flex-1">
                                <span className="block text-sm font-semibold">
                                    {tab.label}
                                </span>
                                {tab.description ? (
                                    <span className="mt-0.5 block text-xs font-normal text-muted-foreground/80">
                                        {tab.description}
                                    </span>
                                ) : null}
                            </span>
                        </TabsTrigger>
                    ))}
                </TabsList>
            </div>

            {/* Mobile dropdown selector */}
            <DropdownMenu>
                <DropdownMenuTrigger
                    render={
                        <Button
                            variant="outline"
                            className="h-9 w-full gap-1 lg:hidden"
                            aria-label="انتخاب بخش"
                        />
                    }
                >
                    <span className="flex-1 truncate text-start">
                        {active?.label}
                    </span>
                    <IconChevronDown className="size-4 shrink-0 opacity-60" />
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    align="start"
                    className="max-h-[70dvh] w-56 overflow-y-auto"
                >
                    {tabs.map((tab) => (
                        <DropdownMenuItem
                            key={tab.key}
                            onClick={() => selectTab(tab.key)}
                            className="gap-2"
                        >
                            <IconCheck
                                className={cn(
                                    "size-4 shrink-0 text-primary",
                                    tab.key === active?.key
                                        ? "opacity-100"
                                        : "opacity-0",
                                )}
                            />
                            <span className="flex-1">{tab.label}</span>
                        </DropdownMenuItem>
                    ))}
                </DropdownMenuContent>
            </DropdownMenu>
        </nav>
    );
}
