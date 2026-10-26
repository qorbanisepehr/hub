import { useCallback, useEffect, useRef, useState } from "react";
import { toast } from "sonner";

/**
 * Write text to the clipboard, with feedback.
 *
 * ONE copy interaction for the whole app: the clipboard write, the failure
 * toast for the browser that refuses it, the success toast, and the brief
 * copied state a caller shows as its own feedback all live here. Features
 * supply the text and, when the generic «کپی شد.» is not enough, the sentence.
 *
 * `copied` is a moment, not a toggle: it flips on for a beat and resets, so a
 * button can flash its check-mark without anyone tracking a timeout.
 */
export function useCopyToClipboard() {
    const [copied, setCopied] = useState(false);
    const timer = useRef<number | undefined>(undefined);

    useEffect(() => () => window.clearTimeout(timer.current), []);

    const copy = useCallback(
        async (value: string, message?: string): Promise<boolean> => {
            try {
                await navigator.clipboard.writeText(value);
            } catch {
                toast.error("کپی نشد — دسترسی به حافظهٔ موقت ممکن نشد.");

                return false;
            }

            window.clearTimeout(timer.current);
            setCopied(true);
            toast.success(message ?? "کپی شد.");
            timer.current = window.setTimeout(() => setCopied(false), 1500);

            return true;
        },
        [],
    );

    return { copied, copy };
}
