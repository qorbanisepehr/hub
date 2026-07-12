import * as React from "react";
import { useQuery } from "@tanstack/react-query";
import { saveAs } from "file-saver";
import { IconDownload, IconFileUpload, IconLoader2, IconTrash } from "@tabler/icons-react";

import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { bulkDownloadDocuments, fetchTrashedDocuments } from "@/features/documents/api";
import { getApiError } from "@/lib/error-utils";
import { DocumentList } from "./document-list";
import { DocumentUploadModal } from "./document-upload-modal";
import { DocumentTrashModal } from "./document-trash-modal";

type DocumentSectionProps = {
    employeeId: number;
    personnelCode?: string;
    showActions?: boolean;
    selectedIds?: number[];
    onSelectionChange?: (ids: number[]) => void;
};

export function DocumentSection({
    employeeId,
    personnelCode,
    showActions = true,
    selectedIds: externalSelectedIds,
    onSelectionChange: externalOnSelectionChange,
}: DocumentSectionProps) {
    const [uploadOpen, setUploadOpen] = React.useState(false);
    const [trashOpen, setTrashOpen] = React.useState(false);
    const [internalSelectedIds, setInternalSelectedIds] = React.useState<number[]>([]);
    const [isDownloading, setIsDownloading] = React.useState(false);
    const [downloadError, setDownloadError] = React.useState<string | null>(null);

    const selectedIds = externalSelectedIds ?? internalSelectedIds;
    const onSelectionChange = externalOnSelectionChange ?? setInternalSelectedIds;

    const { data: trashedDocuments } = useQuery({
        queryKey: ["employee-documents", employeeId, "trash"],
        enabled: showActions,
        queryFn: async () => {
            const { data } = await fetchTrashedDocuments(employeeId);
            return data.data;
        },
    });

    const trashCount = trashedDocuments?.length ?? 0;

    async function handleBulkDownload() {
        setIsDownloading(true);
        setDownloadError(null);
        try {
            const response = await bulkDownloadDocuments(
                employeeId,
                selectedIds.length > 0 ? selectedIds : undefined,
            );
            const blob = new Blob([response.data as BlobPart], { type: "application/zip" });
            saveAs(blob, `${personnelCode ?? `employee-${employeeId}`}.zip`);
        } catch (error) {
            setDownloadError(getApiError(error));
        } finally {
            setIsDownloading(false);
        }
    }

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
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={handleBulkDownload}
                        disabled={isDownloading}
                    >
                        {isDownloading ? (
                            <IconLoader2 className="size-4 animate-spin" />
                        ) : (
                            <IconDownload className="size-4" />
                        )}
                        دانلود
                        {selectedIds.length > 0 && (
                            <Badge
                                variant="secondary"
                                className="ml-1 px-1.5 py-0 text-xs"
                            >
                                {selectedIds.length}
                            </Badge>
                        )}
                    </Button>
                    <Button size="sm" onClick={() => setUploadOpen(true)}>
                        <IconFileUpload className="size-4" />
                        آپلود مدرک
                    </Button>
                </div>
            )}

            {downloadError && (
                <p className="text-sm text-destructive">{downloadError}</p>
            )}

            <DocumentList
                employeeId={employeeId}
                selectedIds={selectedIds}
                onSelectionChange={onSelectionChange}
            />

            {showActions && (
                <>
                    <DocumentUploadModal
                        employeeId={employeeId}
                        open={uploadOpen}
                        onOpenChange={setUploadOpen}
                    />
                    <DocumentTrashModal
                        employeeId={employeeId}
                        open={trashOpen}
                        onOpenChange={setTrashOpen}
                    />
                </>
            )}
        </div>
    );
}
