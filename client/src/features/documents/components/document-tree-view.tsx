import * as React from "react";
import {
    IconChevronRight,
    IconDownload,
    IconFolder,
    IconFolderOpen,
    IconLoader2,
    IconTrash,
    IconChevronsUpRight,
    IconChevronsDownRight,
} from "@tabler/icons-react";

import { cn } from "@/lib/utils";
import { getFileIcon } from "@/lib/file-utils";
import { getFileColorClasses } from "@/lib/file-utils";
import { getFileTypeLabel } from "@/lib/file-utils";
import { toPersianDate } from "@/lib/date-format";
import { DocumentThumbnail } from "@/components/documents";
import { Button } from "@/components/ui/button";
import { ConfirmDeleteActions } from "./confirm-delete-actions";
import type { Document, DocumentCategory } from "@/features/documents/types";
import { getDocOriginalName, getDocMimeType, getDocFileSizeFormatted, collectDocs } from "@/features/documents/types";

function TopLevelFolder({
    category,
    documents,
    expandAll,
    onPreview,
    onDownload,
    confirmingDeleteId,
    deletingIds,
    onStartDelete,
    onConfirmDelete,
    onCancelDelete,
}: {
    category: DocumentCategory;
    documents: Document[];
    expandAll: boolean | null;
    onPreview: (doc: Document) => void;
    onDownload: (doc: Document) => void;
    confirmingDeleteId: number | null;
    deletingIds: Set<number>;
    onStartDelete: (id: number) => void;
    onConfirmDelete: (id: number) => void;
    onCancelDelete: () => void;
}) {
    const [expanded, setExpanded] = React.useState(true);

    React.useEffect(() => {
        if (expandAll !== null) {
            setExpanded(expandAll);
        }
    }, [expandAll]);

    const children = category.children ?? [];
    const totalCount = children.reduce(
        (sum, child) => sum + collectDocs(child, documents).length,
        0,
    );

    if (totalCount === 0) return null;

    return (
        <div>
            <div
                className={cn(
                    "flex items-center gap-1.5 rounded-md px-2 py-1.5 text-sm",
                    "hover:bg-muted/50 cursor-pointer select-none",
                )}
                onClick={() => setExpanded(!expanded)}
            >
                <IconChevronRight
                    className={cn(
                        "size-3.5 shrink-0 text-muted-foreground transition-transform",
                        expanded && "rotate-90",
                    )}
                />
                {expanded ? (
                    <IconFolderOpen className="size-4 shrink-0 text-blue-500" />
                ) : (
                    <IconFolder className="size-4 shrink-0 text-blue-500" />
                )}
                <span className="truncate font-semibold">{category.name}</span>
                <span className="text-xs text-muted-foreground">
                    ({totalCount})
                </span>
            </div>

            {expanded &&
                children.map((child) => (
                    <FirstLevelFolder
                        key={child.id}
                        category={child}
                        documents={documents}
                        expandAll={expandAll}
                        onPreview={onPreview}
                        onDownload={onDownload}
                        confirmingDeleteId={confirmingDeleteId}
                        deletingIds={deletingIds}
                        onStartDelete={onStartDelete}
                        onConfirmDelete={onConfirmDelete}
                        onCancelDelete={onCancelDelete}
                    />
                ))}
        </div>
    );
}

function FirstLevelFolder({
    category,
    documents,
    expandAll,
    onPreview,
    onDownload,
    confirmingDeleteId,
    deletingIds,
    onStartDelete,
    onConfirmDelete,
    onCancelDelete,
}: {
    category: DocumentCategory;
    documents: Document[];
    expandAll: boolean | null;
    onPreview: (doc: Document) => void;
    onDownload: (doc: Document) => void;
    confirmingDeleteId: number | null;
    deletingIds: Set<number>;
    onStartDelete: (id: number) => void;
    onConfirmDelete: (id: number) => void;
    onCancelDelete: () => void;
}) {
    const [expanded, setExpanded] = React.useState(true);

    React.useEffect(() => {
        if (expandAll !== null) {
            setExpanded(expandAll);
        }
    }, [expandAll]);

    const items = collectDocs(category, documents);

    if (items.length === 0) return null;

    return (
        <div>
            <div
                className={cn(
                    "flex items-center gap-1.5 rounded-md px-2 py-1.5 text-sm",
                    "hover:bg-muted/50 cursor-pointer select-none",
                )}
                style={{ paddingInlineStart: "16px" }}
                onClick={() => setExpanded(!expanded)}
            >
                <IconChevronRight
                    className={cn(
                        "size-3.5 shrink-0 text-muted-foreground transition-transform",
                        expanded && "rotate-90",
                    )}
                />
                {expanded ? (
                    <IconFolderOpen className="size-4 shrink-0 text-blue-500" />
                ) : (
                    <IconFolder className="size-4 shrink-0 text-blue-500" />
                )}
                <span className="truncate font-medium">{category.name}</span>
                <span className="text-xs text-muted-foreground">
                    ({items.length})
                </span>
            </div>

            {expanded && (
                <div>
                    {items.map(({ doc, exactCategory }) => (
                        <TreeFileItem
                            key={doc.id}
                            doc={doc}
                            exactCategory={exactCategory}
                            onPreview={onPreview}
                            onDownload={onDownload}
                            confirmingDeleteId={confirmingDeleteId}
                            deletingIds={deletingIds}
                            onStartDelete={onStartDelete}
                            onConfirmDelete={onConfirmDelete}
                            onCancelDelete={onCancelDelete}
                        />
                    ))}
                </div>
            )}
        </div>
    );
}

function TreeFileItem({
    doc,
    exactCategory,
    onPreview,
    onDownload,
    confirmingDeleteId,
    deletingIds,
    onStartDelete,
    onConfirmDelete,
    onCancelDelete,
}: {
    doc: Document;
    exactCategory: string;
    onPreview: (doc: Document) => void;
    onDownload: (doc: Document) => void;
    confirmingDeleteId: number | null;
    deletingIds: Set<number>;
    onStartDelete: (id: number) => void;
    onConfirmDelete: (id: number) => void;
    onCancelDelete: () => void;
}) {
    const isDeleting = deletingIds.has(doc.id);
    const isConfirming = confirmingDeleteId === doc.id;

    return (
        <div
            className={cn(
                "group flex items-center gap-2 rounded-md px-2 py-1 text-sm",
                "hover:bg-muted/50 cursor-pointer",
            )}
            style={{ paddingInlineStart: "36px" }}
            onClick={() => onPreview(doc)}
        >
            <div
                className="flex items-center gap-2 min-w-0 flex-1"
            >
                <DocumentThumbnail
                    document={doc}
                    variant="icon"
                    size="xs"
                />
                <div className="min-w-0 flex-1">
                    <p className="truncate text-sm">{getDocOriginalName(doc)}</p>
                    <p className="text-xs text-muted-foreground truncate">
                        {exactCategory}
                    </p>
                </div>
            </div>
            <span className="text-xs text-muted-foreground whitespace-nowrap">
                {getFileTypeLabel(getDocMimeType(doc))}
            </span>
            <span className="text-xs text-muted-foreground whitespace-nowrap">
                {getDocFileSizeFormatted(doc)}
            </span>
            <span className="text-xs text-muted-foreground whitespace-nowrap">
                {toPersianDate(doc.created_at)}
            </span>
            <div className="flex items-center gap-0.5 opacity-0 group-hover:opacity-100 transition-opacity">
                <Button
                    variant="ghost"
                    size="icon-xs"
                    onClick={(e) => {
                        e.stopPropagation();
                        onDownload(doc);
                    }}
                    disabled={!doc.download_url && !doc.url}
                >
                    <IconDownload className="size-3.5" />
                </Button>
                {isConfirming ? (
                    <ConfirmDeleteActions
                        docId={doc.id}
                        isPending={isDeleting}
                        onConfirm={onConfirmDelete}
                        onCancel={onCancelDelete}
                    />
                ) : (
                    <Button
                        variant="ghost"
                        size="icon-xs"
                        onClick={(e) => {
                            e.stopPropagation();
                            onStartDelete(doc.id);
                        }}
                        disabled={isDeleting}
                    >
                        {isDeleting ? (
                            <IconLoader2 className="size-3.5 animate-spin" />
                        ) : (
                            <IconTrash className="size-3.5" />
                        )}
                    </Button>
                )}
            </div>
        </div>
    );
}

type DocumentTreeViewProps = {
    categories: DocumentCategory[];
    documents: Document[];
    onPreview: (doc: Document) => void;
    onDownload: (doc: Document) => void;
    confirmingDeleteId: number | null;
    deletingIds: Set<number>;
    onStartDelete: (id: number) => void;
    onConfirmDelete: (id: number) => void;
    onCancelDelete: () => void;
};

export function DocumentTreeView({
    categories,
    documents,
    onPreview,
    onDownload,
    confirmingDeleteId,
    deletingIds,
    onStartDelete,
    onConfirmDelete,
    onCancelDelete,
}: DocumentTreeViewProps) {
    const [expandAll, setExpandAll] = React.useState<boolean | null>(null);

    function handleToggleExpand() {
        setExpandAll((prev) => (prev === true ? false : true));
    }

    return (
        <div className="space-y-2">
            <div className="flex items-center justify-end">
                <Button
                    variant="ghost"
                    size="sm"
                    onClick={handleToggleExpand}
                    className="text-xs"
                >
                    {expandAll === true ? (
                        <IconChevronsUpRight className="size-3.5" />
                    ) : (
                        <IconChevronsDownRight className="size-3.5" />
                    )}
                    {expandAll === true ? "بستن همه" : "باز کردن همه"}
                </Button>
            </div>
            <div className="rounded-lg border">
                {categories.map((cat) => (
                    <TopLevelFolder
                        key={cat.id}
                        category={cat}
                        documents={documents}
                        expandAll={expandAll}
                        onPreview={onPreview}
                        onDownload={onDownload}
                        confirmingDeleteId={confirmingDeleteId}
                        deletingIds={deletingIds}
                        onStartDelete={onStartDelete}
                        onConfirmDelete={onConfirmDelete}
                        onCancelDelete={onCancelDelete}
                    />
                ))}
            </div>
        </div>
    );
}
