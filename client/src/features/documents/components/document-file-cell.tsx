import { getFileTypeLabel } from "@/lib/file-type-label";
import { DocumentThumbnail } from "@/components/shared/document-thumbnail";
import { getDocOriginalName, getDocMimeType } from "@/features/documents/types";
import type { Document } from "@/features/documents/types";

export function DocumentFileCell({ doc }: { doc: Document }) {
    const mimeType = getDocMimeType(doc);
    const originalName = getDocOriginalName(doc);

    return (
        <div className="flex items-center gap-2.5">
            <DocumentThumbnail
                document={doc}
                variant="icon"
                size="sm"
            />
            <div className="min-w-0">
                <p className="truncate text-sm font-medium">
                    {originalName}
                </p>
                <p className="text-xs text-muted-foreground">
                    {getFileTypeLabel(mimeType)}
                </p>
            </div>
        </div>
    );
}
