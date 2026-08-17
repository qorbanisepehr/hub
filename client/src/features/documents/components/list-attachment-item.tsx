import {
    IconDownload,
    IconTrash,
} from "@tabler/icons-react";

import {
    Attachment,
    AttachmentAction,
    AttachmentActions,
    AttachmentContent,
    AttachmentMedia,
    AttachmentTitle,
    AttachmentTrigger,
} from "@/components/ui/attachment";
import { DocumentThumbnail } from "@/components/documents";
import { ConfirmDeleteActions } from "./confirm-delete-actions";
import { buildParentPath, getExactCategoryName, getDocOriginalName } from "@/features/documents/types";
import type { Document, DocumentCategory } from "@/features/documents/types";

export function ListAttachmentItem({
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
    return (
        <Attachment size="sm">
            <AttachmentTrigger onClick={() => onPreview(doc)} />
            <AttachmentMedia>
                <DocumentThumbnail
                    document={doc}
                    variant="icon"
                    size="sm"
                    className="size-full rounded-none border-0"
                />
            </AttachmentMedia>
            <AttachmentContent>
                <AttachmentTitle>{getDocOriginalName(doc)}</AttachmentTitle>
                {categories && (
                    <div className="text-xs text-muted-foreground truncate">
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
                    aria-label={`دانلود ${getDocOriginalName(doc)}`}
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
                        aria-label={`حذف ${getDocOriginalName(doc)}`}
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
