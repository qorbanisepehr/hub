"use client";

import { IconFile } from "@tabler/icons-react";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";

import { cn } from "@/lib/utils";
import { api } from "@/lib/api";
import { publicApi } from "@/lib/public-api";
import { ConfirmDeleteButton } from "@/components/ui/confirm-delete-button";
import { FileThumbnail } from "@/components/ui/file-thumbnail";
import { getFileIcon } from "@/lib/file-icon";
import { getFileColorClasses } from "@/lib/file-colors";
import { isAuthedDocumentEntity } from "@/hooks/use-entity-documents";
import type { EntityDocument } from "@/hooks/use-entity-documents";

type DocumentFileItemProps = {
    uuid: string;
    doc: EntityDocument;
    /** Grant entity the delete targets. Defaults to "questionnaire". */
    entity?: string;
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
    entity = "questionnaire",
    layout = "row",
    subtitle,
    label,
    thumbnailSize = "size-10",
    actionsEnabled = true,
    className,
}: DocumentFileItemProps) {
    const queryClient = useQueryClient();
    const authed = isAuthedDocumentEntity(entity);
    const docClient = authed ? api : publicApi;

    const deleteMutation = useMutation({
        mutationFn: (usageId: number) =>
            docClient.delete(`/${entity}/${uuid}/documents/${usageId}`, {
                ...(authed ? {} : { grant: { entity, uuid, purpose: "edit" } }),
            }),
        onSuccess: () => {
            queryClient.invalidateQueries({
                queryKey: [`${entity}-documents`, uuid],
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
