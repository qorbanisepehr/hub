import * as React from "react";
import { useQuery } from "@tanstack/react-query";
import { IconFileUpload, IconTrash } from "@tabler/icons-react";

import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { fetchTrashedDocuments } from "@/features/documents/api";
import { DocumentList } from "./document-list";
import { DocumentUploadModal } from "./document-upload-modal";
import { DocumentTrashModal } from "./document-trash-modal";

type DocumentSectionProps = {
    employeeId: number;
    showActions?: boolean;
};

export function DocumentSection({
    employeeId,
    showActions = true,
}: DocumentSectionProps) {
    const [uploadOpen, setUploadOpen] = React.useState(false);
    const [trashOpen, setTrashOpen] = React.useState(false);

    const { data: trashedDocuments } = useQuery({
        queryKey: ["employee-documents", employeeId, "trash"],
        enabled: showActions,
        queryFn: async () => {
            const { data } = await fetchTrashedDocuments(employeeId);
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
                    <Button size="sm" onClick={() => setUploadOpen(true)}>
                        <IconFileUpload className="size-4" />
                        آپلود مدرک
                    </Button>
                </div>
            )}

            <DocumentList employeeId={employeeId} />

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
