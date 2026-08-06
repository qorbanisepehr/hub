import { useCallback, useEffect, useRef, useState } from "react";
import { IconCheck, IconLoader2, IconPhoto, IconX } from "@tabler/icons-react";

import { Button } from "@/components/ui/button";
import { ConfirmDeleteButton } from "@/components/ui/confirm-delete-button";
import { cn } from "@/lib/utils";
import { FILE_UPLOAD } from "@/lib/constants";

export type ImageUploadProps = {
    /** Current image URL (server value), or null when no image is uploaded. */
    url: string | null;
    alt: string;
    /** Rendered inside the surface while no image is set. */
    fallback: React.ReactNode;
    /** Called when the user confirms a pending file. */
    onUpload: (file: File) => void;
    /** Called when the user confirms deletion of the current image. */
    onDelete: () => void;
    isPending?: boolean;
    /** Shape + size of the interactive upload surface. */
    containerClassName?: string;
    /** Object-fit applied to the displayed image. */
    fit?: "cover" | "contain";
    /** File input accept attribute. */
    accept?: string;
    /** Allowed MIME types checked before previewing. */
    acceptedTypes?: string[];
    maxSize?: number;
    typeErrorMessage?: string;
    sizeErrorMessage?: string;
    /** Empty-state label under the fallback. */
    emptyLabel?: string;
    deleteLabel?: string;
    deleteConfirmLabel?: string;
    /** Shown on hover while an image is displayed (no pending file). */
    hoverOverlay?: React.ReactNode;
};

const DEFAULT_ACCEPTED_TYPES = ["image/jpeg", "image/png", "image/webp"];

export function ImageUpload({
    url,
    alt,
    fallback,
    onUpload,
    onDelete,
    isPending = false,
    containerClassName,
    fit = "cover",
    accept,
    acceptedTypes = DEFAULT_ACCEPTED_TYPES,
    maxSize = FILE_UPLOAD.MAX_SIZE_AVATAR,
    typeErrorMessage = "فرمت فایل انتخاب‌شده مجاز نیست.",
    sizeErrorMessage = "حجم فایل بیشتر از حد مجاز است.",
    emptyLabel = "آپلود تصویر",
    deleteLabel = "حذف",
    deleteConfirmLabel = "تأیید حذف",
    hoverOverlay,
}: ImageUploadProps) {
    const inputRef = useRef<HTMLInputElement>(null);
    const dragDepthRef = useRef(0);
    const [isDragging, setIsDragging] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [previewUrl, setPreviewUrl] = useState<string | null>(null);
    const [pendingFile, setPendingFile] = useState<File | null>(null);

    const hasPending = previewUrl !== null;
    const displayUrl = previewUrl ?? url;

    useEffect(() => {
        return () => {
            if (previewUrl) URL.revokeObjectURL(previewUrl);
        };
    }, [previewUrl]);

    const validateAndPreview = useCallback(
        (file: File) => {
            setError(null);

            if (!acceptedTypes.includes(file.type)) {
                setError(typeErrorMessage);
                return;
            }

            if (file.size > maxSize) {
                setError(sizeErrorMessage);
                return;
            }

            setPendingFile(file);
            setPreviewUrl(URL.createObjectURL(file));
        },
        [acceptedTypes, maxSize, sizeErrorMessage, typeErrorMessage],
    );

    const confirmUpload = useCallback(() => {
        if (!pendingFile) return;

        onUpload(pendingFile);
        setPendingFile(null);
        setPreviewUrl(null);
    }, [onUpload, pendingFile]);

    const cancelPreview = useCallback(() => {
        if (previewUrl) URL.revokeObjectURL(previewUrl);
        setPendingFile(null);
        setPreviewUrl(null);
    }, [previewUrl]);

    const openPicker = () => {
        if (!isPending && !hasPending) inputRef.current?.click();
    };

    return (
        <div className="flex flex-col items-center gap-3">
            <div
                role="button"
                tabIndex={0}
                onClick={openPicker}
                onKeyDown={(e) => {
                    if (
                        (e.key === "Enter" || e.key === " ") &&
                        !isPending &&
                        !hasPending
                    ) {
                        e.preventDefault();
                        openPicker();
                    }
                }}
                onDrop={(e) => {
                    e.preventDefault();
                    dragDepthRef.current = 0;
                    setIsDragging(false);
                    const file = e.dataTransfer.files[0];
                    if (file) validateAndPreview(file);
                }}
                onDragEnter={(e) => {
                    e.preventDefault();
                    dragDepthRef.current += 1;
                    setIsDragging(true);
                }}
                onDragLeave={(e) => {
                    e.preventDefault();
                    dragDepthRef.current = Math.max(0, dragDepthRef.current - 1);
                    if (dragDepthRef.current === 0) setIsDragging(false);
                }}
                onDragOver={(e) => e.preventDefault()}
                className={cn(
                    "relative flex cursor-pointer flex-col items-center justify-center overflow-hidden transition-all duration-200",
                    containerClassName,
                    displayUrl
                        ? "border bg-background hover:ring-2 hover:ring-primary/50"
                        : "border-2 border-dashed border-border hover:border-primary/50 hover:bg-muted/50",
                    isDragging &&
                        "border-primary bg-primary/5 ring-2 ring-primary/30 scale-105",
                    isPending && "pointer-events-none opacity-60",
                )}
            >
                <input
                    ref={inputRef}
                    type="file"
                    accept={accept}
                    onChange={(e) => {
                        const file = e.target.files?.[0];
                        if (file) validateAndPreview(file);
                        e.target.value = "";
                    }}
                    className="hidden"
                />

                {displayUrl ? (
                    <img
                        src={displayUrl}
                        alt={alt}
                        className={cn(
                            "size-full",
                            fit === "contain" ? "object-contain p-2" : "object-cover",
                        )}
                    />
                ) : (
                    <div className="flex flex-col items-center justify-center gap-2 p-4 text-muted-foreground">
                        {fallback}
                        {emptyLabel && <span className="text-xs">{emptyLabel}</span>}
                    </div>
                )}

                {isPending && (
                    <div className="absolute inset-0 flex items-center justify-center bg-background/60">
                        <IconLoader2 className="size-6 animate-spin text-primary" />
                    </div>
                )}

                {!isPending && displayUrl && (
                    <>
                        {hasPending ? (
                            <div className="absolute inset-0 flex items-center justify-center gap-2 bg-black/50">
                                <Button
                                    size="icon-sm"
                                    variant="secondary"
                                    onClick={(e) => {
                                        e.stopPropagation();
                                        confirmUpload();
                                    }}
                                >
                                    <IconCheck />
                                </Button>
                                <Button
                                    size="icon-sm"
                                    variant="destructive"
                                    onClick={(e) => {
                                        e.stopPropagation();
                                        cancelPreview();
                                    }}
                                >
                                    <IconX />
                                </Button>
                            </div>
                        ) : (
                            <div className="absolute inset-0 flex items-center justify-center gap-2 bg-black/50 opacity-0 transition-opacity hover:opacity-100">
                                {hoverOverlay ?? (
                                    <>
                                        <IconPhoto className="size-5 text-white" />
                                        <IconX className="size-4 text-white" />
                                    </>
                                )}
                            </div>
                        )}
                    </>
                )}
            </div>

            {error && (
                <p className="text-center text-sm text-destructive">{error}</p>
            )}

            {url && !hasPending && !isPending && (
                <ConfirmDeleteButton
                    onConfirm={onDelete}
                    isPending={isPending}
                    label={deleteLabel}
                    confirmLabel={deleteConfirmLabel}
                    size="sm"
                />
            )}
        </div>
    );
}
