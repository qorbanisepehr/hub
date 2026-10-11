import { useEffect, useMemo, useRef, useState } from "react";
import { IconInbox, IconRobot } from "@tabler/icons-react";

import { Badge } from "@/components/ui/badge";
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
import type {
    BoardMeetingListParams,
    MeetingSummaryAction,
    SummaryWriteMode,
} from "../types";

/** How many candidates to pull at a time; the server caps a page at 200. */
const PAGE_SIZE = 100;

/**
 * The marker a candidate row carries when a run would not be a first one.
 *
 * `generate` has none: it is the default state of the list, and a badge on
 * every row would be noise. The other two are the rows a person has to notice —
 * one needs an opt-in to run at all, the other cannot run — so they say so on
 * the row rather than in a summary line the eye skips.
 */
const ACTION_BADGES: Partial<
    Record<MeetingSummaryAction, { label: string; variant: "secondary" | "warning" }>
> = {
    regenerate: { label: "خلاصه دارد", variant: "secondary" },
    blocked: { label: "قابل اجرا نیست", variant: "warning" },
};

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
 * The bulk picker: choose which meetings to run the model over.
 *
 * Selection lives here rather than in the table, because this action is not
 * really "act on the rows I highlighted" — it is "run this over some of the
 * meetings that have no summary yet". Putting the choice in a dialog means the
 * list it draws from can be searched and paged without changing what the table
 * itself can do, and it puts the count of what is about to be queued on screen
 * before the button is pressed.
 *
 * `regenerate` flips the filter-driven face to its other scope — the meetings
 * that already have a summary — without becoming a second dialog: the picker,
 * the paging, the model choice and the count are the same questions asked about
 * a different set, so only the list, its words and the rule the server re-checks
 * change.
 *
 * The preset face is the same picker over the rows the table already chose. It
 * asks the server to classify exactly those ids, because the table knows which
 * meetings are ticked and nothing about their summaries, and a row ticked on an
 * earlier page is not in the list window at all. Each row comes back with what a
 * run would do with it: the ones with no summary are already ticked, and the
 * ones that have one are offered with a checkbox, because replacing a summary is
 * a different decision from making the first one and is opted into rather than
 * assumed. A row that cannot run — mid-run, or holding a proposal — says so and
 * cannot be ticked.
 *
 * The candidate list is paged rather than fetched whole. The registry runs to
 * hundreds of meetings and every one of them is a slow model call, so the
 * dialog loads a page, appends the next on request, and says how much of the
 * total is on screen. A "select all" that quietly meant "all of the hundred
 * that loaded" would be a checkbox telling a lie, with the count beside it as
 * the only way to notice. The preset list is the exception: it is bounded by the
 * selection and returned whole, because paging would answer the classification
 * question for only part of the set.
 */
export function BulkSummaryDialog({
    open,
    onOpenChange,
    params,
    presetMeetingIds,
    regenerate = false,
    onQueued,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** The list page's own filters, so the blast radius is what is on screen. */
    params: BoardMeetingListParams;
    /**
     * A preset selection (opened from the table's selection bar): the ticked
     * meeting ids. When set, the picker lists exactly those and classifies each
     * one, instead of drawing a scope from the filters. `undefined` is the
     * filter-driven path that picks its own candidates.
     */
    presetMeetingIds?: number[];
    /**
     * The scope the filter-driven face is for: false lists the meetings with no
     * summary yet, true lists the ones that already have a summary. It reaches
     * the candidate query and the dispatch, because the server re-checks
     * eligibility against whichever set the list came from. The preset face
     * ignores it: its scope is the action the server reported per row.
     */
    regenerate?: boolean;
    /** Fired once the batch is accepted by the API (not on dialog close) —
     *  the caller clears its row selection here. */
    onQueued?: () => void;
}) {
    const canPickModel = usePermission([PERMISSIONS.AI_PROVIDER_VIEW]);
    const { data: models } = useAiModels(canPickModel);

    const isPreset = presetMeetingIds !== undefined;

    const [picked, setPicked] = useState<Set<number>>(new Set());
    const [search, setSearch] = useState("");
    const [mode, setMode] = useState<SummaryWriteMode[]>(["review"]);
    const [modelId, setModelId] = useState(AGENT_DEFAULT);
    /** The preset identity already seeded into `picked`, so a re-render does not re-stamp it. */
    const presetSeeded = useRef<string | null>(null);

    // Two ways in, one query. The filter-driven face pages through a scope; the
    // preset face asks about the ids the table already chose and gets them back
    // whole, classified. A row ticked on an earlier page is not in the list
    // window, so there is nothing to filter — only a set to name.
    const { data, isLoading, isFetchingNextPage, fetchNextPage, hasNextPage } =
        useSummaryCandidates(
            isPreset
                ? { meeting_ids: presetMeetingIds }
                : { ...params, per_page: PAGE_SIZE, regenerate },
            open,
        );

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

    // The preset's total is its selection, not the query's: the ids ARE the
    // window, so the count beside the boxes is what the person ticked in the
    // table rather than how much of the registry the filters happen to match.
    const total = isPreset
        ? (presetMeetingIds?.length ?? 0)
        : (data?.pages?.[0]?.meta?.total ?? 0);

    const presetKey = isPreset ? presetMeetingIds.join(",") : "";

    useEffect(() => {
        if (!open) {
            setPicked(new Set());
            setSearch("");
            presetSeeded.current = null;
        }
    }, [open]);

    // A preset opens with the rows that still need a first summary already
    // ticked. The ones that have one are the point of the checkbox: the person
    // chose the rows for a reason, but replacing an existing summary is a
    // different, more expensive decision, so it is opted into rather than
    // assumed. Seeded once per open, on identity rather than spread-compare,
    // because the caller rebuilds the array each open and an unconditional
    // re-seed would stamp over the person's ticks.
    useEffect(() => {
        if (!isPreset || presetKey === "" || rows.length === 0) {
            return;
        }

        if (presetSeeded.current === presetKey) {
            return;
        }

        presetSeeded.current = presetKey;
        setPicked(
            new Set(
                rows
                    .filter((row) => row.action === "generate")
                    .map((row) => row.id),
            ),
        );
    }, [isPreset, presetKey, rows]);

    // "Select all" means "all of the ones that can run": a blocked row cannot be
    // ticked, so counting it as visible would leave the header saying "all
    // selected" while a row sat there unchecked.
    const visibleIds = rows
        .filter((row) => row.action !== "blocked")
        .map((row) => row.id);
    const visiblePickedCount = visibleIds.filter((id) => picked.has(id)).length;
    const everyVisiblePicked =
        visibleIds.length > 0 && visiblePickedCount === visibleIds.length;

    // What the dispatch will hold, split by the intent the server reported per
    // row. The filter-driven face knows its own scope, so its ticks all carry
    // it; the preset face reads each row's action.
    const generateIds = isPreset
        ? rows
              .filter((row) => picked.has(row.id) && row.action === "generate")
              .map((row) => row.id)
        : regenerate
          ? []
          : [...picked];
    const regenerateIds = isPreset
        ? rows
              .filter((row) => picked.has(row.id) && row.action === "regenerate")
              .map((row) => row.id)
        : regenerate
          ? [...picked]
          : [];

    // What the preset list has to explain about itself: how many rows need an
    // opt-in before they will run, and how many cannot run at all. The
    // filter-driven faces are homogeneous — the scope decides for every row —
    // so this is the one face where the sentence above the list has to describe
    // a mixed set instead of naming a scope.
    const regeneratableRows = rows.filter(
        (row) => row.action === "regenerate",
    ).length;
    const blockedRows = rows.filter((row) => row.action === "blocked").length;

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
            title={
                isPreset
                    ? "خلاصهٔ جلسات انتخاب‌شده"
                    : regenerate
                      ? "بازتولید خلاصه برای جلسات دارای خلاصه"
                      : "تولید خلاصه برای جلسات تولیدنشده"
            }
            description={
                isPreset
                    ? [
                          `${total.toLocaleString("fa-IR")} جلسهٔ انتخاب‌شده؛ هر جلسه یک اجرای مدل است و زمان می‌برد.`,
                          regeneratableRows > 0
                              ? "جلساتی که خلاصه دارند انتخاب نشده‌اند؛ برای بازتولید، خودتان تیک بزنید."
                              : null,
                          blockedRows > 0
                              ? `${blockedRows.toLocaleString("fa-IR")} جلسه در حال حاضر قابل اجرا نیست.`
                              : null,
                      ]
                          .filter((part) => part !== null)
                          .join(" ")
                    : regenerate
                      ? `${total.toLocaleString("fa-IR")} جلسه خلاصه دارد. موارد دلخواه را برای بازتولید انتخاب کنید؛ هر جلسه یک اجرای مدل است و زمان می‌برد.`
                      : `${total.toLocaleString("fa-IR")} جلسه خلاصه ندارد. موارد دلخواه را انتخاب کنید؛ هر جلسه یک اجرای مدل است و زمان می‌برد.`
            }
            footer={
                <>
                    <Button
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        انصراف
                    </Button>
                    <BulkSubmit
                        generateIds={generateIds}
                        regenerateIds={regenerateIds}
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
                        {...(onQueued ? { onQueued } : {})}
                    />
                </>
            }
        >
            <div className="flex flex-col gap-4">
                <div className="flex flex-wrap items-center gap-2">
                    {!isPreset && (
                        <Input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="جستجو در شماره یا عنوان جلسه…"
                            className="h-9 min-w-48 flex-1"
                        />
                    )}
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

                {/*
                 * One list for both faces. The filter-driven face draws a scope;
                 * the preset face names the rows the table chose and lets the
                 * server say what each one would do. Same boxes and same count,
                 * and only the source of the rows differs.
                 */}
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
                                {isPreset
                                    ? "انتخاب همهٔ قابل‌اجرا"
                                    : "انتخاب همهٔ نمایش‌داده‌شده‌ها"}
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
                                    isPreset
                                        ? "جلسه‌ای برای نمایش نیست."
                                        : search !== ""
                                          ? "جلسه‌ای با این عبارت پیدا نشد."
                                          : regenerate
                                            ? "هیچ جلسهٔ دارای خلاصه‌ای در این فیلتر نیست."
                                            : "همهٔ جلسات این فیلتر خلاصه دارند."
                                }
                            />
                        ) : (
                            rows.map((row) => {
                                const marker = ACTION_BADGES[row.action];

                                return (
                                    <Field
                                        key={row.id}
                                        orientation="horizontal"
                                        className="rounded px-2 py-1.5 hover:bg-muted/50"
                                    >
                                        <Checkbox
                                            checked={picked.has(row.id)}
                                            // A row the server says cannot run
                                            // cannot be asked for: mid-run, or
                                            // holding a proposal nobody has
                                            // decided. A tick on it would queue
                                            // nothing and say nothing.
                                            disabled={
                                                row.action === "blocked"
                                            }
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
                                                {marker && (
                                                    <Badge
                                                        variant={marker.variant}
                                                    >
                                                        {marker.label}
                                                    </Badge>
                                                )}
                                                {row.title && (
                                                    <span className="text-muted-foreground ms-auto truncate">
                                                        {row.title}
                                                    </span>
                                                )}
                                            </span>
                                        </FieldLabel>
                                    </Field>
                                );
                            })
                        )}
                    </div>
                </FieldSet>

                {!isPreset && hasNextPage && (
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
    generateIds,
    regenerateIds,
    mode,
    provider,
    model,
    onDone,
    onQueued,
}: {
    /** The ids to write a first summary for. */
    generateIds: number[];
    /** The ids whose existing summary the person opted to replace. */
    regenerateIds: number[];
    mode: SummaryWriteMode;
    /** The gateway and model for this batch, as names; null means inherit. */
    provider: string | null;
    model: string | null;
    onDone: () => void;
    onQueued?: () => void;
}) {
    const bulk = useBulkGenerateMeetingSummary();
    const count = generateIds.length + regenerateIds.length;

    // One button, two intents. The label says which kinds are inside rather than
    // naming one of them: a mixed press is neither a generation nor a
    // regeneration, and calling it either would misdescribe half of what is
    // about to run.
    const action =
        regenerateIds.length === 0
            ? "تولید برای"
            : generateIds.length === 0
              ? "بازتولید برای"
              : "اجرا برای";

    return (
        <Button
            onClick={() => {
                bulk.mutate(
                    {
                        meeting_ids: generateIds,
                        regenerate_ids: regenerateIds,
                        mode,
                        provider,
                        model,
                    },
                    {
                        onSuccess: () => {
                            onQueued?.();
                            onDone();
                        },
                    },
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
            {action} {count.toLocaleString("fa-IR")} جلسه
        </Button>
    );
}
