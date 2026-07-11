import * as React from "react";
import {
    useReactTable,
    getCoreRowModel,
    flexRender,
    type ColumnDef,
} from "@tanstack/react-table";
import { IconDownload, IconLoader2, IconTrash } from "@tabler/icons-react";

import { Button } from "@/components/ui/button";
import { ConfirmDeleteActions } from "./confirm-delete-actions";
import { DocumentFileCell } from "./document-file-cell";
import { toPersianDate } from "@/lib/date-format";
import type { Document } from "@/features/documents/types";

type DocumentTableProps = {
    documents: Document[];
    onPreview: (doc: Document) => void;
    onDownload: (doc: Document) => void;
    confirmingDeleteId: number | null;
    deletingIds: Set<number>;
    onStartDelete: (id: number) => void;
    onConfirmDelete: (id: number) => void;
    onCancelDelete: () => void;
};

export function DocumentTable({
    documents,
    onPreview,
    onDownload,
    confirmingDeleteId,
    deletingIds,
    onStartDelete,
    onConfirmDelete,
    onCancelDelete,
}: DocumentTableProps) {
    const columns = React.useMemo<ColumnDef<Document>[]>(
        () => [
            {
                accessorKey: "category.name",
                header: "دسته‌بندی",
                cell: ({ row }) => (
                    <span className="text-sm">
                        {row.original.category?.name ?? "—"}
                    </span>
                ),
            },
            {
                accessorKey: "original_name",
                header: "فایل",
                cell: ({ row }) => <DocumentFileCell doc={row.original} />,
            },
            {
                accessorKey: "file_size_formatted",
                header: "اندازه",
                cell: ({ row }) => {
                    const fileSize =
                        row.original.file_size_formatted.split(" ");
                    return (
                        <>
                            <span className="text-sm text-muted-foreground whitespace-nowrap">
                                {fileSize[0]}
                            </span>
                            <span className="text-[10px] text-muted-foreground/60 px-1 whitespace-nowrap">
                                {fileSize[1]}
                            </span>
                        </>
                    );
                },
            },
            {
                accessorKey: "created_at",
                header: "تاریخ",
                cell: ({ row }) => (
                    <span className="text-sm text-muted-foreground whitespace-nowrap">
                        {toPersianDate(row.original.created_at)}
                    </span>
                ),
            },
            {
                accessorKey: "uploaded_by",
                header: "آپلود توسط",
                cell: ({ row }) => (
                    <span className="text-sm text-muted-foreground">
                        {row.original.uploaded_by ?? "—"}
                    </span>
                ),
            },
            {
                id: "actions",
                header: "",
                cell: ({ row }) => {
                    const doc = row.original;
                    const isDeleting = deletingIds.has(doc.id);
                    const isConfirming = confirmingDeleteId === doc.id;

                    return (
                        <div className="flex items-center justify-end gap-1">
                            <Button
                                variant="ghost"
                                size="icon-xs"
                                onClick={(e) => {
                                    e.stopPropagation();
                                    onDownload(doc);
                                }}
                                disabled={!doc.url}
                                aria-label={`Download ${doc.original_name}`}
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
                                    aria-label={`Delete ${doc.original_name}`}
                                >
                                    {isDeleting ? (
                                        <IconLoader2 className="size-3.5 animate-spin" />
                                    ) : (
                                        <IconTrash className="size-3.5" />
                                    )}
                                </Button>
                            )}
                        </div>
                    );
                },
            },
        ],
        [
            confirmingDeleteId,
            deletingIds,
            onDownload,
            onStartDelete,
            onConfirmDelete,
            onCancelDelete,
        ],
    );

    const table = useReactTable({
        data: documents,
        columns,
        getCoreRowModel: getCoreRowModel(),
    });

    return (
        <div className="relative w-full overflow-auto rounded-lg border">
            <table className="w-full caption-bottom text-sm">
                <thead>
                    {table.getHeaderGroups().map((headerGroup) => (
                        <tr
                            key={headerGroup.id}
                            className="border-b transition-colors"
                        >
                            {headerGroup.headers.map((header) => {
                                const isActions = header.id === "actions";
                                return (
                                    <th
                                        key={header.id}
                                        className={
                                            isActions
                                                ? "sticky inset-e-0 z-20 bg-card shadow-[-2px_0_5px_-2px_rgba(0,0,0,0.1)] h-10 px-3 text-start text-xs font-medium text-muted-foreground cursor-default"
                                                : "h-10 px-3 text-start text-xs font-medium text-muted-foreground"
                                        }
                                    >
                                        {header.isPlaceholder
                                            ? null
                                            : flexRender(
                                                  header.column.columnDef
                                                      .header,
                                                  header.getContext(),
                                              )}
                                    </th>
                                );
                            })}
                        </tr>
                    ))}
                </thead>
                <tbody>
                    {table.getRowModel().rows.map((row) => (
                        <tr
                            key={row.id}
                            className="cursor-pointer border-b transition-colors hover:bg-muted/50 last:border-b-0"
                            onClick={() => onPreview(row.original)}
                        >
                            {row.getVisibleCells().map((cell) => {
                                const isActions = cell.column.id === "actions";
                                return (
                                    <td
                                        key={cell.id}
                                        className={
                                            isActions
                                                ? "sticky inset-e-0 z-10 bg-card shadow-[-2px_0_5px_-2px_rgba(0,0,0,0.1)] p-3 cursor-default"
                                                : "p-3"
                                        }
                                        onClick={
                                            isActions
                                                ? (e) => e.stopPropagation()
                                                : undefined
                                        }
                                    >
                                        {flexRender(
                                            cell.column.columnDef.cell,
                                            cell.getContext(),
                                        )}
                                    </td>
                                );
                            })}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
