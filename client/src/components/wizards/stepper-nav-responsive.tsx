import { IconChevronDown, IconCheck } from "@tabler/icons-react";

import { Button } from "@/components/ui/button";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import type { WizardStep } from "./use-wizard-state";

type StepperNavResponsiveProps = {
    steps: readonly WizardStep[];
    value: number;
    onValueChange: (step: number) => void;
    /** Rendered on lg+ instead of the dropdown (the standard StepperNav). */
    children?: React.ReactNode;
};

/**
 * Mobile step selector for the cv/questionnaire wizards. The horizontal
 * stepper rail with per-step descriptions does not fit phone widths; below lg
 * it collapses into a compact dropdown showing "step x/y — label" while the
 * desktop rail stays rendered via `children`.
 */
export function StepperNavResponsive({
    steps,
    value,
    onValueChange,
    children,
}: StepperNavResponsiveProps) {
    const active = steps[value];

    return (
        <>
            {/* Desktop stepper rail (hidden below lg) */}
            <div className="hidden lg:contents">{children}</div>

            {/* Mobile dropdown selector */}
            <div className="mb-4 lg:hidden">
                <DropdownMenu>
                    <DropdownMenuTrigger
                        render={
                            <Button
                                variant="outline"
                                className="h-9 w-full gap-1"
                                aria-label="انتخاب مرحله"
                            />
                        }
                    >
                        <span className="text-muted-foreground text-xs">
                            {value + 1}/{steps.length}
                        </span>
                        <span className="flex-1 truncate text-start">
                            {active?.label}
                        </span>
                        <IconChevronDown className="size-4 shrink-0 opacity-60" />
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        align="start"
                        className="max-h-[70dvh] w-56 overflow-y-auto"
                    >
                        {steps.map((step, index) => (
                            <DropdownMenuItem
                                key={step.id ?? index}
                                onClick={() => onValueChange(index)}
                                className="gap-2"
                            >
                                <IconCheck
                                    className={
                                        index === value
                                            ? "size-4 shrink-0 text-primary"
                                            : "size-4 shrink-0 text-primary opacity-0"
                                    }
                                />
                                <span className="flex-1">{step.label}</span>
                            </DropdownMenuItem>
                        ))}
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </>
    );
}
