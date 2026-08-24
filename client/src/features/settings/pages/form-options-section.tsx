import { Fragment, useState } from "react";
import {
    flexRender,
    stockFeatures,
    useTable,
    type ColumnDef,
    type PaginationState,
    type Row,
    type StockFeatures,
} from "@tanstack/react-table";
import {
    IconListDetails,
    IconPencil,
    IconPlus,
} from "@tabler/icons-react";

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
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { ResponsiveDialog } from "@/components/ui/responsive-dialog";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { Skeleton } from "@/components/ui/skeleton";
import { Switch } from "@/components/ui/switch";
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
    useFormOptionsByGroup,
} from "@/features/form-options/hooks/use-form-options";
import type { FormOption } from "@/features/form-options/types";
import { PAGINATION } from "@/lib/constants";
import { PERMISSIONS } from "@/lib/permissions";

type OptionFormState = {
    value: string;
    label: string;
    parent_value: string;
    group_label: string;
    meta: string;
    sort_order: string;
    is_active: boolean;
};

function emptyForm(nextSortOrder: number): OptionFormState {
    return {
        value: "",
        label: "",
        parent_value: "",
        group_label: "",
        meta: "",
        sort_order: String(nextSortOrder),
        is_active: true,
    };
}

function formFromOption(option: FormOption): OptionFormState {
    return {
        value: option.value,
        label: option.label,
        parent_value: option.parent_value ?? "",
        group_label: option.group_label ?? "",
        meta: option.meta ? JSON.stringify(option.meta, null, 2) : "",
        sort_order: String(option.sort_order),
        is_active: option.is_active,
    };
}

function parseMeta(raw: string): Record<string, unknown> | null {
    const trimmed = raw.trim();
    if (!trimmed) return null;
    try {
        const parsed = JSON.parse(trimmed);
        return parsed && typeof parsed === "object" && !Array.isArray(parsed)
            ? parsed
            : null;
    } catch {
        return null;
    }
}

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
    const {
        data,
        isLoading,
        isError,
        refetch,
    } = useAdminFormOptions(
        selectedGroup || undefined,
        pagination.pageIndex + 1,
        pagination.pageSize,
    );
    // A city option's parent must be an active province; the dialog swaps the
    // free-text parent field for a province selector. The province list is
    // session-cached, so fetching it unconditionally is cheap.
    const { data: provinceOptions = [] } = useFormOptionsByGroup("province");

    const rows = data?.data ?? [];
    const meta = data?.meta;

    const activeGroup = groups.find((g) => g.group === selectedGroup);

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
        const payload = {
            label: form.label.trim(),
            parent_value: form.parent_value.trim() || null,
            group_label: form.group_label.trim() || null,
            meta: parseMeta(form.meta),
            sort_order: Number(form.sort_order) || 0,
            is_active: form.is_active,
        };

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

    const saveDisabled = (editing ? false : !form.value.trim()) || !form.label.trim();

    const groupLabel = selectedGroup
        ? groupDisplayName(selectedGroup, activeGroup?.label ?? undefined)
        : "";

    const columns: ColumnDef<StockFeatures, FormOption>[] = [
        {
            accessorKey: "label",
            header: "عنوان",
            cell: ({
                row,
            }: {
                row: Row<StockFeatures, FormOption>;
            }) => (
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
                    مدیریت گزینه‌های بازشو (جنسیت، وضعیت تأهل، استان و شهر و…) در فرم‌ها
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
                                    {groupDisplayName(group.group, group.label ?? undefined)}
                                    ({group.count.toLocaleString("fa-IR")})
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    {canManage && (
                        <Button
                            type="button"
                            onClick={openCreate}
                            disabled={!selectedGroup || admin.create.isPending}
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
                                    {table.getHeaderGroups().map((headerGroup) => (
                                        <TableRow key={headerGroup.id}>
                                            {headerGroup.headers.map((header) => (
                                                <TableHead key={header.id}>
                                                    {header.isPlaceholder
                                                        ? null
                                                        : flexRender(
                                                              header.column
                                                                  .columnDef.header,
                                                              header.getContext(),
                                                          )}
                                                </TableHead>
                                            ))}
                                        </TableRow>
                                    ))}
                                </TableHeader>
                                <TableBody>
                                    {table.getRowModel().rows.length ? (
                                        table.getRowModel().rows.map((row) => (
                                            <TableRow key={row.id}>
                                                {row.getVisibleCells().map((cell) => (
                                                    <TableCell key={cell.id}>
                                                        {flexRender(
                                                            cell.column.columnDef.cell,
                                                            cell.getContext(),
                                                        )}
                                                    </TableCell>
                                                ))}
                                            </TableRow>
                                        ))
                                    ) : (
                                        <TableRow>
                                            <TableCell colSpan={columns.length}>
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
                                <DataTablePagination table={table} meta={meta} />
                            </div>
                        )}
                    </>
                )}
            </CardContent>

            <ResponsiveDialog
                open={dialogOpen}
                onOpenChange={setDialogOpen}
                title={editing ? "ویرایش گزینه" : "افزودن گزینه"}
                description={
                    editing
                        ? `گزینه «${groupLabel}»`
                        : `گزینه جدید در گروه «${groupLabel}»`
                }
                footer={
                    <Fragment>
                        <Button
                            variant="outline"
                            className="flex-1"
                            onClick={() => setDialogOpen(false)}
                        >
                            انصراف
                        </Button>
                        <Button
                            className="flex-1"
                            onClick={handleSave}
                            disabled={
                                saveDisabled ||
                                admin.create.isPending ||
                                admin.update.isPending
                            }
                        >
                            {editing ? "ذخیره تغییرات" : "افزودن"}
                        </Button>
                    </Fragment>
                }
            >
                <div className="grid gap-4 py-2">
                    {!editing && (
                        <div className="grid gap-2">
                            <Label>مقدار (ارسال به سرور)</Label>
                            <Input
                                dir="ltr"
                                value={form.value}
                                onChange={(e) =>
                                    setForm((f) => ({ ...f, value: e.target.value }))
                                }
                                placeholder="مثلاً single"
                            />
                        </div>
                    )}
                    <div className="grid gap-2">
                        <Label>عنوان نمایشی</Label>
                        <Input
                            value={form.label}
                            onChange={(e) =>
                                setForm((f) => ({ ...f, label: e.target.value }))
                            }
                            placeholder="مثلاً مجرد"
                        />
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <div className="grid gap-2">
                            <Label>عنوان گروه</Label>
                            <Input
                                value={form.group_label}
                                onChange={(e) =>
                                    setForm((f) => ({
                                        ...f,
                                        group_label: e.target.value,
                                    }))
                                }
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label>ترتیب</Label>
                            <Input
                                dir="ltr"
                                type="number"
                                value={form.sort_order}
                                onChange={(e) =>
                                    setForm((f) => ({
                                        ...f,
                                        sort_order: e.target.value,
                                    }))
                                }
                            />
                        </div>
                    </div>
                    <div className="grid gap-2">
                        {selectedGroup === "city" ? (
                            <>
                                <Label>استان والد</Label>
                                <Select
                                    value={form.parent_value || null}
                                    onValueChange={(value: string | null) => {
                                        if (value === null) return;
                                        setForm((f) => ({
                                            ...f,
                                            parent_value: value,
                                        }));
                                    }}
                                    itemToStringLabel={(val) =>
                                        provinceOptions.find(
                                            (o) => o.value === val,
                                        )?.label ?? (val as string)
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="انتخاب استان" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {provinceOptions.map((option) => (
                                            <SelectItem
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </>
                        ) : (
                            <>
                                <Label>وابسته به (مقدار والد)</Label>
                                <Input
                                    dir="ltr"
                                    value={form.parent_value}
                                    onChange={(e) =>
                                        setForm((f) => ({
                                            ...f,
                                            parent_value: e.target.value,
                                        }))
                                    }
                                    placeholder="در صورت نیاز"
                                />
                            </>
                        )}
                    </div>
                    <div className="grid gap-2">
                        <Label>متادیتا (JSON)</Label>
                        <textarea
                            dir="ltr"
                            rows={4}
                            value={form.meta}
                            onChange={(e) =>
                                setForm((f) => ({ ...f, meta: e.target.value }))
                            }
                            placeholder='مثلاً {"slug":"markazi"}'
                            className="flex min-h-[6rem] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                        />
                    </div>
                    <div className="flex items-center gap-2">
                        <Switch
                            checked={form.is_active}
                            onCheckedChange={(checked) =>
                                setForm((f) => ({ ...f, is_active: checked }))
                            }
                        />
                        <Label>فعال</Label>
                    </div>
                </div>
            </ResponsiveDialog>
        </Card>
    );
}
