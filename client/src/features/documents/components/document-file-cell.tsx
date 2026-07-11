import { cn } from "@/lib/utils";
import { getFileIcon } from "@/lib/file-icon";
import { getFileColorClasses } from "@/lib/file-colors";
import { getFileTypeLabel } from "@/lib/file-type-label";
import type { Document } from "@/features/documents/types";

export function DocumentFileCell({ doc }: { doc: Document }) {
    return (
        <div className="flex items-center gap-2.5">
            <div
                className={cn(
                    "flex size-8 shrink-0 items-center justify-center rounded-md border",
                    getFileColorClasses(doc.mime_type),
                )}
            >
                {getFileIcon(doc.mime_type, "size-4 stroke-[1.5]")}
            </div>
            <div className="min-w-0">
                <p className="truncate text-sm font-medium">
                    {doc.original_name}
                </p>
                <p className="text-xs text-muted-foreground">
                    {getFileTypeLabel(doc.mime_type)}
                </p>
            </div>
        </div>
    );
}
