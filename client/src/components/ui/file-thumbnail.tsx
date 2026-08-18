import * as React from "react";
import { IconEye, IconFile, IconLoader2 } from "@tabler/icons-react";

import { cn } from "@/lib/utils";
import { getFileIcon, getFileColorClasses } from "@/lib/file-utils";

export type ThumbnailFile = {
    name: string;
    type: string;
};

export type FileThumbnailVariant = "thumbnail" | "icon" | "detailed";

export type FileThumbnailProps = {
    file?: ThumbnailFile | File | null;
    className?: string;
    previewAspectRatio?: number;
    previewClassName?: string;
    previewContent?: React.ReactNode;
    previewImageUrl?: string | null;
    isLoading?: boolean;
    hasError?: boolean;
    /** Show a lightbox preview button on hover. */
    onPreview?: () => void;
    /** Display variant: "thumbnail" (default), "icon" (icon only), "detailed" (with name + type). */
    variant?: FileThumbnailVariant;
};

// Preview URLs that have completed a reveal this session. View/tab switches
// remount thumbnails; URLs in this set render instantly instead of replaying
// the blur-in, so only an image's first load animates.
const revealedPreviewImageUrls = new Set<string>();

export function FileThumbnailLoadingOverlay() {
    return (
        <div
            aria-hidden="true"
            className="absolute inset-0 z-10 overflow-hidden bg-muted"
        >
            <div className="absolute inset-0 bg-muted" />
            <div className="absolute inset-0 animate-pulse bg-background/55 motion-reduce:animate-none" />
        </div>
    );
}

function SkeletonLoader({ className }: { className?: string }) {
    return (
        <div
            className={cn(
                "absolute inset-0 z-10 flex items-center justify-center bg-muted",
                className,
            )}
        >
            <IconLoader2 className="size-1/3 animate-spin text-muted-foreground/50" />
        </div>
    );
}

export function FileThumbnail({
    className,
    previewAspectRatio,
    previewClassName,
    previewContent,
    previewImageUrl,
    isLoading = false,
    hasError = false,
    onPreview,
    variant = "thumbnail",
    file,
}: FileThumbnailProps) {
    // ── Null/undefined file: show skeleton placeholder ──
    if (!file) {
        return (
            <div
                className={cn(
                    "relative overflow-hidden rounded-lg border bg-background",
                    className,
                )}
            >
                <div
                    className={cn(
                        "relative aspect-square bg-muted",
                        previewClassName,
                    )}
                    style={
                        previewAspectRatio
                            ? { aspectRatio: String(previewAspectRatio) }
                            : undefined
                    }
                >
                    <SkeletonLoader />
                </div>
            </div>
        );
    }

    const imageRef = React.useRef<HTMLImageElement | null>(null);
    const revealFrameRef = React.useRef<number | null>(null);
    const [loadedPreviewImageUrl, setLoadedPreviewImageUrl] = React.useState<
        string | null
    >(() =>
        previewImageUrl && revealedPreviewImageUrls.has(previewImageUrl)
            ? previewImageUrl
            : null,
    );
    const [failedPreviewImageUrl, setFailedPreviewImageUrl] = React.useState<
        string | null
    >(null);
    const previousPreviewImageUrl = React.useRef<string | null>(
        previewImageUrl,
    );
    const imageFailed = Boolean(
        previewImageUrl && failedPreviewImageUrl === previewImageUrl,
    );
    const isImageLoading = Boolean(
        previewImageUrl &&
        loadedPreviewImageUrl !== previewImageUrl &&
        !imageFailed &&
        !revealedPreviewImageUrls.has(previewImageUrl),
    );
    const showLoading = isLoading || isImageLoading;
    const hasPreviewContent = Boolean(previewContent);
    const showFallback =
        !showLoading &&
        (hasError || imageFailed || (!previewImageUrl && !hasPreviewContent));
    const cancelImageReveal = React.useCallback(() => {
        if (revealFrameRef.current === null) return;

        window.cancelAnimationFrame(revealFrameRef.current);
        revealFrameRef.current = null;
    }, []);
    const markImageLoaded = React.useCallback(
        (image: HTMLImageElement, imageUrl: string | null | undefined) => {
            if (!imageUrl) return;

            const didLoad = image.naturalWidth > 0 && image.naturalHeight > 0;

            setFailedPreviewImageUrl(didLoad ? null : imageUrl);
            if (didLoad) {
                revealedPreviewImageUrls.add(imageUrl);
                cancelImageReveal();
                revealFrameRef.current = window.requestAnimationFrame(() => {
                    revealFrameRef.current = window.requestAnimationFrame(
                        () => {
                            setLoadedPreviewImageUrl(imageUrl);
                            revealFrameRef.current = null;
                        },
                    );
                });
            }
        },
        [cancelImageReveal],
    );

    React.useEffect(() => {
        cancelImageReveal();
    }, [cancelImageReveal, previewImageUrl]);

    React.useEffect(() => {
        if (previousPreviewImageUrl.current === previewImageUrl) return;

        previousPreviewImageUrl.current = previewImageUrl;
        setLoadedPreviewImageUrl(null);
        setFailedPreviewImageUrl(null);
    }, [previewImageUrl]);

    React.useEffect(() => cancelImageReveal, [cancelImageReveal]);

    React.useEffect(() => {
        const image = imageRef.current;

        if (!image || !previewImageUrl) return;

        if (image.complete) {
            markImageLoaded(image, previewImageUrl);
        }
    }, [markImageLoaded, previewImageUrl]);

    // ── Variant: icon (just the file type icon) ──
    if (variant === "icon") {
        const mimeType = typeof file === "object" && "type" in file ? file.type : "";
        return (
            <div
                className={cn(
                    "flex shrink-0 items-center justify-center rounded-md",
                    getFileColorClasses(mimeType),
                    className,
                )}
            >
                {getFileIcon(mimeType, "size-1/2")}
            </div>
        );
    }

    // ── Variant: detailed (thumbnail + name + type) ──
    if (variant === "detailed") {
        const fileName = typeof file === "object" && "name" in file ? file.name : "";
        const mimeType = typeof file === "object" && "type" in file ? file.type : "";
        const isImage = mimeType.startsWith("image/");

        return (
            <div className={cn("flex items-start gap-3", className)}>
                <div
                    className={cn(
                        "relative shrink-0 overflow-hidden rounded-md bg-muted",
                        previewClassName,
                    )}
                    style={
                        previewAspectRatio
                            ? { aspectRatio: String(previewAspectRatio) }
                            : undefined
                    }
                >
                    {previewImageUrl && isImage ? (
                        <>
                            {/* eslint-disable-next-line @next/next/no-img-element */}
                            <img
                                ref={imageRef}
                                src={previewImageUrl}
                                alt=""
                                draggable={false}
                                loading="lazy"
                                decoding="async"
                                className={cn(
                                    "absolute inset-0 block size-full object-cover transition-[opacity,filter] duration-160 ease-[cubic-bezier(0.22,1,0.36,1)] motion-reduce:transition-none",
                                    showLoading
                                        ? "opacity-0 blur-sm"
                                        : "blur-0 opacity-100",
                                )}
                                onLoad={(event) =>
                                    markImageLoaded(event.currentTarget, previewImageUrl)
                                }
                                onError={() => {
                                    if (previewImageUrl) {
                                        revealedPreviewImageUrls.delete(previewImageUrl);
                                        cancelImageReveal();
                                        setFailedPreviewImageUrl(previewImageUrl);
                                        setLoadedPreviewImageUrl((c) =>
                                            c === previewImageUrl ? null : c,
                                        );
                                    }
                                }}
                            />
                            {showLoading && <SkeletonLoader />}
                        </>
                    ) : (
                        <div
                            className={cn(
                                "flex size-full items-center justify-center",
                                getFileColorClasses(mimeType),
                            )}
                        >
                            {getFileIcon(mimeType, "size-1/2")}
                        </div>
                    )}
                    {onPreview && !showLoading && (
                        <button
                            type="button"
                            onClick={(e) => {
                                e.stopPropagation();
                                onPreview();
                            }}
                            className="absolute inset-0 z-20 flex items-center justify-center rounded-md bg-black/0 text-white opacity-0 transition-all hover:bg-black/40 hover:opacity-100"
                            aria-label="پیش‌نمایش"
                        >
                            <IconEye className="size-5" />
                        </button>
                    )}
                </div>
                <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-medium">{fileName}</p>
                </div>
            </div>
        );
    }

    // ── Variant: thumbnail (default) ──
    return (
        <div
            className={cn(
                "group relative overflow-hidden rounded-lg border bg-background text-foreground",
                className,
            )}
        >
            <div
                className={cn(
                    "relative aspect-square overflow-hidden bg-muted contain-[layout_paint]",
                    previewClassName,
                )}
                style={
                    previewAspectRatio
                        ? { aspectRatio: String(previewAspectRatio) }
                        : undefined
                }
            >
                {previewImageUrl ? (
                    // eslint-disable-next-line @next/next/no-img-element -- Preview URLs can be transient object or presigned URLs outside Next image optimization.
                    <img
                        ref={imageRef}
                        src={previewImageUrl}
                        alt=""
                        draggable={false}
                        loading="lazy"
                        decoding="async"
                        className={cn(
                            "absolute inset-0 block size-full object-cover transition-[opacity,filter] duration-160 ease-[cubic-bezier(0.22,1,0.36,1)] motion-reduce:transition-none",
                            showLoading
                                ? "opacity-0 blur-sm"
                                : "blur-0 opacity-100",
                        )}
                        onLoad={(event) => {
                            markImageLoaded(
                                event.currentTarget,
                                previewImageUrl,
                            );
                        }}
                        onError={() => {
                            if (previewImageUrl) {
                                revealedPreviewImageUrls.delete(
                                    previewImageUrl,
                                );
                                cancelImageReveal();
                                setFailedPreviewImageUrl(previewImageUrl);
                                setLoadedPreviewImageUrl((currentUrl) =>
                                    currentUrl === previewImageUrl
                                        ? null
                                        : currentUrl,
                                );
                            }
                        }}
                    />
                ) : null}
                {previewContent ? (
                    <div
                        className={cn(
                            "absolute inset-0 size-full transition-[opacity,filter] duration-160 ease-[cubic-bezier(0.22,1,0.36,1)] motion-reduce:transition-none",
                            showLoading
                                ? "opacity-0 blur-sm"
                                : "blur-0 opacity-100",
                        )}
                    >
                        {previewContent}
                    </div>
                ) : null}
                {showLoading ? <SkeletonLoader /> : null}
                {showFallback ? (
                    <div
                        className="absolute inset-0 bg-muted"
                        aria-hidden="true"
                    />
                ) : null}
                {onPreview && !showLoading && !showFallback && (
                    <button
                        type="button"
                        onClick={(e) => {
                            e.stopPropagation();
                            onPreview();
                        }}
                        className="absolute inset-0 z-20 flex items-center justify-center rounded-lg bg-black/0 text-white opacity-0 transition-all group-hover:bg-black/40 group-hover:opacity-100"
                        aria-label="پیش‌نمایش"
                    >
                        <IconEye className="size-5" />
                    </button>
                )}
            </div>
        </div>
    );
}
