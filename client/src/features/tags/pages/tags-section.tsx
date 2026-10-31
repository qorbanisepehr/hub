import { useEffect, useMemo, useState } from "react";
import { getRouteApi } from "@tanstack/react-router";
import type {
    ColumnDef,
    Row,
    RowSelectionState,
    StockFeatures,
} from "@tanstack/react-table";
import {
    IconGitMerge,
    IconPencil,
    IconPlus,
    IconRefresh,
    IconTag,
} from "@tabler/icons-react";
import { toast } from "sonner";

import { DataTablePage, dataTableSelectColumn } from "@/components/data-table";
import { Button } from "@/components/ui/button";
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import { ConfirmDeleteButton } from "@/components/ui/confirm-delete-button";
import { Spinner } from "@/components/ui/spinner";
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
    useAcceptTagMerges,
    useCreateTag,
    useDeleteTag,
    useProposeTagMerges,
    useTagOptions,
    useTags,
    useUpdateTag,
} from "../hooks";
import { TagMergeDialog } from "./tag-merge-dialog";
import { ManualTagMergeDialog } from "./manual-tag-merge-dialog";

import type { TagDto, TagMergeProposalDto } from "../types";
import { getApiError, getApiErrorStatus } from "@/lib/error-utils";
import { PERMISSIONS } from "@/lib/permissions";

const tagsRoute = getRouteApi("/protected/settings/tags");

/** The slug rule the backend enforces — mirrored client-side for UX. */
// Built from a string: TS's regex type-checker does not know Script property
// names (\p{Arabic}/\p{Latin}) even though the runtime accepts them.
const SLUG_PATTERN = new RegExp("^[\\p{Script=Arabic}\\p{Script=Latin}\\p{Number}_-]+$", "u");

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
    // The merge picker needs the whole vocabulary; the default options cap is
    // 100 and the hub already carries more tags than that.
    const { data: allTagOptions } = useTagOptions(undefined, 500);

    const [rowSelection, setRowSelection] = useState<RowSelectionState>({});

    const selectedTags = useMemo(
        () =>
            (data?.data ?? []).filter(
                (tag) => Boolean(rowSelection[String(tag.id)]),
            ),
        [data?.data, rowSelection],
    );

    const selectionScope = [
        url.globalFilter ?? "",
        url.activeSort?.id ?? "",
        url.activeSort?.desc ? "desc" : "asc",
    ].join("|");

    useEffect(() => {
        // The ticked ids belonged to one result set; carrying them into
        // another would make the bulk count a claim about rows nobody sees.
        setRowSelection({});
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [selectionScope]);

    const [dialogOpen, setDialogOpen] = useState(false);
    const [editing, setEditing] = useState<TagDto | null>(null);
    const [name, setName] = useState("");
    const [slug, setSlug] = useState("");

    // Consolidation lives beside the CRUD, not inside it: the proposal is an
    // answer the server computes on demand and the client holds until the
    // person decides, so there is nothing here to load on mount.
    const [mergeOpen, setMergeOpen] = useState(false);
    const [proposal, setProposal] = useState<TagMergeProposalDto | null>(null);
    const [manualMergeOpen, setManualMergeOpen] = useState(false);
    const [manualCanonicalSlug, setManualCanonicalSlug] = useState<string | null>(null);
    const [manualDuplicateSlugs, setManualDuplicateSlugs] = useState<string[]>([]);
    const [manualSingleSourceSlug, setManualSingleSourceSlug] = useState<string | null>(null);
    const [manualMode, setManualMode] = useState<"bulk" | "manual" | "single">("manual");
    const [manualTags, setManualTags] = useState<TagDto[] | null>(null);

    const openMerge = () => {
        propose.mutate(undefined, {
            onSuccess: (result) => {
                setProposal(result);
                setMergeOpen(true);
            },
        });
    };

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

    const create = useCreateTag();
    const update = useUpdateTag();
    const remove = useDeleteTag();
    const propose = useProposeTagMerges();
    const accept = useAcceptTagMerges();

    const slugValid = SLUG_PATTERN.test(slug);
    const saveDisabled =
        name.trim() === "" ||
        slug === "" ||
        !slugValid ||
        create.isPending ||
        update.isPending;

    const handleSave = () => {
        if (editing) {
            update.mutate(
                { id: editing.id, name: name.trim(), slug },
                {
                    onSuccess: () => setDialogOpen(false),
                    // A name collision is a signal, not a dead end: the hub
                    // already carries that label, so the person most likely
                    // wants the two tags to become one. Send them straight to
                    // the merge dialog with the colliding tag pre-picked.
                    onError: (error) => {
                        const existing = (allTagOptions ?? []).find(
                            (tag) =>
                                tag.name === name.trim() &&
                                tag.slug !== editing.slug,
                        );

                        if (getApiErrorStatus(error) === 422 && existing) {
                            toast.info(
                                `برچسب «${existing.name}» از قبل هست؛ برای ادغام همان را انتخاب کردیم.`,
                            );
                            setDialogOpen(false);
                            setManualMode("single");
                            setManualDuplicateSlugs([]);
                            setManualSingleSourceSlug(editing.slug);
                            setManualCanonicalSlug(existing.slug);
                            setManualMergeOpen(true);
                            return;
                        }

                        toast.error(getApiError(error) ?? "خطا در ذخیره برچسب");
                    },
                },
            );
        } else {
            create.mutate(
                { name: name.trim(), slug },
                {
                    onSuccess: () => setDialogOpen(false),
                    onError: (error) =>
                        toast.error(getApiError(error) ?? "خطا در ساخت برچسب"),
                },
            );
        }
    };

    const columns: ColumnDef<StockFeatures, TagDto>[] = [
        ...(canManage
            ? [dataTableSelectColumn<TagDto>()]
            : []),
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
        enableRowSelection: canManage,
        rowSelection,
        onRowSelectionChange: setRowSelection,
        getRowId: (row: TagDto) => String(row.id),
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
                        <>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={openMerge}
                                disabled={propose.isPending}
                            >
                                {propose.isPending ? (
                                    <Spinner />
                                ) : (
                                    <IconGitMerge className="size-4" />
                                )}
                                ادغام هوشمند
                            </Button>
                            <Button type="button" onClick={openCreate}>
                                <IconPlus className="size-4" />
                                افزودن برچسب
                            </Button>
                        </>
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
                    searchMode="manual"
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
                    bulkActions={
                        canManage
                            ? [
                                  {
                                      id: "merge-selected",
                                      label: "ادغام برچسب‌های انتخاب‌شده",
                                      icon: IconGitMerge,
                                      onClick: () => {
                                          setManualMode("bulk");
                                          setManualTags(selectedTags);
                                          setManualSingleSourceSlug(null);
                                          setManualCanonicalSlug(null);
                                          setManualDuplicateSlugs([]);
                                          setManualMergeOpen(true);
                                      },
                                      disabled: selectedTags.length < 2,
                                  },
                              ]
                            : undefined
                    }
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
                                ? "عنوان و شناسه هر دو قابل ویرایش‌اند؛ شناسه همان کلیدی است که فیلترها و ورود اطلاعات با آن تطبیق می‌دهند."
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
                                value={slug}
                                onChange={(e) => setSlug(e.target.value)}
                                placeholder="project-soongon"
                            />
                            {slug !== "" && !slugValid && (
                                <p className="text-destructive text-xs">
                                    فقط حروف فارسی/انگلیسی، عدد، خط تیره و زیرخط
                                    مجاز است
                                </p>
                            )}
                        </div>
                    </div>

                    <DialogFooter>
                        {editing && (
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={() => {
                                    setDialogOpen(false);
                                    setManualMode("single");
                                    setManualDuplicateSlugs([]);
                                    setManualSingleSourceSlug(editing.slug);
                                    setManualCanonicalSlug(null);
                                    setManualMergeOpen(true);
                                }}
                            >
                                <IconGitMerge className="size-4" />
                                ادغام در...
                            </Button>
                        )}
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

            <TagMergeDialog
                open={mergeOpen}
                onOpenChange={setMergeOpen}
                proposal={proposal}
                isAccepting={accept.isPending}
                onAccept={(groups) =>
                    accept.mutate(
                        { groups },
                        { onSuccess: () => setMergeOpen(false) },
                    )
                }
            />
            <ManualTagMergeDialog
                open={manualMergeOpen}
                onOpenChange={(open) => {
                    setManualMergeOpen(open);
                    if (!open) {
                        setManualSingleSourceSlug(null);
                        setManualCanonicalSlug(null);
                        setManualDuplicateSlugs([]);
                    }
                }}
                mode={manualMode}
                tags={
                    manualMode === "bulk"
                        ? manualTags ?? []
                        : allTagOptions ?? data?.data ?? []
                }
                isAccepting={accept.isPending}
                defaultCanonicalSlug={manualCanonicalSlug}
                defaultDuplicateSlugs={manualDuplicateSlugs}
                singleSourceSlug={manualSingleSourceSlug}
                onAccept={(groups) =>
                    accept.mutate(
                        { groups },
                        { onSuccess: () => {
                            setManualMergeOpen(false);
                            setManualCanonicalSlug(null);
                            setManualDuplicateSlugs([]);
                            setManualSingleSourceSlug(null);
                            setRowSelection({});
                        } },
                    )
                }
            />
        </Card>
    );
}
