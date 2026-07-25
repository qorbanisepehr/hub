"use client";

import { useState } from "react";
import { IconLoader2 } from "@tabler/icons-react";

import { Button } from "@/components/ui/button";

type ConfirmActionProps = {
    trigger: React.ReactNode;
    confirmLabel?: string;
    onConfirm: () => void;
    isPending?: boolean;
    disabled?: boolean;
    stopPropagation?: boolean;
};

export function ConfirmAction({
    trigger,
    confirmLabel = "حذف",
    onConfirm,
    isPending = false,
    disabled,
    stopPropagation,
}: ConfirmActionProps) {
    const [confirming, setConfirming] = useState(false);

    function handleConfirm() {
        onConfirm();
        setConfirming(false);
    }

    function handleTriggerClick(e: React.MouseEvent) {
        if (stopPropagation) e.stopPropagation();
        if (!disabled) setConfirming(true);
    }

    if (confirming) {
        return (
            <div className="flex items-center gap-1">
                <Button
                    type="button"
                    variant="ghost"
                    size="icon-xs"
                    className="text-destructive hover:text-destructive"
                    onClick={(e) => {
                        e.stopPropagation();
                        handleConfirm();
                    }}
                    disabled={isPending}
                >
                    {isPending ? (
                        <IconLoader2 className="size-3.5 animate-spin" />
                    ) : (
                        <span className="text-xs font-bold">✓</span>
                    )}
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon-xs"
                    onClick={(e) => {
                        e.stopPropagation();
                        setConfirming(false);
                    }}
                    disabled={isPending}
                >
                    <span className="text-xs">✕</span>
                </Button>
            </div>
        );
    }

    return (
        <span
            role="button"
            tabIndex={0}
            onClick={handleTriggerClick}
            onKeyDown={(e) => {
                if (e.key === "Enter" || e.key === " ") {
                    e.preventDefault();
                    if (!disabled) setConfirming(true);
                }
            }}
            className={disabled ? "pointer-events-none opacity-50" : "cursor-pointer"}
        >
            {trigger}
        </span>
    );
}
