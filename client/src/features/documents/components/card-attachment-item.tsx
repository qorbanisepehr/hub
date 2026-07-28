import * as React from "react";
import {
    IconDownload,
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
import { DocumentThumbnail } from "@/components/shared/document-thumbnail";
import { ConfirmDeleteActions } from "./confirm-delete-actions";
import { buildParentPath, getExactCategoryName, getDocOriginalName, getDocMimeType, getDocServeUrl } from "@/features/documents/types";
import type { Document, DocumentCategory } from "@/features/documents/types";

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
    const originalName = getDocOriginalName(doc);

    return (
        <Attachment size="default" orientation="vertical">
            <AttachmentTrigger onClick={() => onPreview(doc)} />
            <AttachmentMedia variant="image" className="p-0">
                <DocumentThumbnail
                    document={doc}
                    variant="icon"
                    size="lg"
                    className="size-full rounded-none border-0"
                />
            </AttachmentMedia>
            <AttachmentContent>
                <AttachmentTitle>{originalName}</AttachmentTitle>
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
                    aria-label={`دانلود ${originalName}`}
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
                        aria-label={`حذف ${originalName}`}
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
