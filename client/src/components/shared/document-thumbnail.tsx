"use client";

import * as React from "react";
import { IconDownload } from "@tabler/icons-react";

import { cn } from "@/lib/utils";
import { getFileIcon } from "@/lib/file-icon";
import { getFileColorClasses } from "@/lib/file-colors";
import { renderPdfThumbnailUrl } from "@/lib/pdf-thumbnail-utils";
import { FileThumbnail } from "@/components/ui/file-thumbnail";
import { Button } from "@/components/ui/button";
import { ConfirmDeleteButton } from "@/components/ui/confirm-delete-button";
import { DocumentPreviewLightbox } from "@/features/documents/components/document-preview-lightbox";
import {
    Document,
    DocumentCategory,
    getDocOriginalName,
    getDocMimeType,
    getDocFileSizeFormatted,
    getDocServeUrl,
    buildParentPath,
    getExactCategoryName,
} from "@/features/documents/types";

const SIZE_MAP = {
    xs: "size-7",
    sm: "size-10",
    md: "size-16",
    lg: "size-24",
} as const;

type DocumentThumbnailProps = {
    document: Document;
    categories?: DocumentCategory[];
    variant?: "icon" | "compact" | "default" | "detailed";
    size?: "xs" | "sm" | "md" | "lg" | number;
    aspectRatio?: number;
    showName?: boolean;
    showCategory?: boolean;
    showSize?: boolean;
    showUploader?: boolean;
    clickable?: boolean;
    onPreview?: () => void;
    showActions?: boolean;
    onDownload?: (doc: Document) => void;
    onDelete?: (doc: Document) => void;
    isDeleting?: boolean;
    isConfirming?: boolean;
    onStartDelete?: (id: number) => void;
    onConfirmDelete?: (id: number) => void;
    onCancelDelete?: () => void;
    className?: string;
};

function PdfPreview({ url, className }: { url: string; className?: string }) {
    const [imageUrl, setImageUrl] = React.useState<string | null>(null);

    React.useEffect(() => {
        let isCurrent = true;
        renderPdfThumbnailUrl({ pageIndex: 0, url, width: 240 }).then(
            (nextUrl) => {
                if (isCurrent) setImageUrl(nextUrl);
            },
        );
        return () => {
            isCurrent = false;
        };
    }, [url]);

    if (imageUrl) {
        return (
            <div className={cn("overflow-hidden bg-white", className)}>
                <img
                    src={imageUrl}
                    alt=""
                    className="size-full object-cover"
                />
            </div>
        );
    }

    return (
        <div
            className={cn(
                "flex items-center justify-center",
                getFileColorClasses("application/pdf"),
                className,
            )}
        >
            {getFileIcon("application/pdf", "size-1/2")}
        </div>
    );
}

function ThumbnailPreview({
    document: doc,
    size,
    aspectRatio,
}: {
    document: Document;
    size: string;
    aspectRatio?: number;
}) {
    const mimeType = getDocMimeType(doc);
    const isImage = mimeType.startsWith("image/");
    const isPdf = mimeType === "application/pdf";

    const sizeClass =
        typeof size === "number"
            ? ""
            : SIZE_MAP[size as keyof typeof SIZE_MAP] ?? "size-10";

    return (
        <div
            className={cn(
                "relative shrink-0 overflow-hidden rounded-md bg-muted",
                sizeClass,
                aspectRatio ? "" : "aspect-square",
            )}
            style={aspectRatio ? { aspectRatio: String(aspectRatio) } : undefined}
        >
            {isPdf ? (
                <PdfPreview url={getDocServeUrl(doc, true)} className="size-full" />
            ) : isImage ? (
                <FileThumbnail
                    file={{ name: getDocOriginalName(doc), type: mimeType }}
                    previewImageUrl={getDocServeUrl(doc, true)}
                    className="size-full rounded-none border-0"
                    previewClassName="aspect-square"
                />
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
        </div>
    );
}

function ThumbnailClickHandler({
    doc,
    onClick,
    className,
    children,
}: {
    doc: Document;
    onClick?: () => void;
    className?: string;
    children: React.ReactNode;
}) {
    const [lightboxOpen, setLightboxOpen] = React.useState(false);

    function handleClick() {
        if (onClick) {
            onClick();
        } else {
            setLightboxOpen(true);
        }
    }

    return (
        <>
            <div
                className={className}
                onClick={handleClick}
                role="button"
                tabIndex={0}
                onKeyDown={(e) => {
                    if (e.key === "Enter" || e.key === " ") {
                        e.preventDefault();
                        handleClick();
                    }
                }}
            >
                {children}
            </div>
            <DocumentPreviewLightbox
                documents={[doc]}
                currentIndex={0}
                open={lightboxOpen}
                onClose={() => setLightboxOpen(false)}
                onNavigate={() => {}}
            />
        </>
    );
}

export function DocumentThumbnail({
    document: doc,
    categories,
    variant = "default",
    size = "sm",
    aspectRatio,
    showName = false,
    showCategory = false,
    showSize = false,
    showUploader = false,
    clickable = false,
    onPreview,
    showActions = false,
    onDownload,
    onDelete,
    isDeleting = false,
    isConfirming = false,
    onStartDelete,
    onConfirmDelete,
    onCancelDelete,
    className,
}: DocumentThumbnailProps) {
    const mimeType = getDocMimeType(doc);
    const originalName = getDocOriginalName(doc);
    const categoryPath = categories
        ? buildParentPath(categories, doc.document_category_id)
        : null;
    const categoryName = categories
        ? getExactCategoryName(categories, doc.document_category_id)
        : doc.category?.name;

    if (variant === "icon") {
        return (
            <div
                className={cn(
                    "flex shrink-0 items-center justify-center rounded-md",
                    typeof size === "number" ? "" : SIZE_MAP[size] ?? "size-10",
                    getFileColorClasses(mimeType),
                )}
                style={
                    aspectRatio
                        ? { aspectRatio: String(aspectRatio) }
                        : undefined
                }
            >
                {getFileIcon(mimeType, "size-1/2")}
            </div>
        );
    }

    if (variant === "compact") {
        const content = (
            <div
                className={cn(
                    "flex items-center gap-2 min-w-0",
                    clickable && "cursor-pointer hover:bg-muted/50 rounded-md px-1 -mx-1 py-0.5 -my-0.5 transition-colors",
                    className,
                )}
            >
                <ThumbnailPreview
                    document={doc}
                    size={size}
                    aspectRatio={aspectRatio}
                />
                {showName && (
                    <span className="truncate text-xs font-medium">
                        {originalName}
                    </span>
                )}
            </div>
        );

        if (clickable) {
            return (
                <ThumbnailClickHandler
                    doc={doc}
                    onClick={onPreview}
                    className={cn(
                        "inline-flex items-center gap-2 min-w-0",
                        "cursor-pointer hover:bg-muted/50 rounded-md px-1 -mx-1 py-0.5 -my-0.5 transition-colors",
                    )}
                >
                    <ThumbnailPreview
                        document={doc}
                        size={size}
                        aspectRatio={aspectRatio}
                    />
                    {showName && (
                        <span className="truncate text-xs font-medium">
                            {originalName}
                        </span>
                    )}
                </ThumbnailClickHandler>
            );
        }

        return content;
    }

    // default and detailed variants
    const content = (
        <div
            className={cn(
                "flex items-start gap-3",
                clickable && "cursor-pointer hover:bg-muted/50 rounded-lg p-1 -m-1 transition-colors",
                className,
            )}
        >
            <ThumbnailPreview
                document={doc}
                size={size}
                aspectRatio={aspectRatio}
            />
            <div className="min-w-0 flex-1 min-h-0">
                {showName && (
                    <p className="truncate text-sm font-medium">{originalName}</p>
                )}
                {showCategory && categoryName && (
                    <p className="truncate text-xs text-muted-foreground">
                        {categoryPath && (
                            <span className="opacity-60">
                                {categoryPath} &gt;{" "}
                            </span>
                        )}
                        {categoryName}
                    </p>
                )}
                {showSize && (
                    <p className="text-xs text-muted-foreground">
                        {getDocFileSizeFormatted(doc)}
                    </p>
                )}
                {showUploader && doc.uploader_name && (
                    <p className="text-xs text-muted-foreground">
                        {doc.uploader_name}
                    </p>
                )}
                {showActions && (
                    <div className="flex items-center gap-1 mt-1">
                        {onDownload && (
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon-xs"
                                onClick={(e) => {
                                    e.stopPropagation();
                                    onDownload(doc);
                                }}
                                aria-label={`دانلود ${originalName}`}
                            >
                                <IconDownload className="size-3.5" />
                            </Button>
                        )}
                        {isConfirming && onConfirmDelete && onCancelDelete ? (
                            <div className="flex items-center gap-1">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon-xs"
                                    className="text-destructive hover:text-destructive"
                                    onClick={(e) => {
                                        e.stopPropagation();
                                        onConfirmDelete(doc.id);
                                    }}
                                    disabled={isDeleting}
                                >
                                    {isDeleting ? (
                                        <span className="text-xs font-bold">✓</span>
                                    ) : (
                                        <span className="text-xs font-bold">✓</span>
                                    )}
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon-xs"
                                    onClick={(e) => {
                                        e.stopPropagation();
                                        onCancelDelete();
                                    }}
                                    disabled={isDeleting}
                                >
                                    <span className="text-xs">✕</span>
                                </Button>
                            </div>
                        ) : (
                            onDelete &&
                            onStartDelete && (
                                <ConfirmDeleteButton
                                    iconOnly
                                    isPending={isDeleting}
                                    onConfirm={() => onDelete(doc)}
                                />
                            )
                        )}
                    </div>
                )}
            </div>
        </div>
    );

    if (clickable) {
        return (
            <ThumbnailClickHandler
                doc={doc}
                onClick={onPreview}
                className={cn(
                    "flex items-start gap-3 rounded-lg p-1 -m-1 transition-colors",
                    "cursor-pointer hover:bg-muted/50",
                )}
            >
                <ThumbnailPreview
                    document={doc}
                    size={size}
                    aspectRatio={aspectRatio}
                />
                <div className="min-w-0 flex-1 min-h-0">
                    {showName && (
                        <p className="truncate text-sm font-medium">{originalName}</p>
                    )}
                    {showCategory && categoryName && (
                        <p className="truncate text-xs text-muted-foreground">
                            {categoryPath && (
                                <span className="opacity-60">
                                    {categoryPath} &gt;{" "}
                                </span>
                            )}
                            {categoryName}
                        </p>
                    )}
                    {showSize && (
                        <p className="text-xs text-muted-foreground">
                            {getDocFileSizeFormatted(doc)}
                        </p>
                    )}
                    {showUploader && doc.uploader_name && (
                        <p className="text-xs text-muted-foreground">
                            {doc.uploader_name}
                        </p>
                    )}
                    {showActions && (
                        <div className="flex items-center gap-1 mt-1">
                            {onDownload && (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon-xs"
                                    onClick={(e) => {
                                        e.stopPropagation();
                                        onDownload(doc);
                                    }}
                                    aria-label={`دانلود ${originalName}`}
                                >
                                    <IconDownload className="size-3.5" />
                                </Button>
                            )}
                            {isConfirming && onConfirmDelete && onCancelDelete ? (
                                <div className="flex items-center gap-1">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon-xs"
                                        className="text-destructive hover:text-destructive"
                                        onClick={(e) => {
                                            e.stopPropagation();
                                            onConfirmDelete(doc.id);
                                        }}
                                        disabled={isDeleting}
                                    >
                                        <span className="text-xs font-bold">✓</span>
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon-xs"
                                        onClick={(e) => {
                                            e.stopPropagation();
                                            onCancelDelete();
                                        }}
                                        disabled={isDeleting}
                                    >
                                        <span className="text-xs">✕</span>
                                    </Button>
                                </div>
                            ) : (
                                onDelete &&
                                onStartDelete && (
                                    <ConfirmDeleteButton
                                        iconOnly
                                        isPending={isDeleting}
                                        onConfirm={() => onDelete(doc)}
                                    />
                                )
                            )}
                        </div>
                    )}
                </div>
            </ThumbnailClickHandler>
        );
    }

    return content;
}
