import * as React from "react";
import {
    useReactTable,
    getCoreRowModel,
    flexRender,
    type ColumnDef,
    type RowSelectionState,
} from "@tanstack/react-table";
import { IconDownload, IconLoader2, IconTrash } from "@tabler/icons-react";

import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Table, TableHeader, TableBody, TableHead, TableRow, TableCell } from "@/components/ui/table";
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
    selectedIds: number[];
    onSelectionChange: (ids: number[]) => void;
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
    selectedIds,
    onSelectionChange,
}: DocumentTableProps) {
    const [rowSelection, setRowSelection] = React.useState<RowSelectionState>(
        () => {
            const selection: RowSelectionState = {};
            documents.forEach((doc, index) => {
                if (selectedIds.includes(doc.id)) {
                    selection[index] = true;
                }
            });
            return selection;
        },
    );

    React.useEffect(() => {
        const selection: RowSelectionState = {};
        documents.forEach((doc, index) => {
            if (selectedIds.includes(doc.id)) {
                selection[index] = true;
            }
        });
        setRowSelection(selection);
    }, [selectedIds, documents]);

    const handleSelectionChange = React.useCallback(
        (updater: RowSelectionState | ((old: RowSelectionState) => RowSelectionState)) => {
            const newSelection =
                typeof updater === "function" ? updater(rowSelection) : updater;
            setRowSelection(newSelection);

            const selectedDocuments = Object.keys(newSelection)
                .filter((key) => newSelection[key])
                .map((key) => documents[parseInt(key)]?.id)
                .filter((id): id is number => id !== undefined);
            onSelectionChange(selectedDocuments);
        },
        [rowSelection, documents, onSelectionChange],
    );

    const columns = React.useMemo<ColumnDef<Document>[]>(
        () => [
            {
                id: "select",
                header: ({ table }) => (
                    <Checkbox
                        checked={table.getIsAllPageRowsSelected()}
                        indeterminate={table.getIsSomePageRowsSelected() && !table.getIsAllPageRowsSelected()}
                        onCheckedChange={(value) =>
                            table.toggleAllPageRowsSelected(!!value)
                        }
                        aria-label="Select all"
                    />
                ),
                cell: ({ row }) => (
                    <Checkbox
                        checked={row.getIsSelected()}
                        onCheckedChange={(value) => row.toggleSelected(!!value)}
                        onClick={(e) => e.stopPropagation()}
                        aria-label="Select row"
                    />
                ),
                size: 40,
            },
            {
                accessorKey: "category.name",
                header: "دسته‌بندی",
                cell: ({ row }) => (
                    <span className="text-sm">
                        {row.original.category?.name ?? "سایر"}
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
        onRowSelectionChange: handleSelectionChange,
        state: {
            rowSelection,
        },
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
                            const isSelect = header.id === "select";
                            return (
                                <TableHead
                                    key={header.id}
                                    className={
                                        isActions
                                            ? "sticky inset-e-0 z-20 bg-card shadow-[-2px_0_5px_-2px_rgba(0,0,0,0.1)] cursor-default"
                                            : isSelect
                                                ? "w-10"
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
                        className="cursor-pointer group/row"
                        onClick={() => onPreview(row.original)}
                    >
                        {row.getVisibleCells().map((cell) => {
                            const isActions = cell.column.id === "actions";
                            const isSelect = cell.column.id === "select";
                            return (
                                <TableCell
                                    key={cell.id}
                                    className={
                                        isActions
                                            ? "sticky inset-e-0 z-10 bg-card shadow-[-2px_0_5px_-2px_rgba(0,0,0,0.1)] cursor-default"
                                            : isSelect
                                                ? "w-10"
                                                : undefined
                                    }
                                    onClick={
                                        isActions || isSelect
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
