"use client";

import * as React from "react";
import {
    IconFileSpreadsheet,
    IconPhoto,
    IconUpload,
    IconX,
} from "@tabler/icons-react";

import { cn } from "@/lib/utils";
import { getFileIcon } from "@/lib/file-icon";
import { getFileColorClasses } from "@/lib/file-colors";
import { Card } from "@/components/ui/card";
import { FileThumbnail } from "@/components/ui/file-thumbnail";

type FileUploadItem = {
    id: string;
    name: string;
    type: string;
    size: number;
    url: string;
};

type AcceptedFileType = {
    label: string;
    icon: React.ComponentType<{ className?: string }>;
};

type FileUploadProps = {
    accept?: string;
    acceptedFileTypes?: AcceptedFileType[];
    browseLabel?: string;
    className?: string;
    description?: string;
    draggingLabel?: string;
    multiple?: boolean;
    showFileList?: boolean;
    title?: string;
    onFilesAccepted?: (files: File[]) => void;
    onFilesChange?: (files: FileUploadItem[]) => void;
};

const ACCEPTED_FILE_TYPES: AcceptedFileType[] = [
    { label: "Image", icon: IconPhoto },
    { label: "PDF", icon: IconUpload },
    { label: "Sheet", icon: IconFileSpreadsheet },
];
const DEFAULT_ACCEPT = [
    ".pdf",
    ".doc",
    ".docx",
    ".xlsx",
    ".csv",
    ".png",
    ".jpg",
    ".jpeg",
    "application/pdf",
    "application/msword",
    "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
    "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
    "text/csv",
    "image/png",
    "image/jpeg",
].join(",");
const ICON_TRANSFORMS = [
    {
        idle: "translate(-78%, -50%) rotate(-8deg)",
        active: "translate(-114%, -50%) rotate(-12deg) scale(1.08)",
    },
    {
        idle: "translate(-50%, -50%) rotate(0deg)",
        active: "translate(-50%, -50%) rotate(0deg) scale(1.18)",
    },
    {
        idle: "translate(-22%, -50%) rotate(8deg)",
        active: "translate(14%, -50%) rotate(12deg) scale(1.08)",
    },
];

function formatBytes(bytes: number) {
    if (bytes === 0) return "0 B";

    const units = ["B", "KB", "MB", "GB"];
    const index = Math.min(
        Math.floor(Math.log(bytes) / Math.log(1024)),
        units.length - 1,
    );

    return `${(bytes / 1024 ** index).toFixed(index === 0 ? 0 : 1)} ${
        units[index]
    }`;
}

function matchesAccept(file: File, accept?: string) {
    if (!accept) return true;

    return accept.split(",").some((rawToken) => {
        const token = rawToken.trim().toLowerCase();

        if (!token) return false;
        if (token.startsWith("."))
            return file.name.toLowerCase().endsWith(token);
        if (token.endsWith("/*")) {
            return file.type.toLowerCase().startsWith(token.slice(0, -1));
        }

        return file.type.toLowerCase() === token;
    });
}

function toUploadItems(files: FileList | File[]): FileUploadItem[] {
    return Array.from(files).map((file) => ({
        id: `${file.name}-${file.size}-${file.lastModified}`,
        name: file.name,
        type: file.type || "Unknown type",
        size: file.size,
        url: URL.createObjectURL(file),
    }));
}

function UploadIconCluster({
    acceptedFileTypes,
    isDragging,
}: {
    acceptedFileTypes: AcceptedFileType[];
    isDragging: boolean;
}) {
    const singleIcon = acceptedFileTypes.length === 1;

    return (
        <div className="relative h-14 w-36">
            {acceptedFileTypes.map((item, index) => (
                <Card
                    key={item.label}
                    className={cn(
                        "absolute top-1/2 inset-s-6 grid size-12 place-items-center rounded-xl bg-background text-muted-foreground transition-[transform,color,background-color] duration-500 ease-[cubic-bezier(0.22,1,0.36,1)] before:rounded-[calc(var(--radius-xl)-1px)]",
                        "motion-reduce:transition-none p-0",
                        index === 1 && "z-10",
                        isDragging &&
                            "bg-popover text-foreground shadow-md shadow-black/10 not-dark:bg-clip-border dark:shadow-black/25",
                    )}
                    style={{
                        transform: singleIcon
                            ? `translate(-50%, -50%) scale(${isDragging ? 1.14 : 1})`
                            : isDragging
                              ? ICON_TRANSFORMS[index]?.active
                              : ICON_TRANSFORMS[index]?.idle,
                    }}
                >
                    <item.icon className="size-5" />
                </Card>
            ))}
        </div>
    );
}

export function FileUpload({
    accept = DEFAULT_ACCEPT,
    acceptedFileTypes = ACCEPTED_FILE_TYPES,
    browseLabel = "Browse files",
    className,
    description = "PDF, DOC/DOCX, XLSX, CSV, PNG, or JPG",
    draggingLabel = "Drop to add",
    multiple = true,
    showFileList = true,
    title = "Click to upload or drop files",
    onFilesAccepted,
    onFilesChange,
}: FileUploadProps) {
    const dragDepthRef = React.useRef(0);
    const inputRef = React.useRef<HTMLInputElement>(null);
    const [isDragging, setIsDragging] = React.useState(false);
    const [files, setFiles] = React.useState<FileUploadItem[]>([]);
    const [rejectionMessage, setRejectionMessage] = React.useState<
        string | null
    >(null);

    const commitFiles = React.useCallback(
        (nextFiles: FileList | File[]) => {
            const acceptedFiles = Array.from(nextFiles)
                .filter((file) => matchesAccept(file, accept))
                .slice(0, multiple ? undefined : 1);

            if (acceptedFiles.length === 0) {
                setRejectionMessage("This file type is not supported here.");
                return;
            }

            setRejectionMessage(null);
            onFilesAccepted?.(acceptedFiles);

            const items = toUploadItems(acceptedFiles);
            setFiles((previousFiles) => {
                previousFiles.forEach((file) => URL.revokeObjectURL(file.url));
                return items;
            });
            onFilesChange?.(items);
        },
        [accept, multiple, onFilesAccepted, onFilesChange],
    );

    React.useEffect(() => {
        return () => {
            files.forEach((file) => URL.revokeObjectURL(file.url));
        };
    }, [files]);

    const openFileDialog = React.useCallback(() => {
        inputRef.current?.click();
    }, []);

    const removeFile = React.useCallback(() => {
        setFiles((previousFiles) => {
            previousFiles.forEach((file) => URL.revokeObjectURL(file.url));
            return [];
        });
        onFilesChange?.([]);
    }, [onFilesChange]);

    const dropzone = (
        <div
            role="button"
            tabIndex={0}
            className={cn(
                "relative flex min-h-64 cursor-pointer flex-col items-center justify-center gap-5 overflow-hidden rounded border border-dashed bg-background px-6 py-10 text-center transition-[border-color,background-color] duration-200 ease-out",
                "motion-reduce:transition-none",
                isDragging
                    ? "border-foreground/40 bg-accent/35"
                    : "border-foreground/20 hover:border-foreground/35 hover:bg-muted/35 dark:border-foreground/25 dark:hover:border-foreground/40",
            )}
            onClick={openFileDialog}
            onDragEnter={(event) => {
                event.preventDefault();
                dragDepthRef.current += 1;
                setIsDragging(true);
            }}
            onDragLeave={(event) => {
                event.preventDefault();
                dragDepthRef.current = Math.max(0, dragDepthRef.current - 1);
                if (dragDepthRef.current === 0) setIsDragging(false);
            }}
            onDragOver={(event) => event.preventDefault()}
            onDrop={(event) => {
                event.preventDefault();
                dragDepthRef.current = 0;
                setIsDragging(false);
                if (event.dataTransfer.files.length > 0) {
                    commitFiles(event.dataTransfer.files);
                }
            }}
            onKeyDown={(event) => {
                if (event.key === "Enter" || event.key === " ") {
                    event.preventDefault();
                    openFileDialog();
                }
            }}
        >
            <UploadIconCluster
                acceptedFileTypes={acceptedFileTypes}
                isDragging={isDragging}
            />
            <div className="space-y-1">
                <div className="text-sm font-medium">{title}</div>
                <div className="text-xs text-muted-foreground">
                    {description}
                </div>
                {rejectionMessage ? (
                    <div className="text-xs text-destructive">
                        {rejectionMessage}
                    </div>
                ) : null}
            </div>
            <div className="inline-flex items-center gap-2 rounded-full border bg-background px-3 py-1 text-xs text-muted-foreground">
                <IconUpload className="size-3.5" />
                <span>{isDragging ? draggingLabel : browseLabel}</span>
            </div>
            <input
                ref={inputRef}
                type="file"
                accept={accept}
                multiple={multiple}
                className="hidden"
                onChange={(event) => {
                    if (event.target.files) {
                        commitFiles(event.target.files);
                        event.currentTarget.value = "";
                    }
                }}
            />
        </div>
    );

    return (
        <div className={cn("space-y-3", className)}>
            {dropzone}
            {showFileList && files.length > 0 ? (
                <div className="rounded-xl border bg-background">
                    {files.map((file) => (
                        <div
                            key={file.id}
                            className="flex items-center gap-3 border-b px-3 py-2.5 last:border-b-0"
                        >
                            {file.type.startsWith("image/") ? (
                                <FileThumbnail
                                    file={{
                                        name: file.name,
                                        type: file.type,
                                    }}
                                    previewImageUrl={file.url}
                                    className="size-10 shrink-0 rounded-lg"
                                />
                            ) : (
                                <div
                                    className={cn(
                                        "flex size-10 shrink-0 items-center justify-center rounded-lg border",
                                        getFileColorClasses(file.type),
                                    )}
                                >
                                    {getFileIcon(
                                        file.type,
                                        "size-5 stroke-[1.5]",
                                    )}
                                </div>
                            )}
                            <div className="min-w-0 flex-1">
                                <div className="truncate text-sm font-medium">
                                    {file.name}
                                </div>
                                <div className="truncate text-xs text-muted-foreground">
                                    {formatBytes(file.size)}
                                </div>
                            </div>
                            <button
                                type="button"
                                onClick={(event) => {
                                    event.stopPropagation();
                                    removeFile();
                                }}
                                className="rounded-full p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                                aria-label="Remove file"
                            >
                                <IconX className="size-4" />
                            </button>
                        </div>
                    ))}
                </div>
            ) : null}
        </div>
    );
}
