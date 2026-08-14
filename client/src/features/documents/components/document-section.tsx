import * as React from "react";
import { useQuery } from "@tanstack/react-query";
import { IconFileUpload, IconLibrary, IconTrash } from "@tabler/icons-react";

import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { fetchTrashedDocuments } from "@/features/documents/api";
import { documentKeys } from "@/lib/query-keys";
import { PERMISSIONS } from "@/lib/permissions";
import { usePermission } from "@/features/auth/components/permission-guard";
import { DocumentList } from "./document-list";
import { DocumentUploadModal } from "./document-upload-modal";
import { DocumentTrashModal } from "./document-trash-modal";
import { DocumentLibraryModal } from "./document-library-modal";

type DocumentSectionProps = {
    documentableType: string;
    documentableId: number;
    showActions?: boolean;
    selectedIds?: number[];
    onSelectionChange?: (ids: number[]) => void;
};

export function DocumentSection({
    documentableType,
    documentableId,
    showActions = true,
    selectedIds: externalSelectedIds,
    onSelectionChange: externalOnSelectionChange,
}: DocumentSectionProps) {
    const [uploadOpen, setUploadOpen] = React.useState(false);
    const [trashOpen, setTrashOpen] = React.useState(false);
    const [libraryOpen, setLibraryOpen] = React.useState(false);
    const [internalSelectedIds, setInternalSelectedIds] = React.useState<number[]>([]);

    const canSelectFromLibrary = usePermission(
        PERMISSIONS.EMPLOYEE_DOCUMENTS_LIBRARY_SELECT,
    );

    const selectedIds = externalSelectedIds ?? internalSelectedIds;
    const onSelectionChange = externalOnSelectionChange ?? setInternalSelectedIds;

    const { data: trashedDocuments } = useQuery({
        queryKey: documentKeys.trashed(documentableType, String(documentableId)),
        enabled: showActions,
        queryFn: async () => {
            const { data } = await fetchTrashedDocuments(documentableType, String(documentableId));
            return data.data;
        },
    });

    const trashCount = trashedDocuments?.length ?? 0;

    return (
        <div className="space-y-4">
            {showActions && (
                <div className="flex items-center justify-end gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => setTrashOpen(true)}
                    >
                        <IconTrash className="size-4" />
                        سطل زباله
                        {trashCount > 0 && (
                            <Badge
                                variant="secondary"
                                className="ml-1 px-1.5 py-0 text-xs"
                            >
                                {trashCount}
                            </Badge>
                        )}
                    </Button>
                    {canSelectFromLibrary && (
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setLibraryOpen(true)}
                        >
                            <IconLibrary className="size-4" />
                            کتابخانه
                        </Button>
                    )}
                    <Button size="sm" onClick={() => setUploadOpen(true)}>
                        <IconFileUpload className="size-4" />
                        آپلود مدرک
                    </Button>
                </div>
            )}

            <DocumentList
                documentableType={documentableType}
                documentableId={documentableId}
                selectedIds={selectedIds}
                onSelectionChange={onSelectionChange}
            />

            {showActions && (
                <>
                    <DocumentUploadModal
                        documentableType={documentableType}
                        documentableId={documentableId}
                        open={uploadOpen}
                        onOpenChange={setUploadOpen}
                    />
                    <DocumentTrashModal
                        open={trashOpen}
                        onOpenChange={setTrashOpen}
                        documentableType={documentableType}
                        documentableId={documentableId}
                    />
                    {canSelectFromLibrary && (
                        <DocumentLibraryModal
                            open={libraryOpen}
                            onOpenChange={setLibraryOpen}
                            employeeId={documentableId}
                        />
                    )}
                </>
            )}
        </div>
    );
}
