"use client";

import * as React from "react";
import { IconCheck, IconLoader2, IconTrash, IconX } from "@tabler/icons-react";

import { Button } from "@/components/ui/button";

type Props = {
    onConfirm: () => void;
    isPending?: boolean;
    label?: string;
    confirmLabel?: string;
    cancelLabel?: string;
    variant?: React.ComponentProps<typeof Button>["variant"];
    confirmVariant?: React.ComponentProps<typeof Button>["variant"];
    size?: React.ComponentProps<typeof Button>["size"];
    /** Render confirmation UI as an absolute overlay instead of inline expansion */
    overlay?: boolean;
    /** Render as icon-only (no text). Confirmation renders inline ✓/✕ icons. */
    iconOnly?: boolean;
    disabled?: boolean;
    className?: string;
    stopPropagation?: boolean;
};

export function ConfirmDeleteButton({
    onConfirm,
    isPending,
    label = "حذف",
    confirmLabel = "تأیید حذف",
    cancelLabel = "انصراف",
    variant = "destructive",
    confirmVariant = "default",
    size,
    overlay = false,
    iconOnly = false,
    disabled = false,
    className,
    stopPropagation = false,
}: Props) {
    const [confirming, setConfirming] = React.useState(false);

    if (confirming) {
        if (iconOnly) {
            return (
                <div className="flex items-center gap-1">
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        disabled={isPending}
                        onClick={(e) => {
                            if (stopPropagation) e.stopPropagation();
                            setConfirming(false);
                            onConfirm();
                        }}
                    >
                        {isPending ? (
                            <IconLoader2 className="size-4 animate-spin" />
                        ) : (
                            <IconCheck className="size-4 text-primary" />
                        )}
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        disabled={isPending}
                        onClick={(e) => {
                            if (stopPropagation) e.stopPropagation();
                            setConfirming(false);
                        }}
                    >
                        <IconX className="size-4" />
                    </Button>
                </div>
            );
        }

        const content = (
            <div className="flex items-center gap-2">
                <Button
                    variant={confirmVariant}
                    size={size}
                    disabled={isPending}
                    onClick={(e) => {
                        if (stopPropagation) e.stopPropagation();
                        setConfirming(false);
                        onConfirm();
                    }}
                >
                    {isPending ? (
                        <IconLoader2 className="size-4 animate-spin" />
                    ) : (
                        <IconTrash className="size-4" />
                    )}
                    {confirmLabel}
                </Button>
                <Button
                    variant="outline"
                    size={size}
                    disabled={isPending}
                    onClick={(e) => {
                        if (stopPropagation) e.stopPropagation();
                        setConfirming(false);
                    }}
                >
                    <IconX className="size-4" />
                    {cancelLabel}
                </Button>
            </div>
        );

        if (overlay) {
            return (
                <div className="absolute inset-y-0 inset-e-0 z-10 flex items-center gap-2 rounded-lg bg-background shadow-md">
                    {content}
                </div>
            );
        }

        return content;
    }

    if (iconOnly) {
        return (
            <Button
                variant="ghost"
                size={size ?? "icon-sm"}
                disabled={disabled}
                className={className}
                onClick={(e) => {
                    if (stopPropagation) e.stopPropagation();
                    setConfirming(true);
                }}
            >
                <IconTrash className="size-4" />
            </Button>
        );
    }

    return (
        <Button
            variant={variant}
            size={size}
            disabled={disabled}
            className={className}
            onClick={(e) => {
                if (stopPropagation) e.stopPropagation();
                setConfirming(true);
            }}
        >
            <IconTrash className="size-4" />
            {label}
        </Button>
    );
}
