import { useState } from "react";
import {
    flexRender,
    stockFeatures,
    useTable,
    type ColumnDef,
    type PaginationState,
    type Row,
    type StockFeatures,
} from "@tanstack/react-table";
import { IconListDetails, IconPencil, IconPlus } from "@tabler/icons-react";

import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import { ConfirmDeleteButton } from "@/components/ui/confirm-delete-button";
import { Skeleton } from "@/components/ui/skeleton";
import { Switch } from "@/components/ui/switch";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/components/ui/table";
import { DataTablePagination } from "@/components/data-table";
import { EmptyState } from "@/components/layout";
import { ErrorSection } from "@/components/layout";
import { usePermission } from "@/features/auth/components/permission-guard";
import { groupDisplayName } from "@/features/form-options/groups";
import {
    useAdminFormOptionGroups,
    useAdminFormOptions,
    useFormOptionsAdmin,
} from "@/features/form-options/hooks/use-form-options";
import type { FormOption } from "@/features/form-options/types";
import { PAGINATION } from "@/lib/constants";
import { PERMISSIONS } from "@/lib/permissions";
import { OptionEditorDialog } from "./option-editor-dialog";
import {
    emptyForm,
    formFromOption,
    type OptionFormState,
    type OptionFormActions,
} from "./form-option-form-state";
import {
    buildUpdatePayload,
    saveDisabled,
} from "./form-option-form-state";

export function FormOptionsSection() {
    const canManage = usePermission([PERMISSIONS.FORM_OPTIONS_MANAGE]);
    const admin = useFormOptionsAdmin();

    const [selectedGroup, setSelectedGroup] = useState<string>("");
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editing, setEditing] = useState<FormOption | null>(null);
    const [form, setForm] = useState<OptionFormState>(emptyForm(0));
    const [pagination, setPagination] = useState<PaginationState>({
        pageIndex: 0,
        pageSize: PAGINATION.DEFAULT_PAGE_SIZE,
    });

    const { data: groups = [], isLoading: groupsLoading } =
        useAdminFormOptionGroups();
    const { data, isLoading, isError, refetch } = useAdminFormOptions(
        selectedGroup || undefined,
        pagination.pageIndex + 1,
        pagination.pageSize,
    );

    const rows = data?.data ?? [];
    const meta = data?.meta;
    const activeGroup = groups.find((g) => g.group === selectedGroup);

    const formActions: OptionFormActions = {
        patch: (patch) => setForm((f) => ({ ...f, ...patch })),
    };

    const openCreate = () => {
        if (!selectedGroup) return;
        const nextSortOrder =
            rows.reduce((max, o) => Math.max(max, o.sort_order), 0) + 1;
        setEditing(null);
        setForm(emptyForm(nextSortOrder));
        setDialogOpen(true);
    };

    const openEdit = (option: FormOption) => {
        setEditing(option);
        setForm(formFromOption(option));
        setDialogOpen(true);
    };

    const handleSave = () => {
        const payload = buildUpdatePayload(form);
        if (editing) {
            admin.update.mutate({ id: editing.id, data: payload });
        } else {
            admin.create.mutate({
                group: selectedGroup,
                value: form.value.trim(),
                ...payload,
            });
        }
        setDialogOpen(false);
    };

    const groupLabel = selectedGroup
        ? groupDisplayName(selectedGroup, activeGroup?.label ?? undefined)
        : "";

    const columns: ColumnDef<StockFeatures, FormOption>[] = [
        {
            accessorKey: "label",
            header: "عنوان",
            cell: ({ row }: { row: Row<StockFeatures, FormOption> }) => (
                <span className="font-medium">{row.original.label}</span>
            ),
        },
        {
            accessorKey: "value",
            header: "مقدار",
            cell: ({ row }: { row: Row<StockFeatures, FormOption> }) => (
                <span dir="ltr" className="text-muted-foreground">
                    {row.original.value}
                </span>
            ),
        },
        {
            accessorKey: "parent_value",
            header: "وابسته به",
            cell: ({ row }: { row: Row<StockFeatures, FormOption> }) => (
                <span className="text-muted-foreground">
                    {row.original.parent_value || <span>—</span>}
                </span>
            ),
        },
        {
            accessorKey: "meta",
            header: "متادیتا",
            enableSorting: false,
            cell: ({ row }: { row: Row<StockFeatures, FormOption> }) => (
                <span
                    dir="ltr"
                    className="block max-w-[14rem] truncate text-muted-foreground"
                    title={
                        row.original.meta
                            ? JSON.stringify(row.original.meta)
                            : undefined
                    }
                >
                    {row.original.meta
                        ? JSON.stringify(row.original.meta)
                        : "—"}
                </span>
            ),
        },
        {
            accessorKey: "sort_order",
            header: "ترتیب",
            cell: ({ row }: { row: Row<StockFeatures, FormOption> }) => (
                <span className="flex justify-center">
                    {row.original.sort_order}
                </span>
            ),
        },
        {
            accessorKey: "is_active",
            header: "فعال",
            enableSorting: false,
            cell: ({ row }: { row: Row<StockFeatures, FormOption> }) =>
                canManage ? (
                    <div className="flex justify-center">
                        <Switch
                            size="sm"
                            checked={row.original.is_active}
                            onCheckedChange={() =>
                                admin.toggle.mutate(row.original.id)
                            }
                            disabled={admin.toggle.isPending}
                        />
                    </div>
                ) : (
                    <div className="flex justify-center">
                        <Badge
                            variant={
                                row.original.is_active ? "default" : "secondary"
                            }
                        >
                            {row.original.is_active ? "فعال" : "غیرفعال"}
                        </Badge>
                    </div>
                ),
        },
        ...(canManage
            ? [
                  {
                      id: "actions",
                      header: "",
                      enableSorting: false,
                      cell: ({
                          row,
                      }: {
                          row: Row<StockFeatures, FormOption>;
                      }) => (
                          <div className="flex items-center gap-1">
                              <Button
                                  type="button"
                                  variant="ghost"
                                  size="icon-sm"
                                  onClick={() => openEdit(row.original)}
                              >
                                  <IconPencil className="size-4" />
                              </Button>
                              <ConfirmDeleteButton
                                  iconOnly
                                  onConfirm={() =>
                                      admin.remove.mutate(row.original.id)
                                  }
                                  isPending={admin.remove.isPending}
                              />
                          </div>
                      ),
                  } satisfies ColumnDef<StockFeatures, FormOption>,
              ]
            : []),
    ];

    const table = useTable({
        features: stockFeatures,
        data: rows,
        columns,
        state: { pagination },
        onPaginationChange: setPagination,
        manualPagination: true,
        pageCount: meta?.last_page ?? 1,
    });

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <IconListDetails className="size-5" />
                    گزینه‌های فرم
                </CardTitle>
                <CardDescription>
                    مدیریت گزینه‌های بازشو (جنسیت، وضعیت تأهل، استان و شهر و…) در
                    فرم‌ها
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <Select
                        value={selectedGroup || null}
                        onValueChange={(value: string | null) => {
                            if (!value) return;
                            setSelectedGroup(value);
                            setPagination((p) => ({ ...p, pageIndex: 0 }));
                        }}
                        itemToStringLabel={(val) =>
                            groupDisplayName(
                                val as string,
                                groups.find((g) => g.group === val)?.label ??
                                    undefined,
                            )
                        }
                    >
                        <SelectTrigger className="w-full sm:w-64">
                            <SelectValue
                                placeholder={
                                    groupsLoading
                                        ? "در حال بارگذاری…"
                                        : "انتخاب گروه"
                                }
                            />
                        </SelectTrigger>
                        <SelectContent>
                            {groups.map((group) => (
                                <SelectItem
                                    key={group.group}
                                    value={group.group}
                                >
                                    {groupDisplayName(
                                        group.group,
                                        group.label ?? undefined,
                                    )}
                                    ({group.count.toLocaleString("fa-IR")})
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    {canManage && (
                        <Button
                            type="button"
                            onClick={openCreate}
                            disabled={
                                !selectedGroup || admin.create.isPending
                            }
                        >
                            <IconPlus className="size-4" />
                            افزودن گزینه
                        </Button>
                    )}
                </div>

                {isLoading ? (
                    <div className="mt-6 space-y-2">
                        {Array.from({ length: 5 }).map((_, i) => (
                            <Skeleton key={i} className="h-10 w-full" />
                        ))}
                    </div>
                ) : isError ? (
                    <div className="mt-6">
                        <ErrorSection onRetry={() => refetch()} />
                    </div>
                ) : (
                    <>
                        <div className="mt-6 overflow-x-auto rounded-lg border">
                            <Table>
                                <TableHeader>
                                    {table
                                        .getHeaderGroups()
                                        .map((headerGroup) => (
                                            <TableRow key={headerGroup.id}>
                                                {headerGroup.headers.map(
                                                    (header) => (
                                                        <TableHead
                                                            key={header.id}
                                                        >
                                                            {header.isPlaceholder
                                                                ? null
                                                                : flexRender(
                                                                      header
                                                                          .column
                                                                          .columnDef
                                                                          .header,
                                                                      header.getContext(),
                                                                  )}
                                                        </TableHead>
                                                    ),
                                                )}
                                            </TableRow>
                                        ))}
                                </TableHeader>
                                <TableBody>
                                    {table.getRowModel().rows.length ? (
                                        table
                                            .getRowModel()
                                            .rows.map((row) => (
                                                <TableRow key={row.id}>
                                                    {row
                                                        .getVisibleCells()
                                                        .map((cell) => (
                                                            <TableCell
                                                                key={cell.id}
                                                            >
                                                                {flexRender(
                                                                    cell.column
                                                                        .columnDef
                                                                        .cell,
                                                                    cell.getContext(),
                                                                )}
                                                            </TableCell>
                                                        ))}
                                                </TableRow>
                                            ))
                                    ) : (
                                        <TableRow>
                                            <TableCell
                                                colSpan={columns.length}
                                            >
                                                <EmptyState
                                                    icon={IconListDetails}
                                                    message="هنوز گزینه‌ای در این گروه ثبت نشده است"
                                                />
                                            </TableCell>
                                        </TableRow>
                                    )}
                                </TableBody>
                            </Table>
                        </div>
                        {meta && meta.total > 0 && (
                            <div className="mt-4 border-t pt-3">
                                <DataTablePagination
                                    table={table}
                                    meta={meta}
                                />
                            </div>
                        )}
                    </>
                )}
            </CardContent>

            <OptionEditorDialog
                open={dialogOpen}
                onOpenChange={setDialogOpen}
                editing={editing}
                groupLabel={groupLabel}
                selectedGroup={selectedGroup}
                form={form}
                actions={formActions}
                saveDisabled={saveDisabled(editing !== null, form)}
                isPending={admin.create.isPending || admin.update.isPending}
                onSave={handleSave}
            />
        </Card>
    );
}