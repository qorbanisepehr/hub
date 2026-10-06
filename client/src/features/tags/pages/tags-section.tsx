import { useState } from "react";
import { getRouteApi } from "@tanstack/react-router";
import type { ColumnDef, Row, StockFeatures } from "@tanstack/react-table";
import {
    IconPencil,
    IconPlus,
    IconRefresh,
    IconTag,
} from "@tabler/icons-react";

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
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
    useDataTable,
    useDataTableUrlState,
} from "@/hooks/use-data-table-page";
import { usePermission } from "@/features/auth/components/permission-guard";
import {
    useCreateTag,
    useDeleteTag,
    useTags,
    useUpdateTag,
} from "../hooks";
import type { TagDto } from "../types";
import { PERMISSIONS } from "@/lib/permissions";

const tagsRoute = getRouteApi("/protected/settings/tags");

/** The slug rule the backend enforces — mirrored client-side for UX. */
// Built from a string: TS's regex type-checker does not know Script property
// names (\p{Arabic}/\p{Latin}) even though the runtime accepts them.
const SLUG_PATTERN = new RegExp("^[\\p{Arabic}\\p{Latin}\\p{N}_-]+$/u");

/**
 * Tag management (Phase 4, K3 = general service): a Settings tab on the
 * shared DataTablePage. Tags are create/renamed/deleted here; attaching
 * happens on the taggable entities themselves (meeting detail).
 */
export function TagsSection() {
    const canManage = usePermission([PERMISSIONS.TAG_MANAGE]);

    const search = tagsRoute.useSearch();
    const navigate = tagsRoute.useNavigate();

    const url = useDataTableUrlState({
        search: search as unknown as Record<string, unknown>,
        navigate,
        urlState: {
            pagination: { pageKey: "tags_page", pageSizeKey: "tags_per_page" },
            sorting: { sortKey: "tags_sort", orderKey: "tags_order" },
            globalFilter: { enabled: true, key: "tags_filter" },
        },
    });

    const { data, isLoading, isError, isFetching, refetch } = useTags({
        page: url.pagination.pageIndex + 1,
        per_page: url.pagination.pageSize,
        sort: url.activeSort?.id,
        order: url.activeSort?.desc ? "desc" : "asc",
        filter: url.globalFilter || undefined,
    });

    const create = useCreateTag();
    const update = useUpdateTag();
    const remove = useDeleteTag();

    const [dialogOpen, setDialogOpen] = useState(false);
    const [editing, setEditing] = useState<TagDto | null>(null);
    const [name, setName] = useState("");
    const [slug, setSlug] = useState("");

    const openCreate = () => {
        setEditing(null);
        setName("");
        setSlug("");
        setDialogOpen(true);
    };

    const openEdit = (tag: TagDto) => {
        setEditing(tag);
        setName(tag.name);
        setSlug(tag.slug);
        setDialogOpen(true);
    };

    const slugValid = SLUG_PATTERN.test(slug);
    const saveDisabled =
        name.trim() === "" ||
        create.isPending ||
        update.isPending ||
        (!editing && (slug === "" || !slugValid));

    const handleSave = () => {
        if (editing) {
            update.mutate(
                { id: editing.id, name: name.trim() },
                { onSuccess: () => setDialogOpen(false) },
            );
        } else {
            create.mutate(
                { name: name.trim(), slug },
                { onSuccess: () => setDialogOpen(false) },
            );
        }
    };

    const columns: ColumnDef<StockFeatures, TagDto>[] = [
        {
            accessorKey: "name",
            header: "عنوان",
            enableSorting: true,
            cell: ({ row }: { row: Row<StockFeatures, TagDto> }) => (
                <span className="font-medium">{row.original.name}</span>
            ),
        },
        {
            accessorKey: "slug",
            header: "شناسه",
            enableSorting: false,
            cell: ({ row }: { row: Row<StockFeatures, TagDto> }) => (
                <span
                    dir="ltr"
                    className="text-muted-foreground"
                >
                    {row.original.slug}
                </span>
            ),
        },
        {
            accessorKey: "usage_count",
            header: "تعداد استفاده",
            enableSorting: true,
            cell: ({ row }: { row: Row<StockFeatures, TagDto> }) => (
                <span className="text-muted-foreground">
                    {(row.original.usage_count ?? 0).toLocaleString("fa-IR")}
                </span>
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
                          row: Row<StockFeatures, TagDto>;
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
                                      remove.mutate(row.original.id)
                                  }
                                  isPending={remove.isPending}
                              />
                          </div>
                      ),
                  } satisfies ColumnDef<StockFeatures, TagDto>,
              ]
            : []),
    ];

    const table = useDataTable({
        columns,
        data: data?.data ?? [],
        meta: data?.meta,
        url,
    });

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <IconTag className="size-5" />
                    برچسب‌ها
                </CardTitle>
                <CardDescription>
                    مدیریت برچسب‌های مشترک سیستم — برچسب‌گذاری جلسات و سایر
                    موجودیت‌ها با همین فهرست انجام می‌شود
                </CardDescription>
            </CardHeader>
            <CardContent className="p-0">
                <div className="flex flex-wrap items-center justify-end gap-3 px-4 pt-4">
                    {canManage && (
                        <Button type="button" onClick={openCreate}>
                            <IconPlus className="size-4" />
                            افزودن برچسب
                        </Button>
                    )}
                </div>

                <DataTablePage
                    table={table}
                    meta={data?.meta}
                    isLoading={isLoading}
                    isError={isError}
                    title=""
                    icon={IconTag}
                    embedded
                    searchPlaceholder="جستجوی عنوان یا شناسه…"
                    globalFilter={url.globalFilter}
                    onGlobalFilterChange={url.onGlobalFilterChange}
                    actions={[
                        {
                            id: "refresh",
                            label: "بازخوانی",
                            icon: IconRefresh,
                            onClick: () => refetch(),
                            disabled: isFetching,
                        },
                    ]}
                    onRetry={() => refetch()}
                    colSpan={columns.length}
                    emptyMessage="هنوز برچسبی ثبت نشده است"
                />
            </CardContent>

            <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>
                            {editing ? "ویرایش برچسب" : "افزودن برچسب"}
                        </DialogTitle>
                        <DialogDescription>
                            {editing
                                ? "شناسه برچسب پس از ایجاد تغییر نمی‌کند؛ فقط عنوان قابل ویرایش است."
                                : "شناسه، کلید پایدار برچسب برای فیلترها و اتصال‌ها است."}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4 py-2">
                        <div className="grid gap-2">
                            <Label htmlFor="tag-name">عنوان</Label>
                            <Input
                                id="tag-name"
                                value={name}
                                onChange={(e) => setName(e.target.value)}
                                placeholder="مثلاً پروژه سونگون"
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="tag-slug">شناسه</Label>
                            <Input
                                id="tag-slug"
                                dir="ltr"
                                value={editing ? editing.slug : slug}
                                onChange={(e) => setSlug(e.target.value)}
                                disabled={editing !== null}
                                placeholder="project-soongon"
                            />
                            {!editing && slug !== "" && !slugValid && (
                                <p className="text-destructive text-xs">
                                    فقط حروف فارسی/انگلیسی، عدد، خط تیره و زیرخط
                                    مجاز است
                                </p>
                            )}
                        </div>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setDialogOpen(false)}
                        >
                            انصراف
                        </Button>
                        <Button
                            type="button"
                            onClick={handleSave}
                            disabled={saveDisabled}
                        >
                            ذخیره
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </Card>
    );
}
