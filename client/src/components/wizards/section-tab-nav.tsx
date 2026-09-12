import { useEffect, useRef } from "react";
import { IconChevronDown, IconCheck } from "@tabler/icons-react";

import { Button } from "@/components/ui/button";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { cn } from "@/lib/utils";

export type SectionTabItem = {
    key: string;
    label: string;
    description?: string;
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
            <TabsList className="hidden w-full rounded bg-sidebar text-sidebar-foreground border border-sidebar-border p-2 flex-col items-stretch gap-0.5 self-start lg:flex">
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
                        className="relative h-auto justify-start rounded-md px-3 py-2 text-start text-sm font-medium text-sidebar-foreground hover:text-sidebar-foreground data-active:hover:text-primary data-active:hover:cursor-default"
                    >
                        {tab.label}
                    </TabsTrigger>
                ))}
            </TabsList>

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
