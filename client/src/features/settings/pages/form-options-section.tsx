import { useState } from "react";
import type { ColumnDef, Row, StockFeatures } from "@tanstack/react-table";
import { IconListDetails, IconPencil, IconPlus } from "@tabler/icons-react";

import { ActiveBadge } from "@/components/shared/active-badge";
import { DataTablePage } from "@/components/data-table";
import { Button } from "@/components/ui/button";
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import { ConfirmDeleteButton } from "@/components/ui/confirm-delete-button";
import { Switch } from "@/components/ui/switch";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import {
    useDataTable,
    useDataTableUrlState,
} from "@/hooks/use-data-table-page";
import { usePermission } from "@/features/auth/components/permission-guard";
import { groupDisplayName } from "@/features/form-options/groups";
import { useAdminFormOptionGroups, useAdminFormOptions, useFormOptionsAdmin } from "@/features/form-options/hooks/use-form-options";
import type { FormOption } from "@/features/form-options/types";
import { Route } from "@/routes/_protected/settings";
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

    const search = Route.useSearch();
    const navigate = Route.useNavigate();

    const selectedGroup = search.group ?? "";

    const [dialogOpen, setDialogOpen] = useState(false);
    const [editing, setEditing] = useState<FormOption | null>(null);
    const [form, setForm] = useState<OptionFormState>(emptyForm(0));

    const url = useDataTableUrlState({
        search: search as unknown as Record<string, unknown>,
        navigate,
        urlState: {
            globalFilter: { enabled: true, key: "filter" },
        },
    });

    const { data: groups = [], isLoading: groupsLoading } =
        useAdminFormOptionGroups();
    const { data, isLoading, isError, refetch } = useAdminFormOptions(
        selectedGroup || undefined,
        url.pagination.pageIndex + 1,
        url.pagination.pageSize,
        url.globalFilter || undefined,
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
                        <ActiveBadge isActive={row.original.is_active} />
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

    const table = useDataTable({
        columns,
        data: rows,
        meta,
        url,
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
            <CardContent className="p-0">
                <div className="flex flex-wrap items-center justify-end gap-3 px-4 pt-4">
                    <Select
                        value={selectedGroup || null}
                        onValueChange={(value: string | null) => {
                            if (!value) return;
                            navigate({
                                search: (prev) => ({
                                    ...prev,
                                    tab: "form-options",
                                    group: value,
                                    page: undefined,
                                }),
                            });
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

                <DataTablePage
                    table={table}
                    meta={meta}
                    isLoading={isLoading}
                    isError={isError}
                    title=""
                    icon={IconListDetails}
                    embedded
                    searchPlaceholder="جستجوی عنوان یا مقدار…"
                    globalFilter={url.globalFilter}
                    onGlobalFilterChange={url.onGlobalFilterChange}
                    onRetry={() => refetch()}
                    colSpan={columns.length}
                    emptyMessage="هنوز گزینه‌ای در این گروه ثبت نشده است"
                />
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
