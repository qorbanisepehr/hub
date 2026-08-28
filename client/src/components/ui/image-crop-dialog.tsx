"use client";

import * as React from "react";
import {
    Cropper,
    ImageRestriction,
    RectangleStencil,
    type CropperRef,
} from "react-advanced-cropper";
import "react-advanced-cropper/dist/style.css";
import { Dialog as DialogPrimitive } from "@base-ui/react/dialog";
import {
    IconArrowsHorizontal,
    IconArrowsVertical,
    IconCheck,
    IconPencil,
    IconRotate2,
    IconRotateClockwise2,
    IconX,
    IconZoomIn,
    IconZoomOut,
} from "@tabler/icons-react";

import { Button } from "@/components/ui/button";
import { Dialog, DialogOverlay, DialogPortal } from "@/components/ui/dialog";
import {
    canvasToFile,
    resolveOutputType,
    scaleCanvasToFit,
    type NormalizedImageEditorConfig,
} from "@/components/ui/image-editor";
import { cn } from "@/lib/utils";

export type ImageCropDialogProps = {
    open: boolean;
    imageUrl: string;
    fileName: string;
    fileType: string;
    config: NormalizedImageEditorConfig;
    /** True while the caller persists the edited file (blocks closing). */
    isSaving?: boolean;
    /** When set, the title shows a rename control. The callback receives the
     *  new base name only (the file extension/type is preserved separately)
     *  and returns the authoritative full file name to use for the output. */
    onRename?: (newName: string) => Promise<string> | string;
    onOpenChange: (open: boolean) => void;
    onConfirm: (file: File) => void;
};

function loadImageSize(
    src: string,
): Promise<{ width: number; height: number }> {
    return new Promise((resolve, reject) => {
        const image = new window.Image();
        image.addEventListener("load", () =>
            resolve({ width: image.naturalWidth, height: image.naturalHeight }),
        );
        image.addEventListener("error", () =>
            reject(new Error("Failed to load image")),
        );
        image.src = src;
    });
}

function normalizeDegrees(degrees: number): number {
    return ((degrees % 360) + 360) % 360;
}

/** Split a file name into its base name and extension (`.` included, `""` when none). */
function splitNameExtension(fileName: string): { base: string; ext: string } {
    const dotIndex = fileName.lastIndexOf(".");
    return dotIndex > 0
        ? { base: fileName.slice(0, dotIndex), ext: fileName.slice(dotIndex) }
        : { base: fileName, ext: "" };
}

export function ImageCropDialog({
    open,
    imageUrl,
    fileName,
    fileType,
    config,
    isSaving = false,
    onRename,
    onOpenChange,
    onConfirm,
}: ImageCropDialogProps) {
    const cropperRef = React.useRef<CropperRef | null>(null);
    const [aspect, setAspect] = React.useState<number | undefined>(
        config.aspect === "original" ? undefined : config.aspect,
    );
    const [naturalAspect, setNaturalAspect] = React.useState<number>(1);
    const [flip, setFlip] = React.useState({
        horizontal: false,
        vertical: false,
    });
    const [isApplying, setIsApplying] = React.useState(false);
    const [name, setName] = React.useState(fileName);
    const [renaming, setRenaming] = React.useState(false);
    const [renameDraft, setRenameDraft] = React.useState("");
    const [rotation, setRotation] = React.useState(0);

    const rotateOnly = !config.tools.crop;

    // Reset per image when the dialog opens.
    React.useEffect(() => {
        if (!open) return;

        setName(fileName);
        setRenaming(false);
        setRotation(0);

        setAspect(config.aspect === "original" ? undefined : config.aspect);
        setFlip({ horizontal: false, vertical: false });

        loadImageSize(imageUrl)
            .then((size) => setNaturalAspect(size.width / size.height))
            .catch(() => setNaturalAspect(1));
    }, [open, imageUrl, fileName, config.aspect]);

    const stencilAspectRatio = rotateOnly
        ? naturalAspect
        : typeof aspect === "number"
          ? aspect
          : undefined;

    const rotateStep = (degrees: number) => {
        cropperRef.current?.rotateImage(degrees);
        setRotation((r) => normalizeDegrees(r + degrees));
    };

    const handleRotationSlider = (value: number) => {
        const cropper = cropperRef.current;
        if (!cropper) return;
        const current = cropper.getTransforms().rotate;
        cropper.rotateImage(value - current, { transitions: false });
        setRotation(value);
    };

    const zoomBy = (factor: number) => {
        cropperRef.current?.zoomImage(factor);
    };

    const toggleFlip = (axis: "horizontal" | "vertical") => {
        if (axis === "horizontal") {
            setFlip((current) => {
                const next = !current.horizontal;
                cropperRef.current?.flipImage(next);
                return { ...current, horizontal: next };
            });
        } else {
            setFlip((current) => {
                const next = !current.vertical;
                cropperRef.current?.flipImage(false, next);
                return { ...current, vertical: next };
            });
        }
    };

    const startRename = () => {
        setRenameDraft(splitNameExtension(name).base);
        setRenaming(true);
    };

    const commitRename = async () => {
        const trimmed = renameDraft.trim();
        if (!trimmed || !onRename) {
            setRenaming(false);
            return;
        }
        try {
            // Renaming edits only the base name; the file type/extension is
            // preserved by the caller (backend) independently.
            const result = await onRename(trimmed);
            setName(result || trimmed);
        } finally {
            setRenaming(false);
        }
    };

    const handleConfirm = async () => {
        const cropper = cropperRef.current;
        const canvas = cropper?.getCanvas();
        if (!canvas) return;

        setIsApplying(true);
        try {
            const resized = config.tools.resize
                ? scaleCanvasToFit(canvas, config.maxWidth, config.maxHeight)
                : canvas;
            const file = await canvasToFile(
                resized,
                name,
                resolveOutputType(fileType, config.format),
                config.quality,
            );
            onConfirm(file);
        } finally {
            setIsApplying(false);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogPortal>
                <DialogOverlay className="z-50 bg-black/80" />
                <DialogPrimitive.Popup className="fixed inset-0 z-50 flex flex-col bg-black/95 text-white outline-none data-open:animate-in data-open:fade-in-0 data-closed:animate-out data-closed:fade-out-0">
                    <div className="flex items-center justify-between gap-3 px-4 py-3">
                        <div className="flex min-w-0 flex-1 items-center gap-2">
                            {renaming ? (
                                <form
                                    className="flex min-w-0 flex-1 items-center gap-1"
                                    onSubmit={(e) => {
                                        e.preventDefault();
                                        commitRename();
                                    }}
                                >
                                    <input
                                        autoFocus
                                        value={renameDraft}
                                        onChange={(e) =>
                                            setRenameDraft(e.target.value)
                                        }
                                        className="w-full min-w-0 rounded bg-white/10 px-2 py-1 text-sm text-white outline-none focus:bg-white/15"
                                        placeholder="نام جدید فایل"
                                    />
                                    <Button
                                        type="submit"
                                        variant="ghost"
                                        size="icon-sm"
                                        className="shrink-0 text-white/80 hover:bg-white/10 hover:text-white"
                                    >
                                        <IconCheck className="size-4" />
                                    </Button>
                                </form>
                            ) : (
                                <>
                                    <DialogPrimitive.Title className="min-w-0 truncate text-sm font-medium">
                                        {name}
                                    </DialogPrimitive.Title>
                                    {onRename ? (
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon-sm"
                                            title="تغییر نام فایل"
                                            className="shrink-0 text-white/60 hover:bg-white/10 hover:text-white"
                                            onClick={startRename}
                                        >
                                            <IconPencil className="size-4" />
                                        </Button>
                                    ) : null}
                                </>
                            )}
                        </div>
                        <DialogPrimitive.Close
                            render={
                                <Button
                                    variant="ghost"
                                    size="icon-sm"
                                    disabled={isSaving}
                                    className="text-white/80 hover:bg-white/10 hover:text-white"
                                />
                            }
                        >
                            <IconX className="size-4" />
                            <span className="sr-only">بستن</span>
                        </DialogPrimitive.Close>
                    </div>

                    <div className="relative min-h-0 flex-1 overflow-hidden">
                        <Cropper
                            key={imageUrl}
                            ref={cropperRef}
                            src={imageUrl}
                            className="h-full w-full"
                            style={{ height: "100%", width: "100%" }}
                            stencilComponent={RectangleStencil}
                            stencilProps={
                                stencilAspectRatio
                                    ? { aspectRatio: stencilAspectRatio }
                                    : {}
                            }
                            imageRestriction={ImageRestriction.fitArea}
                            defaultSize={({ imageSize }) => ({
                                width: imageSize.width,
                                height: imageSize.height,
                            })}
                        />
                    </div>

                    <div className="flex flex-col items-center gap-2 px-4 pb-1">
                        {config.tools.rotate ? (
                            <>
                                <div className="flex items-center gap-1">
                                    <Button
                                        variant="ghost"
                                        size="icon-sm"
                                        title="چرخش به چپ"
                                        className="text-white/80 hover:bg-white/10 hover:text-white"
                                        onClick={() => rotateStep(-90)}
                                    >
                                        <IconRotate2 className="size-4" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon-sm"
                                        title="چرخش به راست"
                                        className="text-white/80 hover:bg-white/10 hover:text-white"
                                        onClick={() => rotateStep(90)}
                                    >
                                        <IconRotateClockwise2 className="size-4" />
                                    </Button>
                                </div>
                                <label className="flex w-full max-w-md items-center gap-3 text-xs text-white/70">
                                    <IconRotateClockwise2 className="size-4 shrink-0" />
                                    <input
                                        type="range"
                                        min={0}
                                        max={360}
                                        step={1}
                                        value={rotation}
                                        onChange={(e) =>
                                            handleRotationSlider(
                                                Number(e.target.value),
                                            )
                                        }
                                        className="min-w-0 flex-1 accent-white"
                                    />
                                    <span className="w-10 shrink-0 text-end font-mono tabular-nums">
                                        {rotation}°
                                    </span>
                                </label>
                            </>
                        ) : null}

                        {config.tools.zoom ? (
                            <div className="flex items-center gap-1">
                                <Button
                                    variant="ghost"
                                    size="icon-sm"
                                    title="کوچک‌نمایی"
                                    className="text-white/80 hover:bg-white/10 hover:text-white"
                                    onClick={() => zoomBy(0.85)}
                                >
                                    <IconZoomOut className="size-4" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon-sm"
                                    title="بزرگ‌نمایی"
                                    className="text-white/80 hover:bg-white/10 hover:text-white"
                                    onClick={() => zoomBy(1.15)}
                                >
                                    <IconZoomIn className="size-4" />
                                </Button>
                            </div>
                        ) : null}

                        {config.tools.flip ? (
                            <div className="flex items-center gap-1">
                                <Button
                                    variant="ghost"
                                    size="icon-sm"
                                    title="قرینه افقی"
                                    className={cn(
                                        "text-white/80 hover:bg-white/10 hover:text-white",
                                        flip.horizontal && "bg-white/20 text-white",
                                    )}
                                    onClick={() => toggleFlip("horizontal")}
                                >
                                    <IconArrowsHorizontal className="size-4" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon-sm"
                                    title="قرینه عمودی"
                                    className={cn(
                                        "text-white/80 hover:bg-white/10 hover:text-white",
                                        flip.vertical && "bg-white/20 text-white",
                                    )}
                                    onClick={() => toggleFlip("vertical")}
                                >
                                    <IconArrowsVertical className="size-4" />
                                </Button>
                            </div>
                        ) : null}

                        {config.tools.crop && config.aspectPresets.length > 0 ? (
                            <div className="flex items-center gap-1">
                                {config.aspect === "original" ? (
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        className={cn(
                                            "text-white/80 hover:bg-white/10 hover:text-white",
                                            aspect === undefined &&
                                                "bg-white/20 text-white",
                                        )}
                                        onClick={() => setAspect(undefined)}
                                    >
                                        آزاد
                                    </Button>
                                ) : null}
                                {config.aspectPresets.map((preset) => (
                                    <Button
                                        key={preset.value}
                                        variant="ghost"
                                        size="sm"
                                        className={cn(
                                            "text-white/80 hover:bg-white/10 hover:text-white",
                                            aspect === preset.value &&
                                                "bg-white/20 text-white",
                                        )}
                                        onClick={() => setAspect(preset.value)}
                                    >
                                        {preset.label}
                                    </Button>
                                ))}
                            </div>
                        ) : null}
                    </div>

                    <div className="flex items-center justify-between gap-2 border-t border-white/10 px-4 py-3">
                        <Button
                            variant="ghost"
                            className="text-white/80 hover:bg-white/10 hover:text-white"
                            disabled={isApplying || isSaving}
                            onClick={() => onOpenChange(false)}
                        >
                            انصراف
                        </Button>
                        <Button
                            onClick={handleConfirm}
                            disabled={isApplying || isSaving}
                        >
                            <IconCheck className="size-4" />
                            {isSaving
                                ? "در حال ذخیره..."
                                : isApplying
                                  ? "در حال اعمال..."
                                  : "تأیید"}
                        </Button>
                    </div>
                </DialogPrimitive.Popup>
            </DialogPortal>
        </Dialog>
    );
}
