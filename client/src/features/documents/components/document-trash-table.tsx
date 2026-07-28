import * as React from "react";
import {
    useReactTable,
    getCoreRowModel,
    flexRender,
    type ColumnDef,
} from "@tanstack/react-table";
import { IconArrowBackUp, IconLoader2, IconTrash } from "@tabler/icons-react";

import { Button } from "@/components/ui/button";
import { Table, TableHeader, TableBody, TableHead, TableRow, TableCell } from "@/components/ui/table";
import { ConfirmDeleteActions } from "./confirm-delete-actions";
import { DocumentFileCell } from "./document-file-cell";
import { toPersianDate } from "@/lib/date-format";
import { getDocOriginalName, getDocFileSizeFormatted } from "@/features/documents/types";
import type { Document } from "@/features/documents/types";

type DocumentTrashTableProps = {
    documents: Document[];
    confirmingDeleteId: number | null;
    restoringIds: Set<number>;
    forceDeletingIds: Set<number>;
    onRestore: (id: number) => void;
    onStartForceDelete: (id: number) => void;
    onConfirmForceDelete: (id: number) => void;
    onCancelForceDelete: () => void;
};

export function DocumentTrashTable({
    documents,
    confirmingDeleteId,
    restoringIds,
    forceDeletingIds,
    onRestore,
    onStartForceDelete,
    onConfirmForceDelete,
    onCancelForceDelete,
}: DocumentTrashTableProps) {
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
                accessorKey: "current_revision.original_name",
                header: "فایل",
                cell: ({ row }) => <DocumentFileCell doc={row.original} />,
            },
            {
                id: "file_size",
                header: "اندازه",
                cell: ({ row }) => {
                    const formatted = getDocFileSizeFormatted(row.original);
                    const parts = formatted.split(" ");
                    return (
                        <>
                            <span className="text-sm text-muted-foreground whitespace-nowrap">
                                {parts[0]}
                            </span>
                            <span className="text-[10px] text-muted-foreground/60 px-1 whitespace-nowrap">
                                {parts[1]}
                            </span>
                        </>
                    );
                },
            },
            {
                accessorKey: "deleted_at",
                header: "تاریخ حذف",
                cell: ({ row }) => (
                    <span className="text-sm text-muted-foreground whitespace-nowrap">
                        {toPersianDate(row.original.deleted_at)}
                    </span>
                ),
            },
            {
                id: "actions",
                header: "",
                cell: ({ row }) => {
                    const doc = row.original;
                    const isRestoring = restoringIds.has(doc.id);
                    const isForceDeleting = forceDeletingIds.has(doc.id);
                    const isConfirming = confirmingDeleteId === doc.id;

                    return (
                        <div className="flex items-center justify-end gap-1">
                            <Button
                                variant="ghost"
                                size="icon-xs"
                                onClick={(e) => {
                                    e.stopPropagation();
                                    onRestore(doc.id);
                                }}
                                disabled={isRestoring}
                                aria-label={`Restore ${getDocOriginalName(doc)}`}
                            >
                                {isRestoring ? (
                                    <IconLoader2 className="size-3.5 animate-spin" />
                                ) : (
                                    <IconArrowBackUp className="size-3.5" />
                                )}
                            </Button>
                            {isConfirming ? (
                                <ConfirmDeleteActions
                                    docId={doc.id}
                                    isPending={isForceDeleting}
                                    onConfirm={onConfirmForceDelete}
                                    onCancel={onCancelForceDelete}
                                />
                            ) : (
                                <Button
                                    variant="ghost"
                                    size="icon-xs"
                                    onClick={(e) => {
                                        e.stopPropagation();
                                        onStartForceDelete(doc.id);
                                    }}
                                    disabled={isForceDeleting}
                                    aria-label={`Permanently delete ${getDocOriginalName(doc)}`}
                                >
                                    {isForceDeleting ? (
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
            restoringIds,
            forceDeletingIds,
            onRestore,
            onStartForceDelete,
            onConfirmForceDelete,
            onCancelForceDelete,
        ],
    );

    const table = useReactTable({
        data: documents,
        columns,
        getCoreRowModel: getCoreRowModel(),
    });

    return (
        <Table>
            <TableHeader>
                {table.getHeaderGroups().map((headerGroup) => (
                    <TableRow
                        key={headerGroup.id}
                        className="group/row"
                    >
                        {headerGroup.headers.map((header) => {
                            const isActions = header.id === "actions";
                            return (
                                <TableHead
                                    key={header.id}
                                    className={
                                        isActions
                                            ? "sticky inset-e-0 z-20 bg-card shadow-[-2px_0_5px_-2px_rgba(0,0,0,0.1)] cursor-default"
                                            : undefined
                                    }
                                >
                                    {header.isPlaceholder
                                        ? null
                                        : flexRender(
                                              header.column.columnDef.header,
                                              header.getContext(),
                                          )}
                                </TableHead>
                            );
                        })}
                    </TableRow>
                ))}
            </TableHeader>
            <TableBody>
                {table.getRowModel().rows.map((row) => (
                    <TableRow
                        key={row.id}
                        className="group/row"
                    >
                        {row.getVisibleCells().map((cell) => {
                            const isActions = cell.column.id === "actions";
                            return (
                                <TableCell
                                    key={cell.id}
                                    className={
                                        isActions
                                            ? "sticky inset-e-0 z-10 bg-card shadow-[-2px_0_5px_-2px_rgba(0,0,0,0.1)] cursor-default"
                                            : undefined
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
                                </TableCell>
                            );
                        })}
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}
