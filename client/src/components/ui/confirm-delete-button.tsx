"use client";

import * as React from "react";
import { IconLoader2, IconTrash, IconX } from "@tabler/icons-react";

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
}: Props) {
    const [confirming, setConfirming] = React.useState(false);

    if (confirming) {
        const content = (
            <div className="flex items-center gap-2">
                <Button
                    variant={confirmVariant}
                    size={size}
                    disabled={isPending}
                    onClick={() => {
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
                    onClick={() => setConfirming(false)}
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

    return (
        <Button
            variant={variant}
            size={size}
            onClick={() => setConfirming(true)}
        >
            <IconTrash className="size-4" />
            {label}
        </Button>
    );
}
