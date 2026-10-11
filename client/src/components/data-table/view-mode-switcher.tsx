import { Button } from "@/components/ui/button";
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from "@/components/ui/tooltip";
import { cn } from "@/lib/utils";
import type { ToolbarIcon } from "./toolbar-types";

/** One rendering a list can offer for the same data. */
export type DataTableViewOption = {
    mode: string;
    label: string;
    icon: ToolbarIcon;
};

type ViewModeSwitcherProps = {
    views: DataTableViewOption[];
    value: string;
    onChange: (mode: string) => void;
    className?: string;
};

/**
 * The shared «حالت نمایش» switcher (D15). A list that renders the same rows
 * more than one way declares its views once; the data-table toolbar renders
 * this segmented control inline on desktop and as radio items inside the
 * mobile «...» menu, so the two shells can never disagree about the modes.
 *
 * Renders nothing when there is only one view to choose from — a switcher
 * with a single option is chrome that lies about being a choice.
 */
export function ViewModeSwitcher({
    views,
    value,
    onChange,
    className,
}: ViewModeSwitcherProps) {
    if (views.length < 2) {
        return null;
    }

    return (
        <div
            role="group"
            aria-label="حالت نمایش"
            className={cn(
                "flex items-center gap-0.5 rounded-lg border p-0.5",
                className,
            )}
        >
            {views.map(({ mode, icon: Icon, label }) => (
                <Tooltip key={mode}>
                    <TooltipTrigger
                        render={
                            <Button
                                variant={value === mode ? "secondary" : "ghost"}
                                size="icon-sm"
                                onClick={() => onChange(mode)}
                                aria-pressed={value === mode}
                                aria-label={label}
                            />
                        }
                    >
                        <Icon className="size-4" />
                    </TooltipTrigger>
                    <TooltipContent>{label}</TooltipContent>
                </Tooltip>
            ))}
        </div>
    );
}
