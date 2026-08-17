import { useCallback, useEffect, useState } from "react";

/**
 * تمام‌صفحه کردن یک المان خاص (نه کل صفحه) با Fullscreen API مرورگر.
 * بعد از هر تغییر وضعیت، callback اجرا می‌شود (مثلاً برای fitView در React Flow).
 */
export function useFullscreen<T extends HTMLElement>(
    ref: React.RefObject<T | null>,
    onChange?: (isFullscreen: boolean) => void,
) {
    const [isFullscreen, setIsFullscreen] = useState(false);

    useEffect(() => {
        const handleChange = () => {
            const active = document.fullscreenElement === ref.current;
            setIsFullscreen(active);
            onChange?.(active);
        };
        document.addEventListener("fullscreenchange", handleChange);
        return () =>
            document.removeEventListener("fullscreenchange", handleChange);
    }, [ref, onChange]);

    const enter = useCallback(async () => {
        const el = ref.current;
        if (!el) return;
        try {
            await el.requestFullscreen();
        } catch (error) {
            console.error("خطا در ورود به حالت تمام‌صفحه:", error);
        }
    }, [ref]);

    const exit = useCallback(async () => {
        if (document.fullscreenElement) {
            await document.exitFullscreen();
        }
    }, []);

    const toggle = useCallback(() => {
        if (isFullscreen) {
            void exit();
        } else {
            void enter();
        }
    }, [isFullscreen, enter, exit]);

    return { isFullscreen, toggle };
}
