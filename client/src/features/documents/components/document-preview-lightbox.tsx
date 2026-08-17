import * as React from "react";
import { createPortal } from "react-dom";
import {
    IconChevronLeft,
    IconChevronRight,
    IconDownload,
    IconInfoCircle,
    IconX,
} from "@tabler/icons-react";

import { cn } from "@/lib/utils";
import { getFileIcon } from "@/lib/file-utils";
import { getFileColorClasses } from "@/lib/file-utils";
import { getFileTypeLabel } from "@/lib/file-utils";
import { renderPdfThumbnailUrl } from "@/lib/pdf-thumbnail-utils";
import type { Document } from "@/features/documents/types";
import { getDocOriginalName, getDocMimeType, getDocFileSizeFormatted, getDocServeUrl, getDocDownloadUrl } from "@/features/documents/types";
import { getFieldKeyLabel } from "@/features/questionnaire/constants";

type DocumentPreviewLightboxProps = {
    documents: Document[];
    currentIndex: number;
    open: boolean;
    onClose: () => void;
    onNavigate: (index: number) => void;
};

const AUTO_HIDE_DELAY = 3000;
const SWIPE_THRESHOLD = 50;

function PreviewContent({ doc }: { doc: Document }) {
    const [pdfImageUrl, setPdfImageUrl] = React.useState<string | null>(null);
    const [imageLoaded, setImageLoaded] = React.useState(false);

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

    React.useEffect(() => {
        setImageLoaded(false);
    }, [doc.id]);

    if (getDocMimeType(doc).startsWith("image/")) {
        return (
            <div className="relative flex items-center justify-center">
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
    const touchStartRef = React.useRef<{ x: number; y: number } | null>(null);
    const [showInfo, setShowInfo] = React.useState(false);

    const doc = documents[currentIndex];
    const hasPrev = currentIndex > 0;
    const hasNext = currentIndex < documents.length - 1;

    const resetHideTimer = React.useCallback(() => {
        setControlsVisible(true);
        if (hideTimerRef.current) clearTimeout(hideTimerRef.current);
        hideTimerRef.current = setTimeout(() => {
            setControlsVisible(false);
            setShowInfo(false);
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
            if (e.key === "ArrowLeft" && hasPrev) onNavigate(currentIndex - 1);
            if (e.key === "ArrowRight" && hasNext) onNavigate(currentIndex + 1);
            resetHideTimer();
        }

        window.addEventListener("keydown", handleKeyDown);
        return () => window.removeEventListener("keydown", handleKeyDown);
    }, [
        open,
        currentIndex,
        hasPrev,
        hasNext,
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

    function handleTouchStart(e: React.TouchEvent) {
        touchStartRef.current = {
            x: e.touches[0].clientX,
            y: e.touches[0].clientY,
        };
    }

    function handleTouchEnd(e: React.TouchEvent) {
        if (!touchStartRef.current) return;
        const dx = e.changedTouches[0].clientX - touchStartRef.current.x;
        const dy = e.changedTouches[0].clientY - touchStartRef.current.y;
        touchStartRef.current = null;

        if (Math.abs(dx) > Math.abs(dy) && Math.abs(dx) > SWIPE_THRESHOLD) {
            if (dx > 0 && hasPrev) onNavigate(currentIndex - 1);
            if (dx < 0 && hasNext) onNavigate(currentIndex + 1);
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

    return createPortal(
        <div
            className="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm"
            onMouseMove={resetHideTimer}
            onClick={(e) => {
                if (e.target === e.currentTarget) onClose();
            }}
            onTouchStart={handleTouchStart}
            onTouchEnd={handleTouchEnd}
        >
            {/* Top bar */}
            <div
                className={cn(
                    "absolute inset-x-0 top-0 flex items-center justify-between bg-linear-to-b from-black/60 to-transparent px-4 py-3 transition-opacity duration-300",
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
                    "absolute right-4 top-14 w-64 rounded-lg border border-white/10 bg-black/70 p-3 text-sm text-white backdrop-blur-md transition-opacity duration-300",
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
                            value={doc.created_at.split("T")[0]}
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

            {/* Navigation arrows */}
            {hasPrev && (
                <button
                    type="button"
                    onClick={() => onNavigate(currentIndex - 1)}
                    className={cn(
                        "absolute left-2 top-1/2 -translate-y-1/2 rounded-full bg-white/10 p-2 text-white backdrop-blur-sm transition-all duration-300 hover:bg-white/20 sm:left-4 sm:p-3",
                        controlsVisible
                            ? "opacity-100"
                            : "opacity-0 pointer-events-none",
                    )}
                    aria-label="Previous"
                >
                    <IconChevronLeft className="size-5 sm:size-6" />
                </button>
            )}
            {hasNext && (
                <button
                    type="button"
                    onClick={() => onNavigate(currentIndex + 1)}
                    className={cn(
                        "absolute right-2 top-1/2 -translate-y-1/2 rounded-full bg-white/10 p-2 text-white backdrop-blur-sm transition-all duration-300 hover:bg-white/20 sm:right-4 sm:p-3",
                        controlsVisible
                            ? "opacity-100"
                            : "opacity-0 pointer-events-none",
                    )}
                    aria-label="Next"
                >
                    <IconChevronRight className="size-5 sm:size-6" />
                </button>
            )}

            {/* Center content */}
            <div className="flex items-center justify-center px-12 py-16 sm:px-20">
                <PreviewContent doc={doc} />
            </div>

            {/* Bottom counter */}
            <div
                className={cn(
                    "absolute bottom-4 left-1/2 -translate-x-1/2 rounded-full bg-black/50 px-3 py-1 text-xs text-white/70 backdrop-blur-sm transition-opacity duration-300",
                    controlsVisible ? "opacity-100" : "opacity-0",
                )}
            >
                {currentIndex + 1} / {documents.length}
            </div>
        </div>,
        document.body,
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
