import { useEffect, useRef, useState, useCallback } from "react";

type UseInfiniteScrollOptions = {
    hasNextPage: boolean;
    isFetchingNextPage: boolean;
    fetchNextPage: () => void;
    threshold?: number;
};

type UseInfiniteScrollReturn = {
    scrollRef: (el: HTMLDivElement | null) => void;
};

export function useInfiniteScroll({
    hasNextPage,
    isFetchingNextPage,
    fetchNextPage,
    threshold = 60,
}: UseInfiniteScrollOptions): UseInfiniteScrollReturn {
    const [scrollEl, setScrollEl] = useState<HTMLDivElement | null>(null);

    const fetchRef = useRef(fetchNextPage);
    const hasNextPageRef = useRef(hasNextPage);
    const isFetchingRef = useRef(isFetchingNextPage);

    useEffect(() => { fetchRef.current = fetchNextPage; }, [fetchNextPage]);
    useEffect(() => { hasNextPageRef.current = hasNextPage; }, [hasNextPage]);
    useEffect(() => { isFetchingRef.current = isFetchingNextPage; }, [isFetchingNextPage]);

    useEffect(() => {
        if (!scrollEl) return;

        let rafId = 0;
        const onScroll = () => {
            if (rafId) return;
            rafId = requestAnimationFrame(() => {
                rafId = 0;
                if (
                    hasNextPageRef.current &&
                    !isFetchingRef.current &&
                    scrollEl.scrollTop + scrollEl.clientHeight >= scrollEl.scrollHeight - threshold
                ) {
                    fetchRef.current?.();
                }
            });
        };

        scrollEl.addEventListener("scroll", onScroll, { passive: true });
        return () => {
            scrollEl.removeEventListener("scroll", onScroll);
            if (rafId) cancelAnimationFrame(rafId);
        };
    }, [scrollEl, threshold]);

    const scrollRef = useCallback((el: HTMLDivElement | null) => {
        setScrollEl(el);
    }, []);

    return { scrollRef };
}
