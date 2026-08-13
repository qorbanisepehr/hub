"use client";

import { IconPaperclip } from "@tabler/icons-react";

import { cn } from "@/lib/utils";
import { FileThumbnail } from "@/components/ui/file-thumbnail";
import { getFileIcon } from "@/lib/file-icon";
import { getFileColorClasses } from "@/lib/file-colors";
import type { QuestionnaireDocument } from "@/features/questionnaire/hooks/use-questionnaire-documents";

type RepeaterAttachmentCellProps = {
    docs: QuestionnaireDocument[];
    className?: string;
};

function AttachmentThumbnail({ doc }: { doc: QuestionnaireDocument }) {
    if (doc.mime_type.startsWith("image/")) {
        return (
            <FileThumbnail
                file={{ name: doc.structure_name, type: doc.mime_type }}
                previewImageUrl={doc.url}
                className="size-8 shrink-0 rounded border-0"
                previewClassName="aspect-square"
            />
        );
    }

    return (
        <div
            className={cn(
                "flex size-8 shrink-0 items-center justify-center rounded",
                getFileColorClasses(doc.mime_type),
            )}
        >
            {getFileIcon(doc.mime_type, "size-4")}
        </div>
    );
}

/**
 * Reusable "attachment" table cell for repeaters.
 * Shows a thumbnail / file-icon for each uploaded document
 * or a muted placeholder when nothing is uploaded.
 */
export function RepeaterAttachmentCell({ docs, className }: RepeaterAttachmentCellProps) {
    if (docs.length === 0) {
        return (
            <span className={cn("inline-flex items-center text-muted-foreground/40", className)}>
                <IconPaperclip className="size-3.5" />
            </span>
        );
    }

    return (
        <div className={cn("flex flex-wrap items-center gap-1.5", className)}>
            {docs.map((doc) => (
                <AttachmentThumbnail key={doc.usage_id} doc={doc} />
            ))}
        </div>
    );
}

type RepeaterAttachmentColumnOptions = {
    categorySlug: string;
    /** field-key prefix, e.g. "edu-", "lang-", "train-" */
    fieldKeyPrefix: string;
    getDocumentsBySlug: (slug: string, fieldKey?: string) => QuestionnaireDocument[];
};

/**
 * Builds the standard `_attachment` repeater column for the given
 * document category + field-key prefix, wiring each row to its docs.
 */
export function repeaterAttachmentColumn({
    categorySlug,
    fieldKeyPrefix,
    getDocumentsBySlug,
}: RepeaterAttachmentColumnOptions) {
    return {
        key: "_attachment",
        label: "پیوست",
        render: (_value: unknown, _item: unknown, index: number) => (
            <RepeaterAttachmentCell
                docs={getDocumentsBySlug(categorySlug, `${fieldKeyPrefix}${index}`)}
            />
        ),
    };
}
