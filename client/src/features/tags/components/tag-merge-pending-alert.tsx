import { useEffect, useState } from "react";
import { IconRobot } from "@tabler/icons-react";

import {
    Alert,
    AlertDescription,
    AlertTitle,
} from "@/components/ui/alert";
import { Spinner } from "@/components/ui/spinner";

/**
 * The wait, where the result will appear.
 *
 * A spinner inside the button says only that the press was accepted; the model
 * may take a minute over a few thousand tokens of vocabulary, and during that
 * minute the person is looking at a list that has not changed. This says what is
 * being done, that leaving the page is safe (the run is on a worker and the
 * dialog opens by itself when it lands), and how long ago it began — which is
 * the only measure that can honestly be offered for a single model call.
 *
 * The proposal is not streamed: it is one answer, not a sequence, so elapsed
 * time is the whole of the progress there is.
 */
export function TagMergePendingAlert() {
    const [startedAt] = useState(() => Date.now());
    const [now, setNow] = useState(() => Date.now());

    useEffect(() => {
        const timer = setInterval(() => setNow(Date.now()), 1000);

        return () => clearInterval(timer);
    }, []);

    const seconds = Math.max(0, Math.round((now - startedAt) / 1000));

    return (
        <Alert variant="default" className="me-auto w-full sm:w-auto">
            <IconRobot />

            <AlertTitle className="flex items-center gap-2">
                <Spinner className="size-3" />
                در حال بررسی واژه‌نامه
            </AlertTitle>

            <AlertDescription className="flex flex-wrap items-center gap-x-2 gap-y-1">
                <span>
                    مدل برچسب‌های هم‌معنا را پیدا می‌کند؛ پیشنهاد که آماده شد،
                    همین‌جا باز می‌شود.
                </span>
                <span
                    dir="ltr"
                    className="text-muted-foreground font-mono text-xs tabular-nums"
                >
                    {formatElapsed(seconds)}
                </span>
            </AlertDescription>
        </Alert>
    );
}

/** `M:SS` — a vocabulary pass is measured in minutes, not hours. */
function formatElapsed(totalSeconds: number): string {
    const minutes = Math.floor(totalSeconds / 60);
    const seconds = totalSeconds % 60;

    return `${minutes}:${String(seconds).padStart(2, "0")}`;
}
