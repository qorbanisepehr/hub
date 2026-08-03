"use client";

import { IconFile } from "@tabler/icons-react";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";

import { cn } from "@/lib/utils";
import { publicApi } from "@/lib/public-api";
import { ConfirmDeleteButton } from "@/components/ui/confirm-delete-button";
import { FileThumbnail } from "@/components/ui/file-thumbnail";
import { getFileIcon } from "@/lib/file-icon";
import { getFileColorClasses } from "@/lib/file-colors";
import type { QuestionnaireDocument } from "@/features/recruitment/hooks/use-questionnaire-documents";

type DocumentFileItemProps = {
    uuid: string;
    doc: QuestionnaireDocument;
    /** "row": name + subtitle next to thumbnail (FileUploadField); "compact": thumbnail with a label row below (orphan entries) */
    layout?: "row" | "compact";
    /** Row layout: secondary text under the file name */
    subtitle?: string | null;
    /** Compact layout: text shown under the thumbnail */
    label?: string | null;
    thumbnailSize?: string;
    /** Whether to show the delete action button. Defaults to true. */
    actionsEnabled?: boolean;
    className?: string;
};

export function DocumentFileItem({
    uuid,
    doc,
    layout = "row",
    subtitle,
    label,
    thumbnailSize = "size-10",
    actionsEnabled = true,
    className,
}: DocumentFileItemProps) {
    const queryClient = useQueryClient();

    const deleteMutation = useMutation({
        mutationFn: (usageId: number) =>
            publicApi.delete(`/questionnaire/${uuid}/documents/${usageId}`, {
                grant: { entity: "questionnaire", uuid, purpose: "edit" },
            }),
        onSuccess: () => {
            queryClient.invalidateQueries({
                queryKey: ["questionnaire-documents", uuid],
            });
            toast.success("مدرک حذف شد.");
        },
        onError: () => toast.error("خطا در حذف مدرک."),
    });

    const isImage = doc.mime_type.startsWith("image/");

    const thumbnail = isImage ? (
        <FileThumbnail
            file={{ name: doc.original_name, type: doc.mime_type }}
            previewImageUrl={doc.url}
            className={cn(thumbnailSize, "shrink-0 rounded-none border-0")}
            previewClassName="aspect-square"
        />
    ) : (
        <div
            className={cn(
                "flex shrink-0 items-center justify-center rounded",
                getFileColorClasses(doc.mime_type),
                thumbnailSize,
            )}
        >
            {getFileIcon(doc.mime_type, thumbnailSize === "size-10" ? "size-5" : "size-6")}
        </div>
    );

    if (layout === "compact") {
        return (
            <div className={cn("flex flex-col items-start gap-0.5", className)}>
                {thumbnail}
                <div className="flex items-center gap-1 px-1">
                    {label && (
                        <span className="text-[10px] text-muted-foreground">
                            {label}
                        </span>
                    )}
                    {actionsEnabled && (
                        <ConfirmDeleteButton
                            iconOnly
                            isPending={deleteMutation.isPending}
                            onConfirm={() => deleteMutation.mutate(doc.usage_id)}
                        />
                    )}
                </div>
            </div>
        );
    }

    return (
        <div className={cn("flex items-center gap-3", className)}>
            {thumbnail}
            <div className="min-w-0 flex-1">
                <p className="truncate text-sm">{doc.original_name}</p>
                {subtitle && (
                    <p className="text-xs text-muted-foreground">{subtitle}</p>
                )}
            </div>
            {actionsEnabled && (
                <ConfirmDeleteButton
                    iconOnly
                    isPending={deleteMutation.isPending}
                    onConfirm={() => deleteMutation.mutate(doc.usage_id)}
                />
            )}
        </div>
    );
}
