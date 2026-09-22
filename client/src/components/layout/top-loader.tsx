import { useCallback, useEffect, useRef, useState } from "react";
import { useRouter } from "@tanstack/react-router";
import { cn } from "@/lib/utils";

const MIN_VISIBLE_MS = 400;
const TICK_MS = 180;
const MAX_ACTIVE_MS = 15_000;

/**
 * Progress bar shown at the top of the viewport during route navigations.
 *
 * Shown on `onBeforeNavigate` when the path actually changes, advances toward
 * 92% with a decaying increment while the route loads, and completes (100%)
 * once the route resolves. Animations are pure CSS transitions, so nothing
 * re-renders the page tree; the bar is `dir="ltr"` so it sweeps left-to-right
 * even inside RTL pages.
 */
export function TopLoader() {
    const router = useRouter();
    const [progress, setProgress] = useState<number | null>(null);
    const startedAt = useRef(0);
    const timer = useRef<ReturnType<typeof setTimeout> | null>(null);
    const finishTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
    const generation = useRef(0);

    const clearTimers = useCallback(() => {
        if (timer.current !== null) {
            clearTimeout(timer.current);
            timer.current = null;
        }
        if (finishTimer.current !== null) {
            clearTimeout(finishTimer.current);
            finishTimer.current = null;
        }
    }, []);

    const stop = useCallback(() => {
        generation.current += 1;
        const gen = generation.current;
        clearTimers();

        const elapsed = Date.now() - startedAt.current;
        finishTimer.current = setTimeout(
            () => {
                if (generation.current !== gen) {
                    return;
                }
                setProgress(100);
                finishTimer.current = setTimeout(() => {
                    if (generation.current !== gen) {
                        return;
                    }
                    setProgress(null);
                }, 300);
            },
            Math.max(0, MIN_VISIBLE_MS - elapsed),
        );
    }, [clearTimers]);

    const start = useCallback(() => {
        generation.current += 1;
        const gen = generation.current;
        clearTimers();
        startedAt.current = Date.now();
        setProgress(8);

        const tick = () => {
            if (generation.current !== gen) {
                return;
            }
            if (Date.now() - startedAt.current >= MAX_ACTIVE_MS) {
                stop();
                return;
            }
            setProgress((current) => {
                if (current === null) {
                    return 8;
                }
                return Math.min(92, current + (92 - current) * 0.14);
            });
            timer.current = setTimeout(tick, TICK_MS);
        };
        timer.current = setTimeout(tick, TICK_MS);
    }, [clearTimers, stop]);

    useEffect(() => {
        const beforeNavigate = router.subscribe("onBeforeNavigate", ({ pathChanged }) => {
            if (pathChanged) {
                start();
            }
        });
        const onResolved = router.subscribe("onResolved", ({ pathChanged }) => {
            if (pathChanged) {
                stop();
            }
        });
        return () => {
            beforeNavigate();
            onResolved();
        };
    }, [router, start, stop]);

    useEffect(() => () => clearTimers(), [clearTimers]);

    const visible = progress !== null;

    return (
        <div
            aria-hidden
            dir="ltr"
            className={cn(
                "pointer-events-none fixed inset-x-0 top-0 z-[200] transition-opacity duration-200",
                visible ? "opacity-100" : "opacity-0",
            )}
        >
            <div className="relative h-[3px]">
                <div className="absolute inset-0 bg-primary/10" />
                <div
                    className="absolute inset-y-0 start-0 bg-primary/30 blur-[2px] transition-[width] duration-200 ease-out"
                    style={{ width: `${visible ? progress : 0}%` }}
                />
                <div
                    className="absolute inset-y-0 start-0 bg-primary shadow-[0_0_8px_1px] shadow-primary/50 transition-[width] duration-200 ease-out"
                    style={{ width: `${visible ? progress : 0}%` }}
                />
            </div>
        </div>
    );
}