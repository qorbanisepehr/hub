import { IconCheck, IconCopy } from "@tabler/icons-react";
import type { ComponentProps } from "react";

import { Button } from "@/components/ui/button";
import { useCopyToClipboard } from "@/hooks/use-copy-to-clipboard";
import { cn } from "@/lib/utils";

type CopyButtonProps = Omit<ComponentProps<typeof Button>, "onClick"> & {
    /** The text that lands on the clipboard, verbatim. */
    value: string;
    /** The toast's sentence; a generic «کپی شد.» when the value speaks for itself. */
    message?: string;
    /** Shown beside the icon — the label, when the button is not icon-only. */
    children?: ComponentProps<typeof Button>["children"];
};

/**
 * Copy a value to the clipboard, then say so twice: a toast, and the icon
 * briefly turning into a check.
 *
 * One of these wherever a value begs to be carried elsewhere — a placeholder
 * from a cheat sheet, a webhook address, a tracking code — instead of every
 * feature re-deriving the clipboard write, the toasts and the flash.
 */
export function CopyButton({
    value,
    message,
    className,
    children,
    variant = "ghost",
    size = "icon-sm",
    ...props
}: CopyButtonProps) {
    const { copied, copy } = useCopyToClipboard();

    return (
        <Button
            type="button"
            variant={variant}
            size={size}
            className={cn("text-muted-foreground", className)}
            onClick={() => void copy(value, message)}
            {...props}
        >
            {copied ? (
                <IconCheck className="size-4 text-emerald-600" />
            ) : (
                <IconCopy className="size-4" />
            )}
            {children}
        </Button>
    );
}
