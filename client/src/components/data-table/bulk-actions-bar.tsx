import { useEffect, useRef, useState } from "react";
import { IconX } from "@tabler/icons-react";

import type { DataTableToolbarAction } from "./toolbar-types";

import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Separator } from "@/components/ui/separator";
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from "@/components/ui/tooltip";
import { usePermission } from "@/features/auth/hooks/use-permission";

/**
 * The floating bulk bar over a table with rows selected (shadcn-admin's
 * bulk-actions shape, adapted to this codebase's conventions).
 *
 * It reads like a sentence, not a toolbar: a round «×» to clear (Esc does the
 * same when focus is inside the bar), the count as a badge between separators
 * (fa-IR digits, matching every other count in the app), then the actions the
 * page declared. It floats *below* the rows it acts on, sticky to the card's
 * visible bottom edge — a bar inside the scroll area would scroll itself out
 * of reach exactly when it is needed.
 *
 * Arrow keys rove focus across the bar's buttons and announcement of the
 * selection count is made to screen readers, following the accessibility
 * contract of the reference implementation.
 *
 * Permission gating follows the toolbar's contract (`DataTableToolbarAction`
 * is the one declaration): an action without `permission` renders for
 * everyone; a gated action renders through its own component so its
 * `usePermission` hook call sits unconditionally at component level — never
 * inside a loop, callback or condition.
 */
export function BulkActionsBar({
    count,
    onClear,
    actions,
}: {
    count: number;
    onClear: () => void;
    actions: DataTableToolbarAction[];
}) {
    const toolbarRef = useRef<HTMLDivElement>(null);
    const [announcement, setAnnouncement] = useState("");

    // Announce selection changes to screen readers; deferred by microtask so
    // the live region exists before it must speak, and cleared shortly after.
    useEffect(() => {
        if (count > 0) {
            const message = `${count.toLocaleString("fa-IR")} مورد انتخاب شد؛ نوار عملیات دسته‌ای در دسترس است.`;
            const timer = setTimeout(() => setAnnouncement(message), 100);
            const clearTimer = setTimeout(() => setAnnouncement(""), 3000);
            return () => {
                clearTimeout(timer);
                clearTimeout(clearTimer);
            };
        }
    }, [count]);

    const handleKeyDown = (event: React.KeyboardEvent) => {
        const buttons = toolbarRef.current?.querySelectorAll("button");
        if (!buttons || buttons.length === 0) return;
        const currentIndex = Array.from(buttons).findIndex(
            (button) => button === document.activeElement,
        );

        switch (event.key) {
            // RTL reading order: the bar's first button is rightmost, so the
            // physical arrow keys mirror the shadcn-admin LTR mapping swapped
            // for this direction — Right = previous-in-order, Left = next.
            case "ArrowRight": {
                event.preventDefault();
                const prevIndex =
                    currentIndex <= 0 ? buttons.length - 1 : currentIndex - 1;
                buttons[prevIndex]?.focus();
                break;
            }
            case "ArrowLeft": {
                event.preventDefault();
                const nextIndex = (currentIndex + 1) % buttons.length;
                buttons[nextIndex]?.focus();
                break;
            }
            case "Home": {
                event.preventDefault();
                buttons[0]?.focus();
                break;
            }
            case "End": {
                event.preventDefault();
                buttons[buttons.length - 1]?.focus();
                break;
            }
            case "Escape": {
                // Escape fired while a dropdown inside this bar is open belongs
                // to the dropdown (it closes first); only a bare Escape clears.
                const target = event.target as HTMLElement | null;
                const active = document.activeElement as HTMLElement | null;
                const fromDropdown =
                    target?.closest('[data-slot="dropdown-menu-content"]') ??
                    active?.closest('[data-slot="dropdown-menu-content"]');
                if (fromDropdown) return;
                event.preventDefault();
                onClear();
                break;
            }
        }
    };

    return (
        <>
            {/* Live region for screen reader announcements */}
            <div aria-live="polite" aria-atomic="true" className="sr-only" role="status">
                {announcement}
            </div>

            <div
                ref={toolbarRef}
                role="toolbar"
                aria-label={`عملیات دسته‌ای برای ${count.toLocaleString("fa-IR")} ردیف انتخاب‌شده`}
                onKeyDown={handleKeyDown}
                className="animate-in fade-in-0 slide-in-from-bottom-2 duration-300 fixed inset-x-4 bottom-6 z-50 flex flex-wrap items-center gap-x-2 rounded-xl border bg-background/95 p-2 shadow-xl backdrop-blur-lg supports-[backdrop-filter]:bg-background/60 md:inset-x-auto md:left-1/2 md:max-w-3xl md:-translate-x-1/2"
            >
                <Tooltip>
                    <TooltipTrigger
                        render={
                            <Button
                                variant="outline"
                                size="icon"
                                onClick={onClear}
                                className="size-6 rounded-full"
                                aria-label="لغو انتخاب"
                            >
                                <IconX className="size-4" />
                            </Button>
                        }
                    />
                    <TooltipContent side="top">
                        <p>لغو انتخاب (Esc)</p>
                    </TooltipContent>
                </Tooltip>

                <Separator orientation="vertical" className="h-5" aria-hidden="true" />

                <div className="flex items-center gap-x-1 text-sm">
                    <Badge variant="default" className="min-w-8 justify-center">
                        {count.toLocaleString("fa-IR")}
                    </Badge>
                    <span className="text-muted-foreground">مورد انتخاب شد</span>
                </div>

                <Separator orientation="vertical" className="h-5" aria-hidden="true" />

                <div className="ms-auto flex flex-wrap items-center gap-2">
                    {actions.map((action) =>
                        action.permission ? (
                            <PermissionedBulkAction
                                key={action.id}
                                action={action}
                                permission={action.permission}
                            />
                        ) : (
                            <BulkActionButton key={action.id} action={action} />
                        ),
                    )}
                </div>
            </div>
        </>
    );
}

/** Owns the `usePermission` call for one permission-gated action. */
function PermissionedBulkAction({
    action,
    permission,
}: {
    action: DataTableToolbarAction;
    permission: string | string[];
}) {
    const allowed = usePermission(permission);

    if (!allowed) {
        return null;
    }

    return <BulkActionButton action={action} />;
}

function BulkActionButton({ action }: { action: DataTableToolbarAction }) {
    return (
        <Button
            variant="default"
            size="sm"
            onClick={() => {
                action.onClick();
            }}
            disabled={action.disabled}
            className="gap-1.5"
        >
            {action.icon ? <action.icon className="size-4" /> : null}
            {action.label}
        </Button>
    );
}
