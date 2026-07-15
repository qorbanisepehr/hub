import { memo, useCallback, useEffect, useMemo, useState, useRef } from "react";
import {
    IconChevronDown,
    IconPlus,
    IconSearch,
    IconCircleCheck,
    IconLoader2,
} from "@tabler/icons-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Skeleton } from "@/components/ui/skeleton";
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogTrigger,
} from "@/components/ui/dialog";

type SearchSelectModalProps<T> = {
    items: T[];
    isLoading?: boolean;
    isFetchingNextPage?: boolean;
    isSearchPending?: boolean;
    hasNextPage?: boolean;
    fetchNextPage?: () => void;
    searchQuery: string;
    onSearchChange: (query: string) => void;
    value?: number | string | null;
    onChange: (item: T | null) => void;
    onCreateNew?: () => void;
    searchPlaceholder?: string;
    placeholder?: string;
    emptyText?: string;
    disabled?: boolean;
    modalTitle?: string;
    modalDescription?: string;
    getItemKey: (item: T) => number | string;
    getItemLabel: (item: T) => string;
    getItemSubLabel?: (item: T) => string;
    className?: string;
};

function SelectItem<T>({
    item,
    isSelected,
    onSelect,
    getItemKey,
    getItemLabel,
    getItemSubLabel,
}: {
    item: T;
    isSelected: boolean;
    onSelect: (key: number | string) => void;
    getItemKey: (item: T) => number | string;
    getItemLabel: (item: T) => string;
    getItemSubLabel?: (item: T) => string;
}) {
    const key = getItemKey(item);
    return (
        <button
            type="button"
            onClick={() => onSelect(key)}
            className={`flex w-full items-center gap-3 rounded-lg px-2 py-2 text-start text-sm transition-colors outline-none hover:bg-accent hover:text-accent-foreground ${
                isSelected ? "bg-accent text-accent-foreground" : ""
            }`}
        >
            <div className="flex min-w-0 flex-1 flex-col">
                <span className="truncate font-medium">
                    {getItemLabel(item)}
                </span>
                {getItemSubLabel && (
                    <span className="truncate text-xs text-muted-foreground">
                        {getItemSubLabel(item)}
                    </span>
                )}
            </div>
            {isSelected && (
                <IconCircleCheck className="size-4 shrink-0 text-primary" />
            )}
        </button>
    );
}

const SkeletonList = memo(function SkeletonList() {
    return (
        <div className="space-y-1 p-1">
            {Array.from({ length: 5 }).map((_, i) => (
                <div
                    key={i}
                    className="flex items-center gap-3 rounded-lg px-2 py-2"
                >
                    <Skeleton className="size-8 shrink-0 rounded-full" />
                    <div className="flex-1 space-y-1.5">
                        <Skeleton className="h-3.5 w-24" />
                        <Skeleton className="h-3 w-16" />
                    </div>
                </div>
            ))}
        </div>
    );
});

const EmptyState = memo(function EmptyState({ text }: { text: string }) {
    return (
        <div className="flex flex-col items-center justify-center py-8 text-muted-foreground">
            <IconSearch className="mb-2 size-8 opacity-40" />
            <p className="text-sm">{text}</p>
        </div>
    );
});

export function SearchSelectModal<T>({
    items,
    isLoading = false,
    isFetchingNextPage = false,
    isSearchPending = false,
    hasNextPage = false,
    fetchNextPage,
    searchQuery,
    onSearchChange,
    value,
    onChange,
    onCreateNew,
    searchPlaceholder = "جستجو...",
    placeholder = "انتخاب کنید",
    emptyText = "موردی یافت نشد",
    disabled = false,
    modalTitle = "انتخاب",
    modalDescription,
    getItemKey,
    getItemLabel,
    getItemSubLabel,
    className,
}: SearchSelectModalProps<T>) {
    const [open, setOpen] = useState(false);
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
                    scrollEl.scrollTop + scrollEl.clientHeight >= scrollEl.scrollHeight - 60
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
    }, [scrollEl]);

    const selectedItemMap = useMemo(() => {
        const map = new Map<number | string, T>();
        for (const item of items) {
            map.set(getItemKey(item), item);
        }
        return map;
    }, [items, getItemKey]);

    const selectedItem = value != null ? selectedItemMap.get(value as number | string) ?? null : null;

    const handleItemSelect = useCallback(
        (key: number | string) => {
            const item = selectedItemMap.get(key);
            if (item) {
                onChange(item);
                setOpen(false);
                onSearchChange("");
            }
        },
        [selectedItemMap, onChange, onSearchChange],
    );

    const handleClear = useCallback(
        (e: React.MouseEvent) => {
            e.stopPropagation();
            onChange(null);
        },
        [onChange],
    );

    const handleOpenChange = useCallback(
        (nextOpen: boolean) => {
            setOpen(nextOpen);
            if (!nextOpen) {
                onSearchChange("");
                setScrollEl(null);
            }
        },
        [onSearchChange],
    );

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogTrigger
                render={
                    <button
                        type="button"
                        disabled={disabled}
                        className={`flex h-8 w-full items-center justify-between gap-2 rounded-lg border border-input bg-transparent px-2.5 py-1 text-base transition-colors outline-none hover:bg-muted focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm ${className ?? ""}`}
                    />
                }
            >
                <span className={selectedItem ? "" : "text-muted-foreground"}>
                    {selectedItem ? getItemLabel(selectedItem) : placeholder}
                </span>
                <div className="flex items-center gap-1">
                    {selectedItem && !disabled && (
                        <span
                            role="button"
                            tabIndex={-1}
                            onClick={handleClear}
                            className="rounded-sm p-0.5 text-muted-foreground hover:text-foreground"
                        >
                            ×
                        </span>
                    )}
                    <IconChevronDown className="size-4 shrink-0 text-muted-foreground" />
                </div>
            </DialogTrigger>

            <DialogContent className="sm:max-w-md gap-0 p-0 overflow-hidden">
                <DialogHeader className="px-4 pt-4 pb-2">
                    <DialogTitle>{modalTitle}</DialogTitle>
                    {modalDescription && (
                        <DialogDescription>{modalDescription}</DialogDescription>
                    )}
                </DialogHeader>

                <div className="px-4 pb-2">
                    <div className="relative">
                        <IconSearch className="absolute start-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            placeholder={searchPlaceholder}
                            value={searchQuery}
                            onChange={(e) => onSearchChange(e.target.value)}
                            className="h-9 ps-8"
                            autoFocus
                        />
                        {isSearchPending && searchQuery && (
                            <IconLoader2 className="absolute end-2.5 top-1/2 size-3.5 -translate-y-1/2 animate-spin text-muted-foreground" />
                        )}
                    </div>
                </div>

                <div
                    ref={setScrollEl}
                    className="max-h-72 overflow-y-auto overscroll-contain px-2 pb-2"
                >
                    {isLoading ? (
                        <SkeletonList />
                    ) : items.length === 0 ? (
                        <EmptyState text={searchQuery ? "نتیجه‌ای یافت نشد" : emptyText} />
                    ) : (
                        <div className="space-y-0.5 p-1">
                            {items.map((item) => {
                                const key = getItemKey(item);
                                return (
                                    <SelectItem
                                        key={key}
                                        item={item}
                                        isSelected={key === value}
                                        onSelect={handleItemSelect}
                                        getItemKey={getItemKey}
                                        getItemLabel={getItemLabel}
                                        getItemSubLabel={getItemSubLabel}
                                    />
                                );
                            })}

                            {isFetchingNextPage && (
                                <div className="flex items-center justify-center gap-2 py-2.5">
                                    <IconLoader2 className="size-3.5 animate-spin text-muted-foreground" />
                                    <span className="text-xs text-muted-foreground">بارگذاری...</span>
                                </div>
                            )}

                            {!hasNextPage && items.length > 0 && (
                                <p className="py-2 text-center text-xs text-muted-foreground">
                                    {items.length} مورد بارگذاری شد
                                </p>
                            )}
                        </div>
                    )}
                </div>

                {onCreateNew && (
                    <div className="border-t px-4 py-2.5">
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => {
                                onCreateNew();
                                setOpen(false);
                                onSearchChange("");
                            }}
                            className="w-full justify-start gap-2"
                        >
                            <IconPlus className="size-4" />
                            ایجاد مورد جدید
                        </Button>
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}
