import * as React from "react";
import {
    IconChevronRight,
    IconDownload,
    IconLoader2,
    IconTrash,
    IconFolder,
    IconFolderOpen,
} from "@tabler/icons-react";

import { cn } from "@/lib/utils";
import { getFileIcon } from "@/lib/file-utils";
import { getFileColorClasses } from "@/lib/file-utils";
import { getFileTypeLabel } from "@/lib/file-utils";
import { DocumentThumbnail } from "@/components/documents";
import { Checkbox } from "@/components/ui/checkbox";
import { Button } from "@/components/ui/button";
import {
    Table,
    TableHeader,
    TableBody,
    TableHead,
    TableRow,
    TableCell,
} from "@/components/ui/table";
import { ConfirmDeleteActions } from "./confirm-delete-actions";
import { toPersianDate } from "@/lib/date-format";
import type { Document, DocumentCategory } from "@/features/documents/types";
import {
    getDocOriginalName,
    getDocMimeType,
    getDocFileSizeFormatted,
    collectDocs,
} from "@/features/documents/types";

function TopLevelGroup({
    category,
    documents,
    onPreview,
    onDownload,
    confirmingDeleteId,
    deletingIds,
    onStartDelete,
    onConfirmDelete,
    onCancelDelete,
    selectedIds,
    onSelectionChange,
}: {
    category: DocumentCategory;
    documents: Document[];
    onPreview: (doc: Document) => void;
    onDownload: (doc: Document) => void;
    confirmingDeleteId: number | null;
    deletingIds: Set<number>;
    onStartDelete: (id: number) => void;
    onConfirmDelete: (id: number) => void;
    onCancelDelete: () => void;
    selectedIds: number[];
    onSelectionChange: (ids: number[]) => void;
}) {
    const [expanded, setExpanded] = React.useState(true);
    const children = category.children ?? [];
    const totalCount = children.reduce(
        (sum, child) => sum + collectDocs(child, documents).length,
        0,
    );

    if (totalCount === 0) return null;

    return (
        <>
            <TableRow
                className="cursor-pointer hover:bg-muted/30"
                onClick={() => setExpanded(!expanded)}
            >
                <TableCell colSpan={7} className="py-1.5">
                    <div className="flex items-center gap-1.5">
                        <IconChevronRight
                            className={cn(
                                "size-3.5 text-muted-foreground transition-transform",
                                expanded && "rotate-90",
                            )}
                        />
                        {expanded ? (
                            <IconFolderOpen className="size-4 shrink-0 text-blue-500" />
                        ) : (
                            <IconFolder className="size-4 shrink-0 text-blue-500" />
                        )}
                        <span className="text-sm font-semibold">
                            {category.name}
                        </span>
                        <span className="text-xs text-muted-foreground">
                            ({totalCount})
                        </span>
                    </div>
                </TableCell>
            </TableRow>
            {expanded &&
                children.map((child) => (
                    <FirstLevelGroup
                        key={child.id}
                        category={child}
                        documents={documents}
                        onPreview={onPreview}
                        onDownload={onDownload}
                        confirmingDeleteId={confirmingDeleteId}
                        deletingIds={deletingIds}
                        onStartDelete={onStartDelete}
                        onConfirmDelete={onConfirmDelete}
                        onCancelDelete={onCancelDelete}
                        selectedIds={selectedIds}
                        onSelectionChange={onSelectionChange}
                    />
                ))}
        </>
    );
}

function FirstLevelGroup({
    category,
    documents,
    onPreview,
    onDownload,
    confirmingDeleteId,
    deletingIds,
    onStartDelete,
    onConfirmDelete,
    onCancelDelete,
    selectedIds,
    onSelectionChange,
}: {
    category: DocumentCategory;
    documents: Document[];
    onPreview: (doc: Document) => void;
    onDownload: (doc: Document) => void;
    confirmingDeleteId: number | null;
    deletingIds: Set<number>;
    onStartDelete: (id: number) => void;
    onConfirmDelete: (id: number) => void;
    onCancelDelete: () => void;
    selectedIds: number[];
    onSelectionChange: (ids: number[]) => void;
}) {
    const [expanded, setExpanded] = React.useState(true);
    const items = collectDocs(category, documents);

    if (items.length === 0) return null;

    return (
        <>
            <TableRow
                className="cursor-pointer hover:bg-muted/30"
                onClick={() => setExpanded(!expanded)}
            >
                <TableCell colSpan={7} className="py-1.5">
                    <div
                        className="flex items-center gap-1.5"
                        style={{ paddingInlineStart: "16px" }}
                    >
                        <IconChevronRight
                            className={cn(
                                "size-3.5 text-muted-foreground transition-transform",
                                expanded && "rotate-90",
                            )}
                        />
                        {expanded ? (
                            <IconFolderOpen className="size-4 shrink-0 text-blue-500" />
                        ) : (
                            <IconFolder className="size-4 shrink-0 text-blue-500" />
                        )}
                        <span className="text-sm font-medium">
                            {category.name}
                        </span>
                        <span className="text-xs text-muted-foreground">
                            ({items.length})
                        </span>
                    </div>
                </TableCell>
            </TableRow>
            {expanded &&
                items.map(({ doc, exactCategory }) => (
                    <FileRow
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
                        selectedIds={selectedIds}
                        onSelectionChange={onSelectionChange}
                    />
                ))}
        </>
    );
}

function FileRow({
    doc,
    exactCategory,
    onPreview,
    onDownload,
    confirmingDeleteId,
    deletingIds,
    onStartDelete,
    onConfirmDelete,
    onCancelDelete,
    selectedIds,
    onSelectionChange,
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
    selectedIds: number[];
    onSelectionChange: (ids: number[]) => void;
}) {
    const isDeleting = deletingIds.has(doc.id);
    const isConfirming = confirmingDeleteId === doc.id;

    return (
        <TableRow
            className="cursor-pointer group/row"
            onClick={() => onPreview(doc)}
        >
            <TableCell className="w-10" onClick={(e) => e.stopPropagation()}>
                <Checkbox
                    checked={selectedIds.includes(doc.id)}
                    onCheckedChange={(value) => {
                        if (value) {
                            onSelectionChange([...selectedIds, doc.id]);
                        } else {
                            onSelectionChange(
                                selectedIds.filter((id) => id !== doc.id),
                            );
                        }
                    }}
                />
            </TableCell>
            <TableCell className="ps-8">
                <div
                    className="flex items-center gap-2.5"
                    style={{ paddingInlineStart: "32px" }}
                >
                    <DocumentThumbnail
                        document={doc}
                        variant="default"
                        size="sm"
                    />
                    <div className="min-w-0">
                        <p className="truncate text-sm font-medium">
                            {getDocOriginalName(doc)}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            {exactCategory}
                        </p>
                    </div>
                </div>
            </TableCell>
            <TableCell>
                <span className="text-sm text-muted-foreground whitespace-nowrap">
                    {getFileTypeLabel(getDocMimeType(doc))}
                </span>
            </TableCell>
            <TableCell>
                <span className="text-sm text-muted-foreground whitespace-nowrap">
                    {getDocFileSizeFormatted(doc)}
                </span>
            </TableCell>
            <TableCell>
                <span className="text-sm text-muted-foreground whitespace-nowrap">
                    {toPersianDate(doc.created_at)}
                </span>
            </TableCell>
            <TableCell>
                <span className="text-sm text-muted-foreground">
                    {doc.uploaded_by ?? "—"}
                </span>
            </TableCell>
            <TableCell
                className="sticky inset-e-0 z-10 bg-card shadow-[-2px_0_5px_-2px_rgba(0,0,0,0.1)] cursor-default"
                onClick={(e) => e.stopPropagation()}
            >
                <div className="flex items-center justify-end gap-1">
                    <Button
                        variant="ghost"
                        size="icon-xs"
                        onClick={() => onDownload(doc)}
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
                            onClick={() => onStartDelete(doc.id)}
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
            </TableCell>
        </TableRow>
    );
}

type DocumentGroupedTableProps = {
    categories: DocumentCategory[];
    documents: Document[];
    onPreview: (doc: Document) => void;
    onDownload: (doc: Document) => void;
    confirmingDeleteId: number | null;
    deletingIds: Set<number>;
    onStartDelete: (id: number) => void;
    onConfirmDelete: (id: number) => void;
    onCancelDelete: () => void;
    selectedIds: number[];
    onSelectionChange: (ids: number[]) => void;
};

export function DocumentGroupedTable({
    categories,
    documents,
    onPreview,
    onDownload,
    confirmingDeleteId,
    deletingIds,
    onStartDelete,
    onConfirmDelete,
    onCancelDelete,
    selectedIds,
    onSelectionChange,
}: DocumentGroupedTableProps) {
    return (
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead className="w-10" />
                    <TableHead className="ps-8">فایل</TableHead>
                    <TableHead>نوع</TableHead>
                    <TableHead>اندازه</TableHead>
                    <TableHead>تاریخ</TableHead>
                    <TableHead>آپلود توسط</TableHead>
                    <TableHead className="sticky inset-e-0 z-20 bg-card shadow-[-2px_0_5px_-2px_rgba(0,0,0,0.1)]" />
                </TableRow>
            </TableHeader>
            <TableBody>
                {categories.map((cat) => (
                    <TopLevelGroup
                        key={cat.id}
                        category={cat}
                        documents={documents}
                        onPreview={onPreview}
                        onDownload={onDownload}
                        confirmingDeleteId={confirmingDeleteId}
                        deletingIds={deletingIds}
                        onStartDelete={onStartDelete}
                        onConfirmDelete={onConfirmDelete}
                        onCancelDelete={onCancelDelete}
                        selectedIds={selectedIds}
                        onSelectionChange={onSelectionChange}
                    />
                ))}
            </TableBody>
        </Table>
    );
}
