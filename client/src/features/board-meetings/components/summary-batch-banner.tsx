import { useState } from "react";
import { IconPlayerStopFilled, IconRobot } from "@tabler/icons-react";

import {
    Alert,
    AlertAction,
    AlertDescription,
    AlertTitle,
} from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Spinner } from "@/components/ui/spinner";
import { useCan } from "@/features/auth";
import { PERMISSIONS } from "@/lib/permissions";

import { useCancelSummaryBatch, useLiveSummaryBatches } from "../hooks";

/**
 * The stop for a bulk in progress, on the list rather than in the dialog.
 *
 * A bulk is hundreds of slow model calls, so it outlives the dialog that
 * started it, the tab that showed it, and often the intent that asked for it.
 * The stop therefore lives where the meetings are, and — this is the part that
 * makes it usable — it is read back from the server rather than remembered. The
 * batch id is only ever in the dispatch response, and a reload throws that
 * away; a stop that vanished on refresh would only work in the minute after the
 * click, which is not the minute it is needed in.
 *
 * Nothing is drawn without the sync permission, because there would be nothing
 * the viewer could do about it: the block is a control, not a progress report.
 */
export function SummaryBatchBanner() {
    const canSync = useCan(PERMISSIONS.BOARD_MEETING_SYNC);
    const { data } = useLiveSummaryBatches(canSync);
    const cancel = useCancelSummaryBatch();
    const [isStopping, setIsStopping] = useState(false);

    const batches = data ?? [];

    if (!canSync || batches.length === 0) {
        return null;
    }

    const remaining = batches.reduce(
        (total, batch) => total + batch.remaining,
        0,
    );

    async function stopAll() {
        setIsStopping(true);

        try {
            // Every live batch, not just the newest: they were all dispatched
            // by this person and the button says "stop", not "stop one".
            await Promise.all(
                batches.map((batch) => cancel.mutateAsync(batch.batch_id)),
            );
        } catch {
            // Each failed stop has already been announced by the mutation; the
            // live list is re-read either way, so nothing is left pretending to
            // still be running.
        } finally {
            setIsStopping(false);
        }
    }

    return (
        <Alert variant="default">
            <IconRobot />

            <AlertTitle>تولید خلاصه در جریان است</AlertTitle>

            <AlertAction>
                <Button
                    size="icon-xs"
                    disabled={isStopping}
                    onClick={() => void stopAll()}
                    aria-label="توقف اجرا"
                    className="me-1"
                >
                    {isStopping ? (
                        <Spinner className="size-3" />
                    ) : (
                        <IconPlayerStopFilled />
                    )}
                </Button>
            </AlertAction>

            <AlertDescription>
                {remaining.toLocaleString("fa-IR")} جلسه در انتظار اجرا است. با
                توقف، اجراهای باقی‌مانده پیش از آنکه مدل فراخوانی شود کنار
                گذاشته می‌شوند.
            </AlertDescription>
        </Alert>
    );
}
