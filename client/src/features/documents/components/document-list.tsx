import * as React from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import {
    IconFile,
    IconLayoutGrid,
    IconList,
    IconTable,
} from "@tabler/icons-react";
import { toast } from "sonner";

import { cn } from "@/lib/utils";
import { Skeleton } from "@/components/ui/skeleton";
import { AttachmentGroup } from "@/components/ui/attachment";
import { Button } from "@/components/ui/button";
import { fetchDocuments, deleteDocument } from "@/features/documents/api";
import type { Document } from "@/features/documents/types";
import { DocumentPreviewLightbox } from "./document-preview-lightbox";
import { DocumentTable } from "./document-table";
import { ListAttachmentItem } from "./list-attachment-item";
import { CardAttachmentItem } from "./card-attachment-item";

type DocumentListProps = {
    employeeId: number;
    selectedIds: number[];
    onSelectionChange: (ids: number[]) => void;
};

function handleDownload(doc: Document) {
    if (!doc.url) return;

    const a = document.createElement("a");
    a.href = doc.url;
    document.body.appendChild(a);
    a.click();
    a.remove();
}

export function DocumentList({
    employeeId,
    selectedIds,
    onSelectionChange,
}: DocumentListProps) {
    const queryClient = useQueryClient();
    const [viewMode, setViewMode] = React.useState<"card" | "list" | "table">(
        "table",
    );
    const [lightboxIndex, setLightboxIndex] = React.useState<number | null>(
        null,
    );
    const [confirmingDeleteId, setConfirmingDeleteId] = React.useState<
        number | null
    >(null);
    const [deletingIds, setDeletingIds] = React.useState<Set<number>>(
        new Set(),
    );
    const [selectedCategory, setSelectedCategory] = React.useState<
        string | null
    >(null);

    const { data: documents, isLoading, error } = useQuery({
        queryKey: ["employee-documents", employeeId],
        queryFn: async () => {
            const { data } = await fetchDocuments(employeeId);
            return data.data;
        },
    });

    function removeFromCache(documentId: number) {
        queryClient.setQueryData<Document[]>(
            ["employee-documents", employeeId],
            (old) => old?.filter((d) => d.id !== documentId),
        );
    }

    const deleteMutation = useMutation({
        mutationFn: (documentId: number) => deleteDocument(documentId),
        onSuccess: (_, documentId) => {
            removeFromCache(documentId);
            queryClient.invalidateQueries({
                queryKey: ["employee-documents", employeeId, "trash"],
            });
            setDeletingIds(new Set());
            setLightboxIndex(null);
            onSelectionChange(selectedIds.filter((id) => id !== documentId));
            toast.success("مدرک حذف شد");
        },
        onError: () => {
            setDeletingIds(new Set());
            toast.error("خطا در حذف مدرک");
        },
    });

    function handlePreview(doc: Document) {
        if (!documents) return;
        const index = documents.findIndex((d) => d.id === doc.id);
        if (index !== -1) setLightboxIndex(index);
    }

    function handleStartDelete(documentId: number) {
        setConfirmingDeleteId(documentId);
    }

    function handleCancelDelete() {
        setConfirmingDeleteId(null);
    }

    function handleConfirmDelete(documentId: number) {
        setConfirmingDeleteId(null);
        setDeletingIds((prev) => new Set(prev).add(documentId));
        deleteMutation.mutate(documentId);
    }

    if (isLoading) {
        return (
            <div className="space-y-3">
                {Array.from({ length: 3 }).map((_, i) => (
                    <Skeleton key={i} className="h-14 w-full rounded-lg" />
                ))}
            </div>
        );
    }

    if (error) {
        return (
            <div className="flex flex-col items-center justify-center py-8 text-muted-foreground">
                <IconFile className="size-10 mb-3 opacity-30" />
                <p className="text-sm text-destructive">خطا در بارگذاری مدارک</p>
            </div>
        );
    }

    if (!documents?.length) {
        return (
            <div className="flex flex-col items-center justify-center py-8 text-muted-foreground">
                <IconFile className="size-10 mb-3 opacity-30" />
                <p className="text-sm">هیچ مدرکی آپلود نشده است</p>
            </div>
        );
    }

    const grouped = documents.reduce<Record<string, Document[]>>((acc, doc) => {
        const key = doc.category?.name ?? "سایر";
        if (!acc[key]) acc[key] = [];
        acc[key].push(doc);
        return acc;
    }, {});

    const sortedKeys = Object.keys(grouped).sort();
    const categories = sortedKeys;

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <h3 className="text-sm font-medium">مدارک</h3>
                <div
                    className="flex items-center gap-0.5 rounded-lg border p-0.5"
                    role="group"
                    aria-label="حالت نمایش"
                >
                    <Button
                        variant={viewMode === "table" ? "secondary" : "ghost"}
                        size="icon-xs"
                        onClick={() => setViewMode("table")}
                        aria-pressed={viewMode === "table"}
                    >
                        <IconTable className="size-4" />
                    </Button>
                    <Button
                        variant={viewMode === "card" ? "secondary" : "ghost"}
                        size="icon-xs"
                        onClick={() => setViewMode("card")}
                        aria-pressed={viewMode === "card"}
                    >
                        <IconLayoutGrid className="size-4" />
                    </Button>
                    <Button
                        variant={viewMode === "list" ? "secondary" : "ghost"}
                        size="icon-xs"
                        onClick={() => setViewMode("list")}
                        aria-pressed={viewMode === "list"}
                    >
                        <IconList className="size-4" />
                    </Button>
                </div>
            </div>

            {viewMode === "table" ? (
                <DocumentTable
                    documents={documents}
                    onPreview={handlePreview}
                    onDownload={handleDownload}
                    confirmingDeleteId={confirmingDeleteId}
                    deletingIds={deletingIds}
                    onStartDelete={handleStartDelete}
                    onConfirmDelete={handleConfirmDelete}
                    onCancelDelete={handleCancelDelete}
                    selectedIds={selectedIds}
                    onSelectionChange={onSelectionChange}
                />
            ) : (
                <>
                    {categories.length > 1 && (
                        <div
                            className="flex gap-1 overflow-x-auto"
                            role="tablist"
                        >
                            <button
                                type="button"
                                role="tab"
                                aria-selected={selectedCategory === null}
                                onClick={() => setSelectedCategory(null)}
                                className={cn(
                                    "shrink-0 rounded-md px-3 py-1.5 text-sm font-medium transition-colors",
                                    selectedCategory === null
                                        ? "bg-secondary text-secondary-foreground"
                                        : "text-muted-foreground hover:text-foreground",
                                )}
                            >
                                همه ({documents.length})
                            </button>
                            {categories.map((name) => (
                                <button
                                    key={name}
                                    type="button"
                                    role="tab"
                                    aria-selected={selectedCategory === name}
                                    onClick={() => setSelectedCategory(name)}
                                    className={cn(
                                        "shrink-0 rounded-md px-3 py-1.5 text-sm font-medium transition-colors",
                                        selectedCategory === name
                                            ? "bg-secondary text-secondary-foreground"
                                            : "text-muted-foreground hover:text-foreground",
                                    )}
                                >
                                    {name} ({grouped[name].length})
                                </button>
                            ))}
                        </div>
                    )}
                    {(selectedCategory ? [selectedCategory] : categories).map(
                        (categoryName) => (
                            <div key={categoryName} className="space-y-2">
                                {!selectedCategory && (
                                    <h4 className="text-sm font-medium text-muted-foreground">
                                        {categoryName}
                                    </h4>
                                )}
                                {viewMode === "card" ? (
                                    <AttachmentGroup>
                                        {grouped[categoryName].map((doc) => (
                                            <CardAttachmentItem
                                                key={doc.id}
                                                doc={doc}
                                                isDeleting={deletingIds.has(
                                                    doc.id,
                                                )}
                                                isConfirming={
                                                    confirmingDeleteId ===
                                                    doc.id
                                                }
                                                onPreview={handlePreview}
                                                onDownload={handleDownload}
                                                onStartDelete={
                                                    handleStartDelete
                                                }
                                                onConfirmDelete={
                                                    handleConfirmDelete
                                                }
                                                onCancelDelete={
                                                    handleCancelDelete
                                                }
                                            />
                                        ))}
                                    </AttachmentGroup>
                                ) : (
                                    <div className="flex gap-2 flex-wrap">
                                        {grouped[categoryName].map((doc) => (
                                            <ListAttachmentItem
                                                key={doc.id}
                                                doc={doc}
                                                isDeleting={deletingIds.has(
                                                    doc.id,
                                                )}
                                                isConfirming={
                                                    confirmingDeleteId ===
                                                    doc.id
                                                }
                                                onPreview={handlePreview}
                                                onDownload={handleDownload}
                                                onStartDelete={
                                                    handleStartDelete
                                                }
                                                onConfirmDelete={
                                                    handleConfirmDelete
                                                }
                                                onCancelDelete={
                                                    handleCancelDelete
                                                }
                                            />
                                        ))}
                                    </div>
                                )}
                            </div>
                        ),
                    )}
                </>
            )}
            <DocumentPreviewLightbox
                documents={documents}
                currentIndex={lightboxIndex ?? 0}
                open={lightboxIndex !== null}
                onClose={() => setLightboxIndex(null)}
                onNavigate={setLightboxIndex}
            />
        </div>
    );
}
