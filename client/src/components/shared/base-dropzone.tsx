import { useCallback, useRef, useState } from "react";
import { cn } from "@/lib/utils";

export type BaseDropzoneProps = {
    accept?: string;
    multiple?: boolean;
    onFilesSelected: (files: File[]) => void | Promise<void>;
    children: React.ReactNode;
    className?: string;
    /** Fully disables the dropzone (no pointer events, dimmed) */
    disabled?: boolean;
    /** Blocks opening the file picker / accepting drops without dimming or
     *  disabling pointer events, so hover interactions (e.g. delete overlays) still work */
    disablePick?: boolean;
    style?: React.CSSProperties;
    /** When true, adds a visual drag-feedback ring while files are dragged over */
    showDragFeedback?: boolean;
};

export function BaseDropzone({
    accept,
    multiple = false,
    onFilesSelected,
    children,
    className,
    disabled = false,
    disablePick = false,
    style,
    showDragFeedback = true,
}: BaseDropzoneProps) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [isDragging, setIsDragging] = useState(false);
    const dragDepthRef = useRef(0);
    const interactive = !disabled && !disablePick;

    const handleFiles = useCallback(
        async (fileList: FileList | File[]) => {
            if (disabled || disablePick) return;
            const files = Array.from(fileList);
            if (files.length === 0) return;
            await onFilesSelected(files);
        },
        [disabled, disablePick, onFilesSelected],
    );

    return (
        <div
            className={cn(
                "relative",
                interactive && "cursor-pointer",
                interactive &&
                    showDragFeedback &&
                    isDragging &&
                    "ring-2 ring-primary/50 ring-offset-2",
                disabled && "pointer-events-none opacity-50",
                className,
            )}
            style={style}
            onClick={() => interactive && inputRef.current?.click()}
            onDragEnter={(e) => {
                e.preventDefault();
                e.stopPropagation();
                dragDepthRef.current += 1;
                if (interactive && showDragFeedback) setIsDragging(true);
            }}
            onDragLeave={(e) => {
                e.preventDefault();
                e.stopPropagation();
                dragDepthRef.current = Math.max(0, dragDepthRef.current - 1);
                if (dragDepthRef.current === 0) setIsDragging(false);
            }}
            onDragOver={(e) => {
                e.preventDefault();
                e.stopPropagation();
            }}
            onDrop={async (e) => {
                e.preventDefault();
                e.stopPropagation();
                dragDepthRef.current = 0;
                setIsDragging(false);
                if (interactive && e.dataTransfer.files.length > 0) {
                    await handleFiles(e.dataTransfer.files);
                }
            }}
        >
            <input
                ref={inputRef}
                type="file"
                accept={accept}
                multiple={multiple}
                className="hidden"
                onChange={async (e) => {
                    if (e.target.files && e.target.files.length > 0) {
                        await handleFiles(e.target.files);
                        e.currentTarget.value = "";
                    }
                }}
            />
            {children}
        </div>
    );
}
