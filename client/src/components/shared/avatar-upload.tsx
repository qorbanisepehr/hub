import { useCallback, useEffect, useRef, useState } from "react";
import {
    IconCamera,
    IconCheck,
    IconLoader2,
    IconPhoto,
    IconX,
} from "@tabler/icons-react";
import { Button } from "@/components/ui/button";
import { ConfirmDeleteButton } from "@/components/ui/confirm-delete-button";
import { cn } from "@/lib/utils";

type AvatarUploadProps = {
    avatarUrl: string | null;
    name: string;
    isPending?: boolean;
    onUpload: (file: File) => void;
    onDelete: () => void;
};

const ACCEPTED_TYPES = ["image/jpeg", "image/png", "image/webp", "image/gif"];
const MAX_SIZE = 2 * 1024 * 1024;

export function AvatarUpload({
    avatarUrl,
    name,
    isPending = false,
    onUpload,
    onDelete,
}: AvatarUploadProps) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [isDragging, setIsDragging] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [previewUrl, setPreviewUrl] = useState<string | null>(null);
    const [pendingFile, setPendingFile] = useState<File | null>(null);
    const dragDepthRef = useRef(0);

    const initials = name
        ?.split(" ")
        .map((n: string) => n[0])
        .join("")
        .toUpperCase()
        .slice(0, 2);

    const hasPreview = previewUrl !== null;

    useEffect(() => {
        return () => {
            if (previewUrl) {
                URL.revokeObjectURL(previewUrl);
            }
        };
    }, [previewUrl]);

    const validateAndPreview = useCallback((file: File) => {
        setError(null);

        if (!ACCEPTED_TYPES.includes(file.type)) {
            setError("فقط فایل‌های تصویری (JPG, PNG, WebP, GIF) مجاز هستند.");
            return;
        }

        if (file.size > MAX_SIZE) {
            setError("حجم فایل نباید بیشتر از ۲ مگابایت باشد.");
            return;
        }

        const url = URL.createObjectURL(file);
        setPendingFile(file);
        setPreviewUrl(url);
    }, []);

    const confirmUpload = useCallback(() => {
        if (!pendingFile) return;
        onUpload(pendingFile);
        setPendingFile(null);
        setPreviewUrl(null);
    }, [pendingFile, onUpload]);

    const cancelPreview = useCallback(() => {
        if (previewUrl) {
            URL.revokeObjectURL(previewUrl);
        }
        setPendingFile(null);
        setPreviewUrl(null);
    }, [previewUrl]);

    const handleFileChange = useCallback(
        (e: React.ChangeEvent<HTMLInputElement>) => {
            const file = e.target.files?.[0];
            if (file) {
                validateAndPreview(file);
            }
            e.target.value = "";
        },
        [validateAndPreview],
    );

    const handleDrop = useCallback(
        (e: React.DragEvent) => {
            e.preventDefault();
            dragDepthRef.current = 0;
            setIsDragging(false);
            const file = e.dataTransfer.files[0];
            if (file) {
                validateAndPreview(file);
            }
        },
        [validateAndPreview],
    );

    const handleDragEnter = useCallback((e: React.DragEvent) => {
        e.preventDefault();
        dragDepthRef.current += 1;
        setIsDragging(true);
    }, []);

    const handleDragLeave = useCallback((e: React.DragEvent) => {
        e.preventDefault();
        dragDepthRef.current = Math.max(0, dragDepthRef.current - 1);
        if (dragDepthRef.current === 0) {
            setIsDragging(false);
        }
    }, []);

    const handleDragOver = useCallback((e: React.DragEvent) => {
        e.preventDefault();
    }, []);

    const displayUrl = previewUrl ?? avatarUrl;

    return (
        <div className="flex flex-col items-center gap-4">
            <div
                role="button"
                tabIndex={0}
                onClick={() => {
                    if (isPending || hasPreview) return;
                    inputRef.current?.click();
                }}
                onKeyDown={(e) => {
                    if (
                        (e.key === "Enter" || e.key === " ") &&
                        !isPending &&
                        !hasPreview
                    ) {
                        e.preventDefault();
                        inputRef.current?.click();
                    }
                }}
                onDrop={handleDrop}
                onDragEnter={handleDragEnter}
                onDragLeave={handleDragLeave}
                onDragOver={handleDragOver}
                className={cn(
                    "relative flex size-28 cursor-pointer flex-col items-center justify-center overflow-hidden rounded-full transition-all duration-200",
                    displayUrl
                        ? "ring-2 ring-border hover:ring-primary/50"
                        : "border-2 border-dashed border-border hover:border-primary/50 hover:bg-muted/50",
                    isDragging &&
                        "border-primary bg-primary/5 ring-2 ring-primary/30 scale-105",
                    isPending && "pointer-events-none opacity-60",
                )}
            >
                {displayUrl ? (
                    <>
                        <img
                            src={displayUrl}
                            alt={name}
                            className="size-full rounded-full object-cover"
                        />

                        {isPending ? (
                            <div className="absolute inset-0 flex items-center justify-center rounded-full bg-black/50">
                                <IconLoader2 className="size-6 text-white animate-spin" />
                            </div>
                        ) : hasPreview ? (
                            <div className="absolute inset-0 flex items-center gap-2 justify-center rounded-full bg-black/50">
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
                            <div className="absolute inset-0 flex items-center justify-center rounded-full bg-black/50 opacity-0 transition-opacity hover:opacity-100">
                                <IconCamera className="size-6 text-white" />
                            </div>
                        )}
                    </>
                ) : (
                    <>
                        {isPending ? (
                            <IconLoader2 className="size-7 text-muted-foreground animate-spin" />
                        ) : (
                            <IconPhoto className="size-7 text-muted-foreground" />
                        )}
                        <span className="mt-1 text-[10px] leading-tight text-muted-foreground text-center px-1">
                            {isDragging ? "رها کنید" : "آپلود عکس"}
                        </span>
                    </>
                )}
            </div>

            <input
                ref={inputRef}
                type="file"
                accept="image/*"
                onChange={handleFileChange}
                className="hidden"
            />

            {error && (
                <p className="text-sm text-destructive text-center">{error}</p>
            )}

            {avatarUrl && !isPending && !hasPreview && (
                <ConfirmDeleteButton
                    onConfirm={onDelete}
                    isPending={isPending}
                    label="حذف عکس"
                    confirmLabel="حذف"
                    size="sm"
                />
            )}
        </div>
    );
}
