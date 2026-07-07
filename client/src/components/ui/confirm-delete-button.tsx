"use client";

import * as React from "react";
import { IconLoader2, IconTrash } from "@tabler/icons-react";

import { Button } from "@/components/ui/button";

type Props = {
    onConfirm: () => void;
    isPending?: boolean;
    label?: string;
    confirmLabel?: string;
    cancelLabel?: string;
    variant?: React.ComponentProps<typeof Button>["variant"];
    size?: React.ComponentProps<typeof Button>["size"];
};

export function ConfirmDeleteButton({
    onConfirm,
    isPending,
    label = "حذف",
    confirmLabel = "تأیید حذف",
    cancelLabel = "انصراف",
    variant = "destructive",
    size,
}: Props) {
    const [confirming, setConfirming] = React.useState(false);

    if (confirming) {
        return (
            <div className="flex items-center gap-2">
                <Button
                    variant={variant}
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
                    {cancelLabel}
                </Button>
            </div>
        );
    }

    return (
        <Button variant={variant} size={size} onClick={() => setConfirming(true)}>
            <IconTrash className="size-4" />
            {label}
        </Button>
    );
}
