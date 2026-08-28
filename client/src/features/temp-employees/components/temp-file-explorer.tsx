import * as React from "react";
import { useQuery } from "@tanstack/react-query";
import {
    IconChevronDown,
    IconChevronLeft,
    IconChevronRight,
    IconChevronsLeft,
    IconChevronsRight,
    IconDownload,
    IconFolder,
    IconFolderOpen,
    IconLayoutGrid,
    IconLayoutList,
    IconListTree,
    IconSearch,
} from "@tabler/icons-react";

import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { FileThumbnail } from "@/components/ui/file-thumbnail";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { DocumentPreviewLightbox } from "@/features/documents/components/document-preview-lightbox";
import type { Document } from "@/features/documents/types";
import { fetchTempEmployeeTree, tempFileDownloadUrl, tempFileUrl } from "../api";
import type { TempEmployee, TempFileNode } from "../types";
import { getFileColorClasses, getFileIcon, getFileTypeLabel, formatBytes } from "@/lib/file-utils";
import { toPersianDate } from "@/lib/date-format";
import { PAGINATION } from "@/lib/constants";
import { cn, getPageNumbers } from "@/lib/utils";

function downloadFile(url: string) {
    const a = document.createElement("a");
    a.href = url;
    a.rel = "noopener";
    document.body.appendChild(a);
    a.click();
    a.remove();
}

/**
 * Lightweight subsequence fuzzy matcher: every character of `query` must
 * appear in `text` (case-insensitive) in order, not necessarily contiguously.
 * Returns null when there is no match, otherwise a score where lower is better
 * (prefers consecutive clusters and earlier occurrences).
 */
function fuzzyScore(query: string, text: string): number | null {
    const q = query.toLowerCase();
    const t = text.toLowerCase();
    if (q === "") return 0;
    if (!t.includes(q[0])) return null;

    let qi = 0;
    let score = 0;
    let last = -1;

    for (let ti = 0; ti < t.length && qi < q.length; ti++) {
        if (t[ti] === q[qi]) {
            score += qi > 0 && ti === last + 1 ? -1 : 2;
            last = ti;
            qi++;
        }
    }

    if (qi < q.length) return null;
    return score;
}

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
        download_url: tempFileDownloadUrl(employee.personnel_code, node.path),
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
    nodes,
    expanded,
    toggle,
    onPreview,
    onDownload,
}: {
    node: TempFileNode;
    depth: number;
    nodes: TempFileNode[];
    expanded: Set<string>;
    toggle: (path: string) => void;
    onPreview: (node: TempFileNode) => void;
    onDownload: (node: TempFileNode) => void;
}) {
    const isOpen = expanded.has(node.path);

    if (node.type === "dir") {
        const children = nodes.filter(
            (f) =>
                f.path.startsWith(`${node.path}/`) &&
                !f.path.slice(node.path.length + 1).includes("/"),
        );

        // Recursive file count for this directory.
        const fileCount = nodes.filter((f) =>
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
                            nodes={nodes}
                            expanded={expanded}
                            toggle={toggle}
                            onPreview={onPreview}
                            onDownload={onDownload}
                        />
                    ))}
            </div>
        );
    }

    return (
        <div
            className="group flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-start text-sm hover:bg-muted"
            style={{ paddingInlineStart: `${8 + depth * 18 + 20}px` }}
        >
            <button
                type="button"
                className="flex min-w-0 flex-1 items-center gap-2"
                onClick={() => onPreview(node)}
            >
                <span
                    className={cn(
                        "flex size-6 shrink-0 items-center justify-center rounded",
                        getFileColorClasses(node.mime ?? guessMime(node.name)),
                    )}
                >
                    {getFileIcon(node.mime ?? guessMime(node.name), "size-3.5")}
                </span>
                <span className="truncate">{node.name}</span>
            </button>
            {node.size !== null && (
                <span className="text-xs text-muted-foreground" dir="ltr">
                    {formatBytes(node.size)}
                </span>
            )}
            <button
                type="button"
                title="دانلود"
                onClick={() => onDownload(node)}
                className="rounded p-1 text-muted-foreground opacity-0 hover:bg-muted hover:text-foreground focus:opacity-100 group-hover:opacity-100"
            >
                <IconDownload className="size-4" />
            </button>
        </div>
    );
}

export function TempFileExplorer({ employee }: { employee: TempEmployee }) {
    const [mode, setMode] = React.useState<ExplorerMode>("tree");
    const [expanded, setExpanded] = React.useState<Set<string>>(new Set());
    const [previewIndex, setPreviewIndex] = React.useState<number | null>(null);
    const [page, setPage] = React.useState(1);
    const [pageSize, setPageSize] = React.useState<number>(PAGINATION.DEFAULT_PAGE_SIZE);
    const [searchInput, setSearchInput] = React.useState("");
    const [search, setSearch] = React.useState("");

    // Debounce the fuzzy search box into the active query.
    React.useEffect(() => {
        const timer = setTimeout(() => setSearch(searchInput.trim()), 250);
        return () => clearTimeout(timer);
    }, [searchInput]);

    const { data: tree, isLoading } = useTreeQuery(employee.personnel_code);

    const filteredNodes = React.useMemo(() => {
        const raw = tree ?? [];
        if (search === "") return raw;

        const scored = raw
            .map((node) => {
                const name = fuzzyScore(search, node.name) ?? Infinity;
                const path = fuzzyScore(search, node.path) ?? Infinity;
                return { node, score: Math.min(name, path) };
            })
            .filter((entry) => entry.score !== Infinity)
            .sort((a, b) => a.score - b.score)
            .map((entry) => entry.node);

        // Keep ancestor directories of any match so matched files stay
        // reachable (and navigable) in the tree view.
        const matchPaths = new Set(scored.map((n) => n.path));
        return raw.filter((candidate) => {
            if (matchPaths.has(candidate.path)) return true;
            if (candidate.type !== "dir") return false;
            return scored.some((match) =>
                match.path.startsWith(`${candidate.path}/`),
            );
        });
    }, [tree, search]);

    const files = React.useMemo(
        () => filteredNodes.filter((n) => n.type === "file"),
        [filteredNodes],
    );

    // Reset pagination whenever the employee, search, or file set changes.
    React.useEffect(() => {
        setPage(1);
    }, [employee.personnel_code, files]);

    const lightboxDocs = React.useMemo(
        () => files.map((node, index) => toLightboxDoc(employee, node, index)),
        [employee, files],
    );

    const totalPages = Math.max(1, Math.ceil(files.length / pageSize));
    const safePage = Math.min(page, totalPages);
    const pageFiles = React.useMemo(
        () => files.slice((safePage - 1) * pageSize, safePage * pageSize),
        [files, safePage, pageSize],
    );

    function changePageSize(value: string | null) {
        const parsed = Number(value);
        const options: number[] = [...PAGINATION.PAGE_SIZE_OPTIONS];
        if (!options.includes(parsed)) return;
        setPageSize(parsed);
        setPage(1);
    }

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

    function download(node: TempFileNode) {
        downloadFile(tempFileDownloadUrl(employee.personnel_code, node.path));
    }

    const pagination = files.length > pageSize && (
        <div className="flex items-center justify-between gap-2 pt-3">
            <div className="flex items-center gap-2">
                <Select
                    value={`${pageSize}`}
                    onValueChange={changePageSize}
                >
                    <SelectTrigger className="h-8 w-18">
                        <SelectValue placeholder={pageSize} />
                    </SelectTrigger>
                    <SelectContent side="top">
                        {PAGINATION.PAGE_SIZE_OPTIONS.map((size) => (
                            <SelectItem key={size} value={`${size}`}>
                                {size}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <span className="text-xs text-muted-foreground">
                    ({files.length.toLocaleString("fa-IR")} فایل)
                </span>
            </div>
            <div className="flex items-center gap-1">
                <Button
                    type="button"
                    variant="outline"
                    className="size-8 p-0"
                    onClick={() => setPage(1)}
                    disabled={safePage <= 1}
                    title="صفحه اول"
                >
                    <IconChevronsRight className="size-4" />
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    className="size-8 p-0"
                    onClick={() => setPage(safePage - 1)}
                    disabled={safePage <= 1}
                    title="قبلی"
                >
                    <IconChevronRight className="size-4" />
                </Button>

                {getPageNumbers(safePage, totalPages).map((pn, index) => (
                    <div key={`${pn}-${index}`} className="flex items-center">
                        {pn === "..." ? (
                            <span className="px-1 text-sm text-muted-foreground">
                                ...
                            </span>
                        ) : (
                            <Button
                                type="button"
                                variant={safePage === pn ? "default" : "outline"}
                                className="h-8 min-w-8 px-2"
                                onClick={() => setPage(pn as number)}
                            >
                                {pn.toLocaleString("fa-IR")}
                            </Button>
                        )}
                    </div>
                ))}

                <Button
                    type="button"
                    variant="outline"
                    className="size-8 p-0"
                    onClick={() => setPage(safePage + 1)}
                    disabled={safePage >= totalPages}
                    title="بعدی"
                >
                    <IconChevronLeft className="size-4" />
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    className="size-8 p-0"
                    onClick={() => setPage(totalPages)}
                    disabled={safePage >= totalPages}
                    title="صفحه آخر"
                >
                    <IconChevronsLeft className="size-4" />
                </Button>
            </div>
        </div>
    );

    return (
        <div className="space-y-3">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
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

            <div className="relative">
                <IconSearch className="absolute top-1/2 size-4 -translate-y-1/2 text-muted-foreground start-2.5" />
                <Input
                    value={searchInput}
                    onChange={(e) => setSearchInput(e.target.value)}
                    placeholder="جستجو در فایل‌ها…"
                    className="ps-8"
                />
            </div>

            {isLoading ? (
                <p className="py-6 text-center text-sm text-muted-foreground">
                    در حال بارگذاری…
                </p>
            ) : files.length === 0 ? (
                <p className="py-6 text-center text-sm text-muted-foreground">
                    {search !== ""
                        ? "فایلی مطابق جستجو یافت نشد."
                        : "فایلی برای این کد پرسنلی یافت نشد."}
                </p>
            ) : mode === "tree" ? (
                <div className="rounded-lg border p-2">
                    {/* Roots: entries whose path has no slash. */}
                    {filteredNodes
                        .filter((n) => !n.path.includes("/"))
                        .map((root) => (
                            <TreeRow
                                key={root.path}
                                node={root}
                                depth={0}
                                nodes={filteredNodes}
                                expanded={expanded}
                                toggle={toggle}
                                onPreview={openPreview}
                                onDownload={download}
                            />
                        ))}
                </div>
            ) : mode === "table" ? (
                <div>
                    <div className="overflow-hidden rounded-lg border">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b bg-muted/50 text-start">
                                    <th className="px-3 py-2 text-right font-medium">نام</th>
                                    <th className="px-3 py-2 text-right font-medium">دسته</th>
                                    <th className="px-3 py-2 text-right font-medium">نوع</th>
                                    <th className="px-3 py-2 text-right font-medium">حجم</th>
                                    <th className="px-3 py-2 text-right font-medium">تاریخ</th>
                                    <th className="px-3 py-2 text-right font-medium"></th>
                                </tr>
                            </thead>
                            <tbody>
                                {pageFiles.map((node) => {
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
                                                    <span
                                                        className={cn(
                                                            "flex size-6 shrink-0 items-center justify-center rounded",
                                                            getFileColorClasses(node.mime ?? guessMime(node.name)),
                                                        )}
                                                    >
                                                        {getFileIcon(node.mime ?? guessMime(node.name), "size-3.5")}
                                                    </span>
                                                    <span className="truncate">{node.name}</span>
                                                </span>
                                            </td>
                                            <td className="px-3 py-2 text-muted-foreground">
                                                {dirPath}
                                            </td>
                                            <td className="px-3 py-2 text-muted-foreground">
                                                {node.mime ? (
                                                    <span dir="ltr">
                                                        {getFileTypeLabel(node.mime)}
                                                    </span>
                                                ) : (
                                                    "—"
                                                )}
                                            </td>
                                            <td className="px-3 py-2 text-muted-foreground" dir="ltr">
                                                {node.size !== null ? formatBytes(node.size) : "—"}
                                            </td>
                                            <td className="px-3 py-2 text-muted-foreground">
                                                {node.modified_at
                                                    ? toPersianDate(node.modified_at)
                                                    : "—"}
                                            </td>
                                            <td className="px-3 py-2 text-end">
                                                <button
                                                    type="button"
                                                    title="دانلود"
                                                    onClick={(e) => {
                                                        e.stopPropagation();
                                                        download(node);
                                                    }}
                                                    className="rounded p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                                                >
                                                    <IconDownload className="size-4" />
                                                </button>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                    {pagination}
                </div>
            ) : (
                <div>
                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                        {pageFiles.map((node) => (
                            <div
                                key={node.path}
                                className="group relative rounded-lg border p-3 text-start hover:border-primary/40 hover:bg-muted/30"
                            >
                                <button
                                    type="button"
                                    className="block w-full text-start"
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
                                <button
                                    type="button"
                                    title="دانلود"
                                    onClick={() => download(node)}
                                    className="absolute end-2 top-2 rounded-full bg-background/80 p-1.5 text-muted-foreground opacity-0 shadow-sm backdrop-blur transition-opacity hover:text-foreground group-hover:opacity-100"
                                >
                                    <IconDownload className="size-4" />
                                </button>
                            </div>
                        ))}
                    </div>
                    {pagination}
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
