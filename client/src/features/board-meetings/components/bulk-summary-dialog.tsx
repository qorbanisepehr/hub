import { useEffect, useMemo, useState } from "react";
import { IconInbox, IconRobot } from "@tabler/icons-react";

import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Field, FieldLabel, FieldSet } from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import { ResponsiveDialog } from "@/components/ui/responsive-dialog";
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { Separator } from "@/components/ui/separator";
import { Skeleton } from "@/components/ui/skeleton";
import { Spinner } from "@/components/ui/spinner";
import { ToggleGroup, ToggleGroupItem } from "@/components/ui/toggle-group";
import { EmptyState } from "@/components/layout";
import { usePermission } from "@/features/auth/components/permission-guard";
import { PERMISSIONS } from "@/lib/permissions";
import { useAiModels } from "@/features/ai/hooks";
import { toPersianDate } from "@/lib/date-format";
import { useBulkGenerateMeetingSummary, useSummaryCandidates } from "../hooks";
import type { BoardMeetingListParams, SummaryWriteMode } from "../types";

/** How many candidates to pull at a time; the server caps a page at 200. */
const PAGE_SIZE = 100;

/**
 * The picker's value for "whatever the agent is bound to".
 *
 * Starts with a `|`, which no provider or model name can contain, so it cannot
 * collide with a real `provider|model` pair however the registry is filled.
 */
const AGENT_DEFAULT = "|inherit";

/**
 * The picker's value for one model: `provider|model`.
 *
 * Both halves, because a model name is unique only within a provider — two
 * gateways offering the same name under one value would be indistinguishable.
 */
function modelValue(model: {
    provider_name?: string | null;
    model_name: string;
}): string {
    return `${model.provider_name ?? ""}|${model.model_name}`;
}

const MODE_LABELS: Record<SummaryWriteMode, string> = {
    review: "پیشنهاد بده",
    apply: "مستقیم بنویس",
};

const MODEL_DEFAULT_LABEL = "مدل پیش‌فرضِ ایجنت";

function modelLabel(model: {
    provider_name?: string | null;
    display_label: string;
}): string {
    return model.provider_name
        ? `${model.provider_name}/${model.display_label}`
        : model.display_label;
}

/**
 * The bulk picker: choose which un-generated meetings to run the model over.
 *
 * Selection lives here rather than in the table, because the table has no row
 * selection and this action is not really "act on the rows I highlighted" — it
 * is "run this over some of the meetings that have no summary yet". Putting the
 * choice in a dialog means the list it draws from can be searched and paged
 * without changing what the table itself can do, and it puts the count of what
 * is about to be queued on screen before the button is pressed.
 *
 * The candidate list is paged rather than fetched whole. The registry runs to
 * hundreds of meetings and every one of them is a slow model call, so the
 * dialog loads a page, appends the next on request, and says how much of the
 * total is on screen. A "select all" that quietly meant "all of the hundred
 * that loaded" would be a checkbox telling a lie, with the count beside it as
 * the only way to notice.
 */
export function BulkSummaryDialog({
    open,
    onOpenChange,
    params,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** The list page's own filters, so the blast radius is what is on screen. */
    params: BoardMeetingListParams;
}) {
    const canPickModel = usePermission([PERMISSIONS.AI_PROVIDER_VIEW]);
    const { data: models } = useAiModels(canPickModel);

    const [picked, setPicked] = useState<Set<number>>(new Set());
    const [search, setSearch] = useState("");
    const [mode, setMode] = useState<SummaryWriteMode[]>(["review"]);
    const [modelId, setModelId] = useState(AGENT_DEFAULT);

    const { data, isLoading, isFetchingNextPage, fetchNextPage, hasNextPage } =
        useSummaryCandidates({ ...params, per_page: PAGE_SIZE }, open);

    // The query accumulates the loaded pages; `meta` is read off the first one
    // because the total belongs to the whole result set, not to a page of it.
    const rows = useMemo(() => {
        const all = (data?.pages ?? []).flatMap((page) => page?.data ?? []);
        const needle = search.trim();

        return needle === ""
            ? all
            : all.filter(
                  (row) =>
                      row.registry_label.includes(needle) ||
                      (row.title ?? "").includes(needle),
              );
    }, [data, search]);

    const total = data?.pages?.[0]?.meta?.total ?? 0;

    useEffect(() => {
        if (!open) {
            setPicked(new Set());
            setSearch("");
        }
    }, [open]);

    const visibleIds = rows.map((row) => row.id);
    const visiblePickedCount = visibleIds.filter((id) => picked.has(id)).length;
    const everyVisiblePicked =
        visibleIds.length > 0 && visiblePickedCount === visibleIds.length;

    // On the Select root, not on `SelectValue`: this is what tells Base UI to
    // print the item's label rather than its raw value. Without it a closed
    // trigger shows the stored string, because the popup's items are not
    // registered until the menu opens.
    const modelLabels: Record<string, string> = {
        [AGENT_DEFAULT]: MODEL_DEFAULT_LABEL,
        ...Object.fromEntries(
            (models?.data ?? []).map((model) => [
                modelValue(model),
                modelLabel(model),
            ]),
        ),
    };

    /**
     * "Select all" adds the visible rows to what is already chosen rather than
     * replacing it. Replacing looked equivalent while the list was one page, and
     * silently dropped every meeting ticked on an earlier page the moment a
     * search narrowed the view — so the count went *down* after ticking all,
     * which is the opposite of what the button says it does.
     */
    function toggleAll(on: boolean) {
        setPicked((prev) => {
            const next = new Set(prev);

            for (const id of visibleIds) {
                if (on) {
                    next.add(id);
                } else {
                    next.delete(id);
                }
            }

            return next;
        });
    }

    return (
        <ResponsiveDialog
            open={open}
            onOpenChange={onOpenChange}
            title="تولید خلاصه برای جلسات تولیدنشده"
            description={`${total.toLocaleString("fa-IR")} جلسه خلاصه ندارد. موارد دلخواه را انتخاب کنید؛ هر جلسه یک اجرای مدل است و زمان می‌برد.`}
            footer={
                <>
                    <Button
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        انصراف
                    </Button>
                    <BulkSubmit
                        count={picked.size}
                        meetingIds={[...picked]}
                        mode={(mode[0] ?? "review") as SummaryWriteMode}
                        {...(modelId === AGENT_DEFAULT
                            ? { provider: null, model: null }
                            : {
                                  provider: modelId.slice(
                                      0,
                                      modelId.indexOf("|"),
                                  ),
                                  model: modelId.slice(
                                      modelId.indexOf("|") + 1,
                                  ),
                              })}
                        onDone={() => onOpenChange(false)}
                    />
                </>
            }
        >
            <div className="flex flex-col gap-4">
                <div className="flex flex-wrap items-center gap-2">
                    <Input
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="جستجو در شماره یا عنوان جلسه…"
                        className="h-9 min-w-48 flex-1"
                    />
                    <ToggleGroup
                        variant="outline"
                        size="sm"
                        value={mode}
                        onValueChange={(value) => {
                            // Deselecting the only selected item would leave no
                            // mode at all and the request requires one, so the
                            // last choice stands.
                            if (value.length > 0) {
                                setMode(value as SummaryWriteMode[]);
                            }
                        }}
                    >
                        {(["review", "apply"] as const).map((value) => (
                            <ToggleGroupItem
                                key={value}
                                value={value}
                                aria-label={MODE_LABELS[value]}
                            >
                                {MODE_LABELS[value]}
                            </ToggleGroupItem>
                        ))}
                    </ToggleGroup>
                    {canPickModel && (
                        <Select
                            value={modelId}
                            items={modelLabels}
                            onValueChange={(value) => {
                                if (value !== null) {
                                    setModelId(value);
                                }
                            }}
                        >
                            <SelectTrigger size="sm" className="w-56">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value={AGENT_DEFAULT}>
                                        {MODEL_DEFAULT_LABEL}
                                    </SelectItem>
                                    {(models?.data ?? []).map((model) => (
                                        <SelectItem
                                            key={modelValue(model)}
                                            value={modelValue(model)}
                                        >
                                            {modelLabel(model)}
                                        </SelectItem>
                                    ))}
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    )}
                </div>

                <Separator />

                <FieldSet>
                    <div className="flex items-center justify-between gap-2">
                        <FieldLabel className="font-normal">
                            <span className="flex items-center gap-2">
                                <Checkbox
                                    checked={everyVisiblePicked}
                                    // Indeterminate rather than off, because
                                    // "some of them" is the common state and a
                                    // plain unchecked box would read as "none".
                                    indeterminate={
                                        !everyVisiblePicked &&
                                        visiblePickedCount > 0
                                    }
                                    onCheckedChange={(checked) =>
                                        toggleAll(checked === true)
                                    }
                                />
                                انتخاب همهٔ نمایش‌داده‌شده‌ها
                            </span>
                        </FieldLabel>

                        <span className="text-muted-foreground text-xs">
                            {picked.size.toLocaleString("fa-IR")} انتخاب شده
                            {total > rows.length &&
                                ` از ${total.toLocaleString("fa-IR")} جلسه`}
                        </span>
                    </div>

                    <div className="mt-2 flex max-h-72 flex-col gap-1 overflow-y-auto rounded-md border p-1">
                        {isLoading ? (
                            <div className="flex flex-col gap-1 p-1">
                                <Skeleton className="h-8 w-full" />
                                <Skeleton className="h-8 w-5/6" />
                                <Skeleton className="h-8 w-4/6" />
                            </div>
                        ) : rows.length === 0 ? (
                            <EmptyState
                                variant="compact"
                                icon={IconInbox}
                                message={
                                    search === ""
                                        ? "همهٔ جلسات این فیلتر خلاصه دارند."
                                        : "جلسه‌ای با این عبارت پیدا نشد."
                                }
                            />
                        ) : (
                            rows.map((row) => (
                                <Field
                                    key={row.id}
                                    orientation="horizontal"
                                    className="rounded px-2 py-1.5 hover:bg-muted/50"
                                >
                                    <Checkbox
                                        checked={picked.has(row.id)}
                                        onCheckedChange={(checked) =>
                                            setPicked((prev) => {
                                                const next = new Set(prev);

                                                if (checked === true) {
                                                    next.add(row.id);
                                                } else {
                                                    next.delete(row.id);
                                                }

                                                return next;
                                            })
                                        }
                                    />
                                    <FieldLabel className="min-w-0 flex-1 font-normal">
                                        <span className="flex min-w-0 items-center gap-2">
                                            <span
                                                dir="ltr"
                                                className="font-medium"
                                            >
                                                {row.registry_label}
                                            </span>
                                            <span className="text-muted-foreground">
                                                {toPersianDate(row.held_on)}
                                            </span>
                                            {row.title && (
                                                <span className="text-muted-foreground ms-auto truncate">
                                                    {row.title}
                                                </span>
                                            )}
                                        </span>
                                    </FieldLabel>
                                </Field>
                            ))
                        )}
                    </div>
                </FieldSet>

                {hasNextPage && (
                    <Button
                        variant="ghost"
                        size="sm"
                        className="self-start"
                        onClick={() => void fetchNextPage()}
                        disabled={isFetchingNextPage}
                    >
                        {isFetchingNextPage && (
                            <Spinner data-icon="inline-start" />
                        )}
                        نمایش جلسات بیشتر
                    </Button>
                )}
            </div>
        </ResponsiveDialog>
    );
}

/** The confirm button, split out so the mutation and the dialog stay separate. */
function BulkSubmit({
    count,
    meetingIds,
    mode,
    provider,
    model,
    onDone,
}: {
    count: number;
    meetingIds: number[];
    mode: SummaryWriteMode;
    /** The gateway and model for this batch, as names; null means inherit. */
    provider: string | null;
    model: string | null;
    onDone: () => void;
}) {
    const bulk = useBulkGenerateMeetingSummary();

    return (
        <Button
            onClick={() => {
                bulk.mutate(
                    { meeting_ids: meetingIds, mode, provider, model },
                    { onSuccess: onDone },
                );
            }}
            // Nothing selected is not a no-op to perform, it is nothing to do:
            // the button says how many sessions it will start.
            disabled={count === 0 || bulk.isPending}
        >
            {bulk.isPending ? (
                <Spinner data-icon="inline-start" />
            ) : (
                <IconRobot data-icon="inline-start" />
            )}
            تولید برای {count.toLocaleString("fa-IR")} جلسه
        </Button>
    );
}
