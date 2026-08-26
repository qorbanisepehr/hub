import * as React from "react";
import { useQuery } from "@tanstack/react-query";
import {
    IconChevronDown,
    IconChevronLeft,
    IconFile,
    IconFolder,
    IconFolderOpen,
    IconLayoutGrid,
    IconLayoutList,
    IconListTree,
} from "@tabler/icons-react";

import { Button } from "@/components/ui/button";
import { FileThumbnail } from "@/components/ui/file-thumbnail";
import { DocumentPreviewLightbox } from "@/features/documents/components/document-preview-lightbox";
import type { Document } from "@/features/documents/types";
import { fetchTempEmployeeTree, tempFileUrl } from "../api";
import type { TempEmployee, TempFileNode } from "../types";
import { getFileColorClasses, getFileIcon, formatBytes } from "@/lib/file-utils";
import { toPersianDate } from "@/lib/date-format";
import { cn } from "@/lib/utils";

function useTreeQuery(personnelCode: string) {
    return useQuery({
        queryKey: ["temp-employees", personnelCode, "tree"],
        queryFn: async () => {
            const { data } = await fetchTempEmployeeTree(personnelCode);
            return data.data;
        },
        staleTime: 60_000,
    });
}

type ExplorerMode = "tree" | "table" | "card";

const MODE_BUTTONS: {
    mode: ExplorerMode;
    icon: typeof IconListTree;
    label: string;
}[] = [
    { mode: "tree", icon: IconListTree, label: "درختی" },
    { mode: "table", icon: IconLayoutList, label: "جدولی" },
    { mode: "card", icon: IconLayoutGrid, label: "کارتی" },
];

/** Fabricate the minimal Document shape the shared lightbox renders. */
function toLightboxDoc(
    employee: TempEmployee,
    node: TempFileNode,
    index: number,
): Document {
    return {
        id: index + 1,
        documentable_type: "temp-employee",
        documentable_id: employee.id,
        document_category_id: 0,
        status: "confirmed",
        notes: null,
        meta: null,
        section_key: null,
        field_key: null,
        structure_name: node.name,
        original_name: node.name,
        mime_type: node.mime ?? undefined,
        size: node.size ?? 0,
        uploaded_by: null,
        uploader_name: null,
        url: tempFileUrl(employee.personnel_code, node.path),
        created_at: node.modified_at ?? "",
        updated_at: node.modified_at ?? "",
        deleted_at: null,
    };
}

function guessMime(name: string): string {
    const ext = name.split(".").pop()?.toLowerCase() ?? "";
    if (["png"].includes(ext)) return "image/png";
    if (["jpg", "jpeg"].includes(ext)) return "image/jpeg";
    if (ext === "pdf") return "application/pdf";
    if (ext === "txt") return "text/plain";
    return "application/octet-stream";
}

function TreeRow({
    node,
    depth,
    files,
    expanded,
    toggle,
    onPreview,
}: {
    node: TempFileNode;
    depth: number;
    files: TempFileNode[];
    expanded: Set<string>;
    toggle: (path: string) => void;
    onPreview: (node: TempFileNode) => void;
}) {
    const isOpen = expanded.has(node.path);

    if (node.type === "dir") {
        const children = files.filter(
            (f) =>
                f.path.startsWith(`${node.path}/`) &&
                !f.path.slice(node.path.length + 1).includes("/"),
        );

        // Recursive file count for this directory.
        const fileCount = files.filter((f) =>
            f.path.startsWith(`${node.path}/`),
        ).length;

        return (
            <div>
                <button
                    type="button"
                    className="flex w-full items-center gap-1.5 rounded-md px-2 py-1.5 text-start text-sm hover:bg-muted"
                    style={{ paddingInlineStart: `${8 + depth * 18}px` }}
                    onClick={() => toggle(node.path)}
                >
                    {/* RTL: the chevron sits on the right (inline start). */}
                    {isOpen ? (
                        <IconChevronDown className="size-3.5 shrink-0 text-muted-foreground" />
                    ) : (
                        <IconChevronLeft className="size-3.5 shrink-0 text-muted-foreground" />
                    )}
                    {isOpen ? (
                        <IconFolderOpen className="size-4 shrink-0 text-warning" />
                    ) : (
                        <IconFolder className="size-4 shrink-0 text-warning" />
                    )}
                    <span className="truncate">{node.name}</span>
                    {fileCount > 0 && (
                        <span
                            className="ms-auto rounded-full bg-muted px-2 text-xs text-muted-foreground"
                            dir="ltr"
                        >
                            {fileCount}
                        </span>
                    )}
                </button>

                {isOpen &&
                    children.map((child) => (
                        <TreeRow
                            key={child.path}
                            node={child}
                            depth={depth + 1}
                            files={files}
                            expanded={expanded}
                            toggle={toggle}
                            onPreview={onPreview}
                        />
                    ))}
            </div>
        );
    }

    return (
        <button
            type="button"
            className="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-start text-sm hover:bg-muted"
            style={{ paddingInlineStart: `${8 + depth * 18 + 20}px` }}
            onClick={() => onPreview(node)}
        >
            <span
                className={cn(
                    "flex size-6 items-center justify-center rounded",
                    getFileColorClasses(node.mime ?? guessMime(node.name)),
                )}
            >
                {getFileIcon(node.mime ?? guessMime(node.name), "size-3.5")}
            </span>
            <span className="min-w-0 flex-1 truncate">{node.name}</span>
            {node.size !== null && (
                <span className="text-xs text-muted-foreground" dir="ltr">
                    {formatBytes(node.size)}
                </span>
            )}
        </button>
    );
}

export function TempFileExplorer({ employee }: { employee: TempEmployee }) {
    const [mode, setMode] = React.useState<ExplorerMode>("tree");
    const [expanded, setExpanded] = React.useState<Set<string>>(new Set());
    const [previewIndex, setPreviewIndex] = React.useState<number | null>(null);

    const { data: tree, isLoading } = useTreeQuery(employee.personnel_code);
    const files = React.useMemo(
        () => (tree ?? []).filter((n) => n.type === "file"),
        [tree],
    );

    const lightboxDocs = React.useMemo(
        () => files.map((node, index) => toLightboxDoc(employee, node, index)),
        [employee, files],
    );

    function toggle(path: string) {
        setExpanded((prev) => {
            const next = new Set(prev);
            if (next.has(path)) next.delete(path);
            else next.add(path);
            return next;
        });
    }

    function openPreview(node: TempFileNode) {
        const index = files.findIndex((f) => f.path === node.path);
        if (index >= 0) setPreviewIndex(index);
    }

    return (
        <div className="space-y-3">
            <div className="flex items-center justify-between">
                <span className="text-sm font-medium">فایل‌ها و پوشه‌ها</span>
                <div className="flex items-center gap-1">
                    {MODE_BUTTONS.map(({ mode: m, icon: Icon, label }) => (
                        <Button
                            key={m}
                            type="button"
                            variant={mode === m ? "default" : "ghost"}
                            size="sm"
                            onClick={() => setMode(m)}
                        >
                            <Icon className="size-4 ms-1" />
                            {label}
                        </Button>
                    ))}
                </div>
            </div>

            {isLoading ? (
                <p className="py-6 text-center text-sm text-muted-foreground">
                    در حال بارگذاری…
                </p>
            ) : files.length === 0 ? (
                <p className="py-6 text-center text-sm text-muted-foreground">
                    فایلی برای این کد پرسنلی یافت نشد.
                </p>
            ) : mode === "tree" ? (
                <div className="rounded-lg border p-2">
                    {/* Roots: entries whose path has no slash. */}
                    {(tree ?? [])
                        .filter((n) => !n.path.includes("/"))
                        .map((root) => (
                            <TreeRow
                                key={root.path}
                                node={root}
                                depth={0}
                                files={files}
                                expanded={expanded}
                                toggle={toggle}
                                onPreview={openPreview}
                            />
                        ))}
                </div>
            ) : mode === "table" ? (
                <div className="overflow-hidden rounded-lg border">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b bg-muted/50 text-start">
                                <th className="px-3 py-2 text-right font-medium">نام</th>
                                <th className="px-3 py-2 text-right font-medium">دسته</th>
                                <th className="px-3 py-2 text-right font-medium">نوع</th>
                                <th className="px-3 py-2 text-right font-medium">حجم</th>
                                <th className="px-3 py-2 text-right font-medium">تاریخ</th>
                            </tr>
                        </thead>
                        <tbody>
                            {files.map((node) => {
                                const dirPath = node.path.includes("/")
                                    ? node.path.slice(0, node.path.lastIndexOf("/"))
                                    : "—";

                                return (
                                    <tr
                                        key={node.path}
                                        className="cursor-pointer border-b last:border-b-0 hover:bg-muted/50"
                                        onClick={() => openPreview(node)}
                                    >
                                        <td className="px-3 py-2">
                                            <span className="inline-flex items-center gap-2">
                                                <IconFile className="size-4 shrink-0 text-muted-foreground" />
                                                {node.name}
                                            </span>
                                        </td>
                                        <td className="px-3 py-2 text-muted-foreground">
                                            {dirPath}
                                        </td>
                                        <td className="px-3 py-2 text-muted-foreground">
                                            {node.mime ?? "—"}
                                        </td>
                                        <td className="px-3 py-2 text-muted-foreground" dir="ltr">
                                            {node.size !== null ? formatBytes(node.size) : "—"}
                                        </td>
                                        <td className="px-3 py-2 text-muted-foreground">
                                            {node.modified_at
                                                ? toPersianDate(node.modified_at)
                                                : "—"}
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            ) : (
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                    {files.map((node, index) => (
                        <button
                            key={node.path}
                            type="button"
                            className="rounded-lg border p-3 text-start hover:border-primary/40 hover:bg-muted/30"
                            onClick={() => openPreview(node)}
                        >
                            <FileThumbnail
                                file={{ name: node.name, type: node.mime ?? guessMime(node.name) }}
                                previewImageUrl={tempFileUrl(
                                    employee.personnel_code,
                                    node.path,
                                )}
                                className="mx-auto h-24 w-full rounded"
                                previewClassName="aspect-[4/3]"
                            />
                            <p className="mt-2 truncate text-sm">{node.name}</p>
                            <p className="text-xs text-muted-foreground" dir="ltr">
                                {node.size !== null ? formatBytes(node.size) : ""}
                            </p>
                        </button>
                    ))}
                </div>
            )}

            <DocumentPreviewLightbox
                documents={lightboxDocs}
                currentIndex={previewIndex ?? 0}
                open={previewIndex !== null}
                onClose={() => setPreviewIndex(null)}
                onNavigate={setPreviewIndex}
            />
        </div>
    );
}
