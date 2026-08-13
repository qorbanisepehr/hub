"use client";

import * as React from "react";
import {
    IconChevronDown,
    IconColumns,
    IconDownload,
    IconFolder,
    IconFolderOpen,
    IconLayoutGrid,
    IconList,
    IconTable,
} from "@tabler/icons-react";

import { cn } from "@/lib/utils";
import { formatBytes } from "@/lib/file-size";
import { getFileIcon } from "@/lib/file-icon";
import { getFileColorClasses } from "@/lib/file-colors";
import { getFileTypeLabel } from "@/lib/file-type-label";
import { Button } from "@/components/ui/button";
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from "@/components/ui/tooltip";
import {
    Attachment,
    AttachmentAction,
    AttachmentActions,
    AttachmentContent,
    AttachmentMedia,
    AttachmentTitle,
    AttachmentTrigger,
} from "@/components/ui/attachment";
import {
    Table,
    TableHeader,
    TableBody,
    TableHead,
    TableCell,
    TableRow,
} from "@/components/ui/table";
import { FileThumbnail } from "@/components/ui/file-thumbnail";
import { DocumentPreviewLightbox } from "@/features/documents/components/document-preview-lightbox";
import type { Document } from "@/features/documents/types";
import type { EntityDocument } from "@/hooks/use-entity-documents";

/**
 * Group documents by their category, in upload order, so that every uploaded
 * document is represented regardless of which category it belongs to. Documents
 * without a category fall back to a generic "سایر مدارک" group.
 */
export function groupDocumentsByCategory<T extends EntityDocument>(
    documents: T[],
): { label: string; docs: T[] }[] {
    const groups: { label: string; docs: T[] }[] = [];
    const byKey = new Map<string, { label: string; docs: T[] }>();

    for (const doc of documents) {
        const key = doc.category_slug ?? "other";
        let group = byKey.get(key);

        if (!group) {
            group = {
                label: doc.category_label ?? "سایر مدارک",
                docs: [],
            };
            byKey.set(key, group);
            groups.push(group);
        }

        group.docs.push(doc);
    }

    return groups;
}

/**
 * Bridge an EntityDocument into the Document shape the preview lightbox
 * expects, so read-only views can reuse the shared preview without any `any`
 * casts.
 */
export function toLightboxDocument(doc: EntityDocument): Document {
    return {
        id: doc.usage_id,
        documentable_type: "entity",
        documentable_id: 0,
        document_category_id: 0,
        category: doc.category_label
            ? {
                  id: 0,
                  name: doc.category_label,
                  slug: doc.category_slug ?? "",
                  description: null,
                  sort_order: 0,
                  parent_id: null,
                  type: "",
              }
            : undefined,
        status: "pending",
        notes: doc.notes,
        meta: doc.metadata,
        section_key: doc.section_key,
        field_key: doc.field_key,
        structure_name: doc.structure_name,
        mime_type: doc.mime_type,
        size: doc.size,
        original_name: doc.structure_name,
        uploaded_by: null,
        uploader_name: null,
        serve_url: doc.url,
        thumbnail_url: "",
        download_url: doc.download_url ?? doc.url,
        url: doc.url,
        created_at: "",
        updated_at: "",
        deleted_at: null,
    };
}

export type DocumentViewerMode = "table" | "grouped" | "tree" | "card" | "list";

type DocumentViewerProps = {
    documents: EntityDocument[];
    className?: string;
};

const VIEW_BUTTONS: {
    mode: DocumentViewerMode;
    icon: typeof IconTable;
    label: string;
}[] = [
    { mode: "table", icon: IconTable, label: "جدول" },
    { mode: "grouped", icon: IconColumns, label: "جدول دسته‌بندی" },
    { mode: "tree", icon: IconFolder, label: "پوشه‌ای" },
    { mode: "card", icon: IconLayoutGrid, label: "کارتی" },
    { mode: "list", icon: IconList, label: "لیستی" },
];

function handleDownload(doc: EntityDocument) {
    const url = doc.download_url ?? doc.url;
    if (!url) return;

    const a = document.createElement("a");
    a.href = url;
    a.download = doc.structure_name;
    document.body.appendChild(a);
    a.click();
    a.remove();
}

function EntityDocThumb({
    doc,
    className,
}: {
    doc: EntityDocument;
    className?: string;
}) {
    const isImage = doc.mime_type.startsWith("image/");

    if (isImage) {
        return (
            <div
                className={cn(
                    "overflow-hidden rounded-md border bg-background",
                    className,
                )}
            >
                <FileThumbnail
                    file={{ name: doc.structure_name, type: doc.mime_type }}
                    previewImageUrl={doc.url}
                    className="size-full rounded-none border-0"
                    previewClassName="aspect-square"
                />
            </div>
        );
    }

    return (
        <div
            className={cn(
                "flex shrink-0 items-center justify-center rounded-md border",
                getFileColorClasses(doc.mime_type),
                className,
            )}
        >
            {getFileIcon(doc.mime_type, "size-1/2")}
        </div>
    );
}

function DownloadButton({ doc }: { doc: EntityDocument }) {
    return (
        <Button
            variant="ghost"
            size="icon-xs"
            onClick={(e) => {
                e.stopPropagation();
                handleDownload(doc);
            }}
            aria-label={`دانلود ${doc.structure_name}`}
        >
            <IconDownload className="size-3.5" />
        </Button>
    );
}

function DocumentTableView({
    documents,
    onPreview,
}: {
    documents: EntityDocument[];
    onPreview: (doc: EntityDocument) => void;
}) {
    return (
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>فایل</TableHead>
                    <TableHead>دسته‌بندی</TableHead>
                    <TableHead>نوع</TableHead>
                    <TableHead>اندازه</TableHead>
                    <TableHead className="w-12" />
                </TableRow>
            </TableHeader>
            <TableBody>
                {documents.map((doc) => (
                    <TableRow
                        key={doc.usage_id}
                        className="cursor-pointer"
                        onClick={() => onPreview(doc)}
                    >
                        <TableCell>
                            <div className="flex min-w-0 items-center gap-2">
                                <EntityDocThumb doc={doc} className="size-10" />
                                <span className="truncate font-medium">
                                    {doc.structure_name}
                                </span>
                            </div>
                        </TableCell>
                        <TableCell className="text-muted-foreground">
                            {doc.category_label ?? "—"}
                        </TableCell>
                        <TableCell className="text-muted-foreground">
                            {getFileTypeLabel(doc.mime_type)}
                        </TableCell>
                        <TableCell className="text-muted-foreground">
                            {formatBytes(doc.size)}
                        </TableCell>
                        <TableCell>
                            <DownloadButton doc={doc} />
                        </TableCell>
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}

function DocumentRow({
    doc,
    onPreview,
}: {
    doc: EntityDocument;
    onPreview: (doc: EntityDocument) => void;
}) {
    return (
        <div
            key={doc.usage_id}
            role="button"
            tabIndex={0}
            onClick={() => onPreview(doc)}
            onKeyDown={(e) => {
                if (e.key === "Enter" || e.key === " ") {
                    e.preventDefault();
                    onPreview(doc);
                }
            }}
            className="flex cursor-pointer items-center gap-3 px-3 py-2 transition-colors hover:bg-muted/40"
        >
            <EntityDocThumb doc={doc} className="size-10" />
            <div className="min-w-0 flex-1">
                <p className="truncate text-sm font-medium">
                    {doc.structure_name}
                </p>
                <p className="text-xs text-muted-foreground">
                    {getFileTypeLabel(doc.mime_type)} • {formatBytes(doc.size)}
                </p>
            </div>
            <DownloadButton doc={doc} />
        </div>
    );
}

function DocumentGroupedView({
    groups,
    onPreview,
}: {
    groups: { label: string; docs: EntityDocument[] }[];
    onPreview: (doc: EntityDocument) => void;
}) {
    return (
        <div className="overflow-hidden rounded-lg border">
            {groups.map((group) => (
                <div key={group.label}>
                    <div className="flex items-center gap-2 bg-muted/50 px-3 py-2">
                        <IconFolder className="size-4 text-blue-500" />
                        <span className="text-sm font-medium">{group.label}</span>
                        <span className="text-xs text-muted-foreground">
                            ({group.docs.length})
                        </span>
                    </div>
                    <div className="divide-y">
                        {group.docs.map((doc) => (
                            <DocumentRow
                                key={doc.usage_id}
                                doc={doc}
                                onPreview={onPreview}
                            />
                        ))}
                    </div>
                </div>
            ))}
        </div>
    );
}

function TreeGroup({
    group,
    onPreview,
}: {
    group: { label: string; docs: EntityDocument[] };
    onPreview: (doc: EntityDocument) => void;
}) {
    const [open, setOpen] = React.useState(true);

    return (
        <div className="rounded-lg border">
            <button
                type="button"
                onClick={() => setOpen((prev) => !prev)}
                className="flex w-full items-center gap-2 px-3 py-2 text-start transition-colors hover:bg-muted/40"
            >
                {open ? (
                    <IconFolderOpen className="size-4 shrink-0 text-blue-500" />
                ) : (
                    <IconFolder className="size-4 shrink-0 text-blue-500" />
                )}
                <span className="text-sm font-medium">{group.label}</span>
                <span className="text-xs text-muted-foreground">
                    ({group.docs.length})
                </span>
                <IconChevronDown
                    className={cn(
                        "ms-auto size-4 shrink-0 text-muted-foreground transition-transform",
                        open && "rotate-180",
                    )}
                />
            </button>
            {open && (
                <div className="divide-y border-t">
                    {group.docs.map((doc) => (
                        <DocumentRow
                            key={doc.usage_id}
                            doc={doc}
                            onPreview={onPreview}
                        />
                    ))}
                </div>
            )}
        </div>
    );
}

function DocumentTreeView({
    groups,
    onPreview,
}: {
    groups: { label: string; docs: EntityDocument[] }[];
    onPreview: (doc: EntityDocument) => void;
}) {
    return (
        <div className="space-y-2">
            {groups.map((group) => (
                <TreeGroup key={group.label} group={group} onPreview={onPreview} />
            ))}
        </div>
    );
}

function DocumentCardListView({
    groups,
    viewMode,
    onPreview,
}: {
    groups: { label: string; docs: EntityDocument[] }[];
    viewMode: "card" | "list";
    onPreview: (doc: EntityDocument) => void;
}) {
    return (
        <div className="space-y-4">
            {groups.map((group) => (
                <div key={group.label}>
                    <div className="mb-2 flex items-center gap-1.5">
                        <IconFolder className="size-4 text-blue-500" />
                        <span className="text-sm font-medium">{group.label}</span>
                        <span className="text-xs text-muted-foreground">
                            ({group.docs.length})
                        </span>
                    </div>
                    {viewMode === "card" ? (
                        <div className="flex flex-wrap gap-3">
                            {group.docs.map((doc) => (
                                <Attachment
                                    key={doc.usage_id}
                                    size="default"
                                    orientation="vertical"
                                    className="cursor-pointer"
                                >
                                    <AttachmentTrigger
                                        onClick={() => onPreview(doc)}
                                    />
                                    <AttachmentMedia
                                        variant="image"
                                        className="p-0"
                                    >
                                        <EntityDocThumb
                                            doc={doc}
                                            className="size-full rounded-none border-0"
                                        />
                                    </AttachmentMedia>
                                    <AttachmentContent>
                                        <AttachmentTitle>
                                            {doc.structure_name}
                                        </AttachmentTitle>
                                        <span className="text-xs text-muted-foreground">
                                            {getFileTypeLabel(doc.mime_type)}{" "}
                                            • {formatBytes(doc.size)}
                                        </span>
                                    </AttachmentContent>
                                    <AttachmentActions>
                                        <AttachmentAction
                                            onClick={(e) => {
                                                e.stopPropagation();
                                                handleDownload(doc);
                                            }}
                                            aria-label={`دانلود ${doc.structure_name}`}
                                        >
                                            <IconDownload className="size-3.5" />
                                        </AttachmentAction>
                                    </AttachmentActions>
                                </Attachment>
                            ))}
                        </div>
                    ) : (
                        <div className="space-y-1.5">
                            {group.docs.map((doc) => (
                                <Attachment
                                    key={doc.usage_id}
                                    size="sm"
                                    className="cursor-pointer"
                                >
                                    <AttachmentTrigger
                                        onClick={() => onPreview(doc)}
                                    />
                                    <AttachmentMedia>
                                        <EntityDocThumb
                                            doc={doc}
                                            className="size-full rounded-none border-0"
                                        />
                                    </AttachmentMedia>
                                    <AttachmentContent>
                                        <AttachmentTitle>
                                            {doc.structure_name}
                                        </AttachmentTitle>
                                        <span className="text-xs text-muted-foreground">
                                            {getFileTypeLabel(doc.mime_type)}{" "}
                                            • {formatBytes(doc.size)}
                                        </span>
                                    </AttachmentContent>
                                    <AttachmentActions>
                                        <AttachmentAction
                                            onClick={(e) => {
                                                e.stopPropagation();
                                                handleDownload(doc);
                                            }}
                                            aria-label={`دانلود ${doc.structure_name}`}
                                        >
                                            <IconDownload className="size-3.5" />
                                        </AttachmentAction>
                                    </AttachmentActions>
                                </Attachment>
                            ))}
                        </div>
                    )}
                </div>
            ))}
        </div>
    );
}

/**
 * Read-only, shared document viewer. Renders any collection of
 * EntityDocument (cv / questionnaire / employee) with the same view modes as
 * the employee documents tab — table, grouped, tree, card, list — plus a
 * lightbox preview and per-file download. Intentionally has no upload, trash,
 * or selection affordances; the employee documents manager owns those.
 */
export function DocumentViewer({ documents, className }: DocumentViewerProps) {
    const [viewMode, setViewMode] =
        React.useState<DocumentViewerMode>("grouped");
    const [lightboxIndex, setLightboxIndex] = React.useState<number | null>(
        null,
    );

    const groups = React.useMemo(
        () => groupDocumentsByCategory(documents),
        [documents],
    );
    const lightboxDocs = React.useMemo(
        () => documents.map(toLightboxDocument),
        [documents],
    );

    if (documents.length === 0) return null;

    function handlePreview(doc: EntityDocument) {
        const index = documents.findIndex((d) => d.usage_id === doc.usage_id);
        if (index !== -1) setLightboxIndex(index);
    }

    return (
        <div className={cn("space-y-4", className)}>
            <div className="flex items-center justify-between">
                <span className="text-sm text-muted-foreground">
                    {documents.length} مدرک
                </span>
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
                                        variant={
                                            viewMode === mode
                                                ? "secondary"
                                                : "ghost"
                                        }
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
                <DocumentTableView
                    documents={documents}
                    onPreview={handlePreview}
                />
            )}

            {viewMode === "grouped" && (
                <DocumentGroupedView groups={groups} onPreview={handlePreview} />
            )}

            {viewMode === "tree" && (
                <DocumentTreeView groups={groups} onPreview={handlePreview} />
            )}

            {(viewMode === "card" || viewMode === "list") && (
                <DocumentCardListView
                    groups={groups}
                    viewMode={viewMode}
                    onPreview={handlePreview}
                />
            )}

            <DocumentPreviewLightbox
                documents={lightboxDocs}
                currentIndex={lightboxIndex ?? 0}
                open={lightboxIndex !== null}
                onClose={() => setLightboxIndex(null)}
                onNavigate={setLightboxIndex}
            />
        </div>
    );
}
