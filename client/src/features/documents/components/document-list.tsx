import * as React from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import {
    IconFile,
    IconLayoutGrid,
    IconList,
    IconTable,
    IconColumns,
    IconFolder,
    IconFolderOpen,
} from "@tabler/icons-react";
import { toast } from "sonner";

import { cn } from "@/lib/utils";
import { getApiError } from "@/lib/error-utils";
import { documentKeys } from "@/lib/query-keys";
import { Skeleton } from "@/components/ui/skeleton";
import { Button } from "@/components/ui/button";
import { Tooltip, TooltipContent, TooltipTrigger } from "@/components/ui/tooltip";
import { fetchDocuments, deleteDocument, fetchDocumentCategories } from "@/features/documents/api";
import { getDocServeUrl, getDocDownloadUrl, collectDocs } from "@/features/documents/types";
import type { Document, DocumentCategory } from "@/features/documents/types";
import { DocumentPreviewLightbox } from "./document-preview-lightbox";
import { DocumentTable } from "./document-table";
import { DocumentGroupedTable } from "./document-grouped-table";
import { DocumentTreeView } from "./document-tree-view";
import { ListAttachmentItem } from "./list-attachment-item";
import { CardAttachmentItem } from "./card-attachment-item";

type FilterOption = {
    key: string;
    label: string;
    count: number;
};

function buildFilterOptions(
    categories: DocumentCategory[],
    documents: Document[],
): FilterOption[] {
    const options: FilterOption[] = [];
    for (const topCat of categories) {
        for (const child of topCat.children ?? []) {
            const items = collectDocs(child, documents);
            if (items.length > 0) {
                options.push({
                    key: child.id.toString(),
                    label: `${topCat.name}: ${child.name}`,
                    count: items.length,
                });
            }
        }
    }
    return options;
}

function NestedCategoryCards({
    categories,
    documents,
    viewMode,
    selectedCategory,
    onCategoryChange,
    deletingIds,
    confirmingDeleteId,
    onPreview,
    onDownload,
    onStartDelete,
    onConfirmDelete,
    onCancelDelete,
}: {
    categories: DocumentCategory[];
    documents: Document[];
    viewMode: "card" | "list";
    selectedCategory: string | null;
    onCategoryChange: (id: string | null) => void;
    deletingIds: Set<number>;
    confirmingDeleteId: number | null;
    onPreview: (doc: Document) => void;
    onDownload: (doc: Document) => void;
    onStartDelete: (id: number) => void;
    onConfirmDelete: (id: number) => void;
    onCancelDelete: () => void;
}) {
    const filterOptions = React.useMemo(
        () => buildFilterOptions(categories, documents),
        [categories, documents],
    );

    const totalCount = filterOptions.reduce((sum, opt) => sum + opt.count, 0);

    const filteredCategories = selectedCategory
        ? categories.filter(
              (topCat) =>
                  topCat.children?.some(
                      (child) => child.id.toString() === selectedCategory,
                  ),
          )
        : categories;

    return (
        <div className="space-y-3">
            {filterOptions.length > 1 && (
                <div className="flex gap-1 overflow-x-auto" role="tablist">
                    <button
                        type="button"
                        role="tab"
                        aria-selected={selectedCategory === null}
                        onClick={() => onCategoryChange(null)}
                        className={cn(
                            "shrink-0 rounded-md px-3 py-1.5 text-sm font-medium transition-colors",
                            selectedCategory === null
                                ? "bg-secondary text-secondary-foreground"
                                : "text-muted-foreground hover:text-foreground",
                        )}
                    >
                        همه ({totalCount})
                    </button>
                    {filterOptions.map((opt) => (
                        <button
                            key={opt.key}
                            type="button"
                            role="tab"
                            aria-selected={selectedCategory === opt.key}
                            onClick={() => onCategoryChange(opt.key)}
                            className={cn(
                                "shrink-0 rounded-md px-3 py-1.5 text-sm font-medium transition-colors",
                                selectedCategory === opt.key
                                    ? "bg-secondary text-secondary-foreground"
                                    : "text-muted-foreground hover:text-foreground",
                            )}
                        >
                            {opt.label} ({opt.count})
                        </button>
                    ))}
                </div>
            )}
            {filteredCategories.map((topCat) => {
                const children = selectedCategory
                    ? (topCat.children ?? []).filter(
                          (c) => c.id.toString() === selectedCategory,
                      )
                    : (topCat.children ?? []);

                return children.map((child) => {
                    const items = collectDocs(child, documents);
                    if (items.length === 0) return null;

                    return (
                        <div key={child.id} className="space-y-1.5">
                            <div className="flex items-center gap-1.5">
                                <IconFolder className="size-4 text-blue-500" />
                                <span className="text-sm font-medium text-foreground">
                                    {topCat.name}: {child.name}
                                </span>
                                <span className="text-xs text-muted-foreground">
                                    ({items.length})
                                </span>
                            </div>
                            <div className="space-y-1.5 me-4">
                                {items.map(({ doc }) =>
                                    viewMode === "card" ? (
                                        <CardAttachmentItem
                                            key={doc.id}
                                            doc={doc}
                                            categories={categories}
                                            isDeleting={deletingIds.has(doc.id)}
                                            isConfirming={
                                                confirmingDeleteId === doc.id
                                            }
                                            onPreview={onPreview}
                                            onDownload={onDownload}
                                            onStartDelete={onStartDelete}
                                            onConfirmDelete={onConfirmDelete}
                                            onCancelDelete={onCancelDelete}
                                        />
                                    ) : (
                                        <ListAttachmentItem
                                            key={doc.id}
                                            doc={doc}
                                            categories={categories}
                                            isDeleting={deletingIds.has(doc.id)}
                                            isConfirming={
                                                confirmingDeleteId === doc.id
                                            }
                                            onPreview={onPreview}
                                            onDownload={onDownload}
                                            onStartDelete={onStartDelete}
                                            onConfirmDelete={onConfirmDelete}
                                            onCancelDelete={onCancelDelete}
                                        />
                                    ),
                                )}
                            </div>
                        </div>
                    );
                });
            })}
        </div>
    );
}

type ViewMode = "table" | "grouped" | "tree" | "card" | "list";

type DocumentListProps = {
    documentableType: string;
    documentableId: number;
    selectedIds: number[];
    onSelectionChange: (ids: number[]) => void;
};

function handleDownload(doc: Document) {
    const url = getDocDownloadUrl(doc);
    if (!url) return;

    const a = document.createElement("a");
    a.href = url;
    document.body.appendChild(a);
    a.click();
    a.remove();
}

const VIEW_BUTTONS: { mode: ViewMode; icon: typeof IconTable; label: string }[] = [
    { mode: "table", icon: IconTable, label: "جدول" },
    { mode: "grouped", icon: IconColumns, label: "جدول دسته‌بندی" },
    { mode: "tree", icon: IconFolder, label: "پوشه‌ای" },
    { mode: "card", icon: IconLayoutGrid, label: "کارتی" },
    { mode: "list", icon: IconList, label: "لیستی" },
];

export function DocumentList({
    documentableType,
    documentableId,
    selectedIds,
    onSelectionChange,
}: DocumentListProps) {
    const queryClient = useQueryClient();
    const [viewMode, setViewMode] = React.useState<ViewMode>("grouped");
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
        queryKey: documentKeys.list({ type: documentableType, entity_id: String(documentableId) }),
        queryFn: async () => {
            const { data } = await fetchDocuments(documentableType, String(documentableId));
            return data.data;
        },
    });

    const { data: categories } = useQuery({
        queryKey: documentKeys.categories(documentableType),
        queryFn: async () => {
            const { data } = await fetchDocumentCategories();
            return data.data;
        },
    });

    function removeFromCache(documentId: number) {
        queryClient.setQueryData<Document[]>(
            documentKeys.list({ type: documentableType, entity_id: String(documentableId) }),
            (old) => old?.filter((d) => d.id !== documentId),
        );
    }

    const deleteMutation = useMutation({
        mutationFn: (documentId: number) => deleteDocument(documentId),
        onSuccess: (_, documentId) => {
            removeFromCache(documentId);
            queryClient.invalidateQueries({
                queryKey: documentKeys.trashed(documentableType, String(documentableId)),
            });
            setDeletingIds(new Set());
            setLightboxIndex(null);
            onSelectionChange(selectedIds.filter((id) => id !== documentId));
            toast.success("مدرک حذف شد");
        },
        onError: (err: unknown) => {
            setDeletingIds(new Set());
            toast.error(getApiError(err));
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

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <h3 className="text-sm font-medium">مدارک</h3>
                <div
                    className="flex items-center gap-0.5 rounded-lg border p-0.5"
                    role="group"
                    aria-label="حالت نمایش"
                >
                        {VIEW_BUTTONS.map(({ mode, icon: Icon, label }) => (
                            <Tooltip key={mode}>
                                <TooltipTrigger
                                    render={
                                        <Button
                                            variant={viewMode === mode ? "secondary" : "ghost"}
                                            size="icon-xs"
                                            onClick={() => setViewMode(mode)}
                                            aria-pressed={viewMode === mode}
                                        />
                                    }
                                >
                                    <Icon className="size-4" />
                                </TooltipTrigger>
                                <TooltipContent>{label}</TooltipContent>
                            </Tooltip>
                        ))}
                    </div>
                </div>

            {viewMode === "table" && (
                <DocumentTable
                    documents={documents}
                    categories={categories}
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
            )}

            {viewMode === "grouped" && categories && (
                <DocumentGroupedTable
                    categories={categories}
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
            )}

            {viewMode === "tree" && categories && (
                <DocumentTreeView
                    categories={categories}
                    documents={documents}
                    onPreview={handlePreview}
                    onDownload={handleDownload}
                    confirmingDeleteId={confirmingDeleteId}
                    deletingIds={deletingIds}
                    onStartDelete={handleStartDelete}
                    onConfirmDelete={handleConfirmDelete}
                    onCancelDelete={handleCancelDelete}
                />
            )}

            {(viewMode === "card" || viewMode === "list") && categories && (
                <NestedCategoryCards
                    categories={categories}
                    documents={documents}
                    viewMode={viewMode}
                    selectedCategory={selectedCategory}
                    onCategoryChange={setSelectedCategory}
                    deletingIds={deletingIds}
                    confirmingDeleteId={confirmingDeleteId}
                    onPreview={handlePreview}
                    onDownload={handleDownload}
                    onStartDelete={handleStartDelete}
                    onConfirmDelete={handleConfirmDelete}
                    onCancelDelete={handleCancelDelete}
                />
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
