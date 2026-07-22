import * as React from "react";
import {
    IconDownload,
    IconLoader2,
    IconTrash,
} from "@tabler/icons-react";

import { cn } from "@/lib/utils";
import {
    Attachment,
    AttachmentAction,
    AttachmentActions,
    AttachmentContent,
    AttachmentMedia,
    AttachmentTitle,
    AttachmentTrigger,
} from "@/components/ui/attachment";
import { FileThumbnail } from "@/components/ui/file-thumbnail";
import { getFileIcon } from "@/lib/file-icon";
import { getFileColorClasses } from "@/lib/file-colors";
import { renderPdfThumbnailUrl } from "@/lib/pdf-thumbnail-utils";
import { ConfirmDeleteActions } from "./confirm-delete-actions";
import { buildParentPath, getExactCategoryName } from "@/features/documents/types";
import type { Document, DocumentCategory } from "@/features/documents/types";

function PdfAttachmentThumbnail({ fileUrl }: { fileUrl: string }) {
    const [imageUrl, setImageUrl] = React.useState<string | null>(null);
    const [isLoading, setIsLoading] = React.useState(true);

    React.useEffect(() => {
        let isCurrent = true;

        renderPdfThumbnailUrl({
            pageIndex: 0,
            url: fileUrl,
            width: 240,
        }).then((nextImageUrl) => {
            if (isCurrent) {
                setImageUrl(nextImageUrl);
                setIsLoading(false);
            }
        });

        return () => {
            isCurrent = false;
        };
    }, [fileUrl]);

    return imageUrl ? (
        <div className="aspect-square overflow-hidden bg-white">
            <img src={imageUrl} alt="" className="size-full object-cover" />
        </div>
    ) : (
        <div
            className={cn(
                "flex aspect-square items-center justify-center",
                getFileColorClasses("application/pdf"),
            )}
        >
            {getFileIcon("application/pdf", "size-6")}
        </div>
    );
}

export function CardAttachmentItem({
    doc,
    categories,
    isDeleting,
    isConfirming,
    onPreview,
    onDownload,
    onStartDelete,
    onConfirmDelete,
    onCancelDelete,
}: {
    doc: Document;
    categories?: DocumentCategory[];
    isDeleting: boolean;
    isConfirming: boolean;
    onPreview: (doc: Document) => void;
    onDownload: (doc: Document) => void;
    onStartDelete: (id: number) => void;
    onConfirmDelete: (id: number) => void;
    onCancelDelete: () => void;
}) {
    const isImage = doc.mime_type.startsWith("image/");
    const isPdf = doc.mime_type === "application/pdf";

    return (
        <Attachment size="default" orientation="vertical">
            <AttachmentTrigger onClick={() => onPreview(doc)} />
            <AttachmentMedia variant="image" className="p-0">
                {isPdf ? (
                    <PdfAttachmentThumbnail fileUrl={doc.thumbnail_url!} />
                ) : (
                    <FileThumbnail
                        file={{ name: doc.original_name, type: doc.mime_type }}
                        previewImageUrl={isImage ? doc.thumbnail_url : null}
                        previewContent={
                            isImage ? undefined : (
                                <div
                                    className={cn(
                                        "flex size-full items-center justify-center",
                                        getFileColorClasses(doc.mime_type),
                                    )}
                                >
                                    {getFileIcon(doc.mime_type, "size-6")}
                                </div>
                            )
                        }
                        previewAspectRatio={1}
                        className="size-full object-cover rounded-none border-0 bg-transparent"
                        previewClassName="aspect-square object-cover"
                    />
                )}
            </AttachmentMedia>
            <AttachmentContent>
                <AttachmentTitle>{doc.original_name}</AttachmentTitle>
                {categories && (
                    <div className="text-xs text-muted-foreground truncate mt-0.5">
                        <span>{getExactCategoryName(categories, doc.document_category_id)}</span>
                        {buildParentPath(categories, doc.document_category_id) && (
                            <span className="ms-1 opacity-60">
                                ({buildParentPath(categories, doc.document_category_id)})
                            </span>
                        )}
                    </div>
                )}
            </AttachmentContent>
            <AttachmentActions>
                <AttachmentAction
                    onClick={(e) => {
                        e.stopPropagation();
                        onDownload(doc);
                    }}
                    aria-label={`دانلود ${doc.original_name}`}
                >
                    <IconDownload className="size-3.5" />
                </AttachmentAction>
                {isConfirming ? (
                    <ConfirmDeleteActions
                        docId={doc.id}
                        isPending={isDeleting}
                        onConfirm={onConfirmDelete}
                        onCancel={onCancelDelete}
                    />
                ) : (
                    <AttachmentAction
                        aria-label={`حذف ${doc.original_name}`}
                        onClick={(e) => {
                            e.stopPropagation();
                            onStartDelete(doc.id);
                        }}
                        disabled={isDeleting}
                    >
                        <IconTrash className="size-3.5" />
                    </AttachmentAction>
                )}
            </AttachmentActions>
        </Attachment>
    );
}
