import * as React from "react";
import { createPortal } from "react-dom";
import {
    IconChevronLeft,
    IconChevronRight,
    IconDownload,
    IconFocus2,
    IconInfoCircle,
    IconMaximize,
    IconMinimize,
    IconX,
    IconZoomIn,
    IconZoomOut,
} from "@tabler/icons-react";

import { cn } from "@/lib/utils";
import { toPersianDate } from "@/lib/date-format";
import {
    getFileColorClasses,
    getFileIcon,
    getFileTypeLabel,
} from "@/lib/file-utils";
import { renderPdfThumbnailUrl } from "@/lib/pdf-thumbnail-utils";
import type { Document } from "@/features/documents/types";
import {
    getDocDownloadUrl,
    getDocFileSizeFormatted,
    getDocMimeType,
    getDocOriginalName,
    getDocServeUrl,
} from "@/features/documents/types";
import { getFieldKeyLabel } from "@/features/questionnaire/constants";

import {
    Carousel,
    CarouselContent,
    CarouselItem,
    type CarouselApi,
} from "@/components/ui/carousel";

type DocumentPreviewLightboxProps = {
    documents: Document[];
    currentIndex: number;
    open: boolean;
    onClose: () => void;
    onNavigate: (index: number) => void;
};

const AUTO_HIDE_DELAY = 3000;

function PreviewContent({ doc }: { doc: Document }) {
    const [pdfImageUrl, setPdfImageUrl] = React.useState<string | null>(null);
    const [imageLoaded, setImageLoaded] = React.useState(false);
    const [lastDocId, setLastDocId] = React.useState(doc.id);
    if (doc.id !== lastDocId) {
        // New document selected: reset the load flag during render instead of
        // in an effect, so the spinner shows for the new doc immediately.
        setLastDocId(doc.id);
        setImageLoaded(false);
    }

    React.useEffect(() => {
        if (getDocMimeType(doc) !== "application/pdf" || !getDocServeUrl(doc, true)) return;

        let isCurrent = true;
        renderPdfThumbnailUrl({
            pageIndex: 0,
            url: getDocServeUrl(doc, true),
            width: 800,
        }).then((url) => {
            if (isCurrent) setPdfImageUrl(url);
        });

        return () => {
            isCurrent = false;
        };
    }, [doc]);

    if (getDocMimeType(doc).startsWith("image/")) {
        return (
            <div className="relative flex size-full items-center justify-center">
                {!imageLoaded && (
                    <div className="flex flex-col items-center gap-3">
                        {getFileIcon(getDocMimeType(doc), "size-12 text-white/70")}
                        <span className="text-sm text-white/70">
                            در حال بارگذاری...
                        </span>
                    </div>
                )}
                <img
                    src={getDocServeUrl(doc)}
                    alt={getDocOriginalName(doc)}
                    className={cn(
                        "max-h-[80dvh] max-w-[90dvw] object-contain",
                        imageLoaded ? "block" : "hidden",
                    )}
                    draggable={false}
                    onLoad={() => setImageLoaded(true)}
                />
            </div>
        );
    }

    if (getDocMimeType(doc) === "application/pdf") {
        return (
            <div
                className={cn(
                    "flex aspect-3/4 w-full max-w-md items-center justify-center overflow-hidden rounded-lg",
                    getFileColorClasses("application/pdf"),
                )}
            >
                {pdfImageUrl ? (
                    <img
                        src={pdfImageUrl}
                        alt={getDocOriginalName(doc)}
                        className="size-full object-contain"
                    />
                ) : (
                    <div className="flex flex-col items-center gap-3">
                        {getFileIcon("application/pdf", "size-12")}
                        <span className="text-sm opacity-70">
                            در حال بارگذاری...
                        </span>
                    </div>
                )}
            </div>
        );
    }

    return (
        <div
            className={cn(
                "flex aspect-video w-full max-w-md flex-col items-center justify-center gap-3 rounded-lg",
                getFileColorClasses(getDocMimeType(doc)),
            )}
        >
            {getFileIcon(getDocMimeType(doc), "size-16")}
            <span className="text-xs opacity-70">
                {getFileTypeLabel(getDocMimeType(doc))}
            </span>
        </div>
    );
}

export function DocumentPreviewLightbox({
    documents,
    currentIndex,
    open,
    onClose,
    onNavigate,
}: DocumentPreviewLightboxProps) {
    const [controlsVisible, setControlsVisible] = React.useState(true);
    const hideTimerRef = React.useRef<ReturnType<typeof setTimeout> | null>(
        null,
    );
    const [showInfo, setShowInfo] = React.useState(false);
    const [showThumbs, setShowThumbs] = React.useState(false);
    const [zoomed, setZoomed] = React.useState(false);
    const [fullscreen, setFullscreen] = React.useState(false);
    const [selectedIndex, setSelectedIndex] = React.useState(currentIndex);
    const [api, setApi] = React.useState<CarouselApi>(undefined);

    const rootRef = React.useRef<HTMLDivElement>(null);
    const syncingRef = React.useRef(false);

    // Reading direction decides which physical side is "previous": in RTL
    // (fa) the trail continues right-to-left, so the previous button sits at
    // the END edge and the next button at the START edge, mirrored from LTR.
    const isRtl =
        typeof document !== "undefined" &&
        document.documentElement
            ?.getAttribute("dir")
            ?.toLowerCase() === "rtl";

    const doc = documents[currentIndex];
    const hasPrev = currentIndex > 0;
    const hasNext = currentIndex < documents.length - 1;

    const resetHideTimer = React.useCallback(() => {
        setControlsVisible(true);
        if (hideTimerRef.current) clearTimeout(hideTimerRef.current);
        hideTimerRef.current = setTimeout(() => {
            setControlsVisible(false);
            setShowInfo(false);
            setShowThumbs(false);
        }, AUTO_HIDE_DELAY);
    }, []);

    React.useEffect(() => {
        if (!open) return;
        resetHideTimer();
        return () => {
            if (hideTimerRef.current) clearTimeout(hideTimerRef.current);
        };
    }, [open, resetHideTimer]);

    React.useEffect(() => {
        if (!open) return;

        function handleKeyDown(e: KeyboardEvent) {
            if (e.key === "Escape") onClose();
            // Arrows follow reading direction: in RTL, ArrowLeft means
            // "next" and ArrowRight "previous".
            const prevKey = isRtl ? "ArrowRight" : "ArrowLeft";
            const nextKey = isRtl ? "ArrowLeft" : "ArrowRight";
            if (e.key === prevKey && hasPrev) onNavigate(currentIndex - 1);
            if (e.key === nextKey && hasNext) onNavigate(currentIndex + 1);
            resetHideTimer();
        }

        window.addEventListener("keydown", handleKeyDown);
        return () => window.removeEventListener("keydown", handleKeyDown);
    }, [
        open,
        currentIndex,
        hasPrev,
        hasNext,
        isRtl,
        onClose,
        onNavigate,
        resetHideTimer,
    ]);

    React.useEffect(() => {
        if (!open) return;
        document.body.style.overflow = "hidden";
        return () => {
            document.body.style.overflow = "";
        };
    }, [open]);

    // Sync the embla carousel's internal position with the externally owned
    // index whenever it changes (keyboard arrows, external triggers).
    React.useEffect(() => {
        if (!open || !api) return;
        syncingRef.current = true;
        api.scrollTo(currentIndex, true);
        setSelectedIndex(currentIndex);
        // Delay releasing the sync guard until the embla "select" event fires
        // for this programmatic scroll, so the guard isn't cleared mid-anim.
        const release = window.setTimeout(() => {
            syncingRef.current = false;
        }, 0);
        return () => window.clearTimeout(release);
    }, [open, api, currentIndex]);

    // Mirror embla-driven navigation (swipe, dots, thumbs) back into the store
    // via onNavigate, keeping the single source of truth at the store.
    const handleSelect = React.useCallback(
        (nextApi: CarouselApi) => {
            const index = nextApi?.selectedScrollSnap() ?? 0;
            setSelectedIndex(index);
            setZoomed(false);
            if (syncingRef.current) return;
            onNavigate(index);
        },
        [onNavigate],
    );

    React.useEffect(() => {
        if (!api) return;
        api.on("select", handleSelect);
        return () => {
            api.off("select", handleSelect);
        };
    }, [api, handleSelect]);

    React.useEffect(() => {
        function handleFullscreenChange() {
            setFullscreen(
                Boolean(document.fullscreenElement && rootRef.current?.contains(document.fullscreenElement)),
            );
        }
        document.addEventListener("fullscreenchange", handleFullscreenChange);
        return () =>
            document.removeEventListener("fullscreenchange", handleFullscreenChange);
    }, []);

    function toggleFullscreen() {
        if (document.fullscreenElement) {
            void document.exitFullscreen();
        } else {
            void rootRef.current?.requestFullscreen();
        }
    }

    function handleDownload() {
        if (!doc?.url && !doc?.download_url) return;
        const a = document.createElement("a");
        a.href = getDocDownloadUrl(doc);
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    }

    if (!open || !doc) return null;

    const goTo = (index: number) => api?.scrollTo(index);

    return createPortal(
        <div
            ref={rootRef}
            className={cn(
                "fixed inset-0 z-50 flex flex-col bg-black/80 backdrop-blur-sm",
                fullscreen && "rounded-none",
            )}
            onMouseMove={resetHideTimer}
            onClick={(e) => {
                if (e.target === e.currentTarget) onClose();
            }}
        >
            {/* Top bar */}
            <div
                className={cn(
                    "absolute inset-x-0 top-0 z-20 flex items-center justify-between bg-linear-to-b from-black/60 to-transparent px-4 py-3 transition-opacity duration-300",
                    controlsVisible ? "opacity-100" : "opacity-0",
                )}
            >
                <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-medium text-white">
                        {getDocOriginalName(doc)}
                    </p>
                    {(getFieldKeyLabel(doc.field_key) || doc.category?.name || doc.notes) && (
                        <p className="truncate text-xs text-white/60">
                            {getFieldKeyLabel(doc.field_key) ?? doc.notes ?? doc.category?.name}
                        </p>
                    )}
                </div>
                <div className="flex items-center gap-1">
                    <button
                        type="button"
                        onClick={() => setShowInfo((prev) => !prev)}
                        className="rounded-full p-2 text-white/80 hover:bg-white/10 hover:text-white"
                        aria-label="Info"
                    >
                        <IconInfoCircle className="size-5" />
                    </button>
                    <button
                        type="button"
                        onClick={() => setShowThumbs((prev) => !prev)}
                        className={cn(
                            "rounded-full p-2 text-white/80 hover:bg-white/10 hover:text-white",
                            showThumbs && "bg-white/10 text-white",
                        )}
                        aria-label="Thumbnails"
                    >
                        <IconFocus2 className="size-5" />
                    </button>
                    <button
                        type="button"
                        onClick={() => setZoomed((prev) => !prev)}
                        className={cn(
                            "rounded-full p-2 text-white/80 hover:bg-white/10 hover:text-white",
                            zoomed && "bg-white/10 text-white",
                        )}
                        aria-label="Zoom"
                    >
                        {zoomed ? (
                            <IconZoomOut className="size-5" />
                        ) : (
                            <IconZoomIn className="size-5" />
                        )}
                    </button>
                    <button
                        type="button"
                        onClick={toggleFullscreen}
                        className={cn(
                            "rounded-full p-2 text-white/80 hover:bg-white/10 hover:text-white",
                            fullscreen && "bg-white/10 text-white",
                        )}
                        aria-label="Fullscreen"
                    >
                        {fullscreen ? (
                            <IconMinimize className="size-5" />
                        ) : (
                            <IconMaximize className="size-5" />
                        )}
                    </button>
                    <button
                        type="button"
                        onClick={handleDownload}
                        disabled={!doc.download_url && !doc.url}
                        className="rounded-full p-2 text-white/80 hover:bg-white/10 hover:text-white disabled:opacity-40"
                        aria-label="Download"
                    >
                        <IconDownload className="size-5" />
                    </button>
                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded-full p-2 text-white/80 hover:bg-white/10 hover:text-white"
                        aria-label="Close"
                    >
                        <IconX className="size-5" />
                    </button>
                </div>
            </div>

            {/* Info panel */}
            <div
                className={cn(
                    "absolute top-14 end-4 z-20 w-64 rounded-lg border border-white/10 bg-black/70 p-3 text-sm text-white backdrop-blur-md transition-opacity duration-300",
                    showInfo && controlsVisible
                        ? "opacity-100"
                        : "opacity-0 pointer-events-none",
                )}
            >
                <div className="flex flex-col gap-2">
                    <InfoRow
                        label="نوع"
                        value={getFileTypeLabel(getDocMimeType(doc))}
                    />
                    <InfoRow label="اندازه" value={getDocFileSizeFormatted(doc)} />
                    {doc.uploaded_by && (
                        <InfoRow
                            label="آپلود توسط"
                            value={String(doc.uploaded_by)}
                        />
                    )}
                    {doc.created_at && (
                        <InfoRow
                            label="تاریخ"
                            value={toPersianDate(doc.created_at)}
                        />
                    )}
                    {doc.notes && (
                        <div className="pt-1">
                            <span className="text-white/50 text-xs">
                                یادداشت
                            </span>
                            <p className="text-xs text-white/80 whitespace-pre-wrap">
                                {doc.notes}
                            </p>
                        </div>
                    )}
                </div>
            </div>

            {/* Carousel stage — embla owns swipe/hotkeys; loop wraps first↔last. */}
            <Carousel
                className="flex min-h-0 flex-1 items-center justify-center"
                opts={{
                    align: "center",
                    loop: true,
                    direction: isRtl ? "rtl" : "ltr",
                }}
                setApi={setApi}
            >
                <CarouselContent className="h-full items-center px-12 py-16 sm:px-20">
                    {documents.map((item) => (
                        <CarouselItem key={item.id} className="flex justify-center">
                            <div
                                className={cn(
                                    "transition-transform duration-300",
                                    zoomed && "scale-150",
                                )}
                            >
                                <PreviewContent doc={item} />
                            </div>
                        </CarouselItem>
                    ))}
                </CarouselContent>

                {hasPrev && (
                    <button
                        type="button"
                        onClick={() => onNavigate(currentIndex - 1)}
                        className={cn(
                            "absolute top-1/2 start-2 z-10 -translate-y-1/2 rounded-full bg-white/10 p-2 text-white backdrop-blur-sm transition-all duration-300 hover:bg-white/20 sm:start-4 sm:p-3",
                            controlsVisible
                                ? "opacity-100"
                                : "opacity-0 pointer-events-none",
                        )}
                        aria-label="Previous"
                    >
                        <IconChevronLeft className="size-5 rtl:-scale-x-100 sm:size-6" />
                    </button>
                )}
                {hasNext && (
                    <button
                        type="button"
                        onClick={() => onNavigate(currentIndex + 1)}
                        className={cn(
                            "absolute top-1/2 end-2 z-10 -translate-y-1/2 rounded-full bg-white/10 p-2 text-white backdrop-blur-sm transition-all duration-300 hover:bg-white/20 sm:end-4 sm:p-3",
                            controlsVisible
                                ? "opacity-100"
                                : "opacity-0 pointer-events-none",
                        )}
                        aria-label="Next"
                    >
                        <IconChevronRight className="size-5 rtl:-scale-x-100 sm:size-6" />
                    </button>
                )}
            </Carousel>

            {/* Dot indicators */}
            {documents.length > 1 && (
                <div
                    className={cn(
                        "absolute bottom-24 start-1/2 z-10 flex -translate-x-1/2 items-center gap-1.5 rtl:translate-x-1/2 transition-opacity duration-300",
                        controlsVisible ? "opacity-100" : "opacity-0",
                    )}
                >
                    {documents.map((item, index) => (
                        <button
                            key={item.id}
                            type="button"
                            onClick={() => goTo(index)}
                            aria-label={`Go to slide ${index + 1}`}
                            className={cn(
                                "size-2 rounded-full transition-all duration-300",
                                index === selectedIndex
                                    ? "w-4 bg-white"
                                    : "bg-white/40 hover:bg-white/70",
                            )}
                        />
                    ))}
                </div>
            )}

            {/* Thumbnails strip — toggled while open */}
            <div
                className={cn(
                    "absolute inset-x-0 bottom-0 z-10 flex justify-center gap-2 bg-linear-to-t from-black/70 to-transparent px-4 pt-10 pb-4 transition-opacity duration-300",
                    showThumbs && controlsVisible
                        ? "opacity-100"
                        : "opacity-0 pointer-events-none",
                )}
            >
                {documents.map((item, index) => {
                    const isActive = index === selectedIndex;
                    return (
                        <button
                            key={item.id}
                            type="button"
                            onClick={() => goTo(index)}
                            aria-label={getDocOriginalName(item)}
                            className={cn(
                                "h-16 w-20 shrink-0 overflow-hidden rounded-md border-2 bg-white/10 transition-all duration-300",
                                isActive
                                    ? "border-brand opacity-100"
                                    : "border-transparent opacity-50 hover:opacity-80",
                            )}
                        >
                            <Thumbnail doc={item} />
                        </button>
                    );
                })}
            </div>

            {/* Bottom counter */}
            <div
                className={cn(
                    "absolute bottom-4 start-1/2 z-10 translate-x-[-50%] rtl:translate-x-[50%] rounded-full bg-black/50 px-3 py-1 text-xs text-white/70 backdrop-blur-sm transition-opacity duration-300",
                    controlsVisible ? "opacity-100" : "opacity-0",
                )}
            >
                {currentIndex + 1} / {documents.length}
            </div>
        </div>,
        document.body,
    );
}

function Thumbnail({ doc }: { doc: Document }) {
    const mime = getDocMimeType(doc);
    if (mime.startsWith("image/")) {
        return (
            <img
                src={getDocServeUrl(doc, true)}
                alt={getDocOriginalName(doc)}
                className="size-full object-cover"
                draggable={false}
                loading="lazy"
            />
        );
    }
    return (
        <div
            className={cn(
                "flex size-full items-center justify-center",
                getFileColorClasses(mime),
            )}
        >
            {getFileIcon(mime, "size-5")}
        </div>
    );
}

function InfoRow({
    label,
    value,
}: {
    label: string;
    value: string | null | undefined;
}) {
    if (!value) return null;
    return (
        <div className="flex items-baseline justify-between gap-2">
            <span className="text-white/50 text-xs">{label}</span>
            <span className="text-xs text-white/90">{value}</span>
        </div>
    );
}
