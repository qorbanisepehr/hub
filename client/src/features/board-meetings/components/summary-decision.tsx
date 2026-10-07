import { useMemo, useState } from "react";
import { IconCheck, IconHash, IconPointFilled } from "@tabler/icons-react";

import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import {
    Field,
    FieldContent,
    FieldDescription,
    FieldLabel,
    FieldLegend,
    FieldSet,
    FieldTitle,
} from "@/components/ui/field";
import { Separator } from "@/components/ui/separator";
import { useTagOptions } from "@/features/tags/hooks";
import { usePermission } from "@/features/auth/components/permission-guard";
import { PERMISSIONS } from "@/lib/permissions";
import { cn } from "@/lib/utils";
import { SUMMARY_FIELDS, type SummaryFieldConfig } from "../summary-copy";
import {
    isEmptyValue,
    proposedTagSlugs,
    readField,
    tagSlugsOf,
} from "../summary-read";
import type { MeetingSummaryArtifact, MeetingSummaryFieldName } from "../types";

/**
 * The compare-and-decide panel: a run proposed something, and this is where a
 * person says which parts of it become the meeting's record.
 *
 * The granularity is per field and per tag, not "accept or reject", because
 * those are the two granularities a person can actually judge. A summary is
 * five independent claims about a meeting; accepting four and refusing one is
 * a normal outcome, not an edge case. A tag is a membership rather than a
 * piece of text, so it cannot be half-accepted — but it can be individually
 * ticked, which is what makes the granularity worth having.
 *
 * Everything starts ticked. That is a deliberate reading of the common case:
 * the panel exists so a person can *compare*, and once they have compared, the
 * model having got it right is the usual answer — so the useful action is one
 * click rather than five. Unticking is one click each, and "none of them" is
 * one click, so being wrong costs one click in every direction.
 */
export function SummaryDecision({
    current,
    candidate,
    currentTags,
    onAccept,
    onDismiss,
    isAccepting,
    isDismissing,
    isRunning,
}: {
    current: MeetingSummaryArtifact;
    candidate: MeetingSummaryArtifact;
    currentTags: string[];
    onAccept: (fields: MeetingSummaryFieldName[], tags: string[]) => void;
    onDismiss: () => void;
    isAccepting: boolean;
    isDismissing: boolean;
    /**
     * The candidate is a run still going, not a run's answer.
     *
     * The panel is mounted from the moment a worker picks the job up, because
     * that is when the placeholder it is about is written — so without this the
     * whole panel would spend ten minutes announcing in the past tense that the
     * run had produced nothing, and would then announce the opposite. Every
     * sentence below that refers to what "this run" did is asked which of the
     * two it means first.
     */
    isRunning: boolean;
}) {
    const canUpdate = usePermission([PERMISSIONS.BOARD_MEETING_UPDATE]);

    // The fields a decision can actually be made about: the run filled these
    // and they hold something. A field that came back empty is not offered —
    // the server would merge it as a failed field and keep whatever is stored,
    // so a tick there would look like an action and change nothing.
    const offered = useMemo(
        () =>
            SUMMARY_FIELDS.map((field) => ({
                field,
                reading: readField(candidate, field.name, field.isList),
                stored: readField(current, field.name, field.isList),
            })).filter(
                ({ field, reading }) =>
                    field.name !== "suggested_tags" &&
                    reading.isFilled &&
                    !isEmptyValue(reading.value),
            ),
        [candidate, current],
    );

    const allFields = useMemo(
        () => offered.map(({ field }) => field.name),
        [offered],
    );

    const baselineTags = useMemo(
        () => proposedTagSlugs(candidate, currentTags),
        [candidate, currentTags],
    );

    /**
     * Which run these selections belong to.
     *
     * Identity is the *run*, not the object. Comparing object references looked
     * equivalent and was not: every refetch of the meeting hands the panel a
     * brand-new `summary.pending` object, so a reference check fired on each
     * one and threw the selection away — which made «هیچ‌کدام» look like it did
     * nothing, because the next refetch quietly ticked everything again.
     * `generated_at` is stamped once per run, so it survives a refetch and
     * changes only when the person is looking at a genuinely new proposal.
     */
    const runId = [
        candidate.generated_at ?? "",
        candidate.provider ?? "",
        candidate.model ?? "",
    ].join("|");

    const [selection, setSelection] = useState<{
        runId: string;
        fields: MeetingSummaryFieldName[];
        tags: string[];
    } | null>(null);

    // A selection belonging to another run is not a selection for this one —
    // its field names may not even exist here. Reinterpreting the stored value
    // against the run id is why there is no reset effect: there is no state to
    // reset, only a value that is either about this run or about none.
    const accepted = selection?.runId === runId ? selection.fields : allFields;
    const tags = selection?.runId === runId ? selection.tags : baselineTags;

    function update(next: {
        fields?: MeetingSummaryFieldName[];
        tags?: string[];
    }) {
        setSelection({
            runId,
            fields: next.fields ?? accepted,
            tags: next.tags ?? tags,
        });
    }

    const changedTags = tags.filter((slug) => !currentTags.includes(slug));
    const droppedTags = currentTags.filter((slug) => !tags.includes(slug));

    /**
     * Whether there is anything here to accept at all.
     *
     * A run can finish with no field filled and no tag proposed — it says so in
     * `summary.error` — and then the only honest action is to close the
     * question. Offering a save button for a proposal with nothing in it asks
     * a person to click "save" to achieve no change.
     */
    // Compared by value, not by reference: `baselineTags` is a fresh array
    // every render, so `!==` would be true always and the panel would offer a
    // save button for a proposal that had nothing in it.
    const hasSomethingToAccept =
        offered.length > 0 ||
        !(
            baselineTags.length === currentTags.length &&
            baselineTags.every((slug) => currentTags.includes(slug))
        );

    return (
        <Alert className="border-warning/40 bg-warning/5">
            <IconHash className="text-warning" />
            <AlertTitle>پیشنهاد تازه برای این جلسه</AlertTitle>
            <AlertDescription>
                {isRunning
                    ? "این اجرا هنوز چیزی تولید نکرده است؛ پس از پایان، پیشنهاد همین‌جا می‌آید و تا آن‌وقت چیزی برای تصمیم گرفتن روی میز نیست."
                    : hasSomethingToAccept
                      ? "هر بخش را جدا تصمیم بگیرید؛ آنچه تیک نخورد روی رکورد جلسه نوشته نمی‌شود."
                      : "این اجرا چیزی برای پذیرفتن تولید نکرد. می‌توانید پیشنهاد را ببندید تا دوباره تلاش کنید."}
            </AlertDescription>

            <div className="col-start-2 flex flex-col gap-4">
                <FieldSet>
                    <LegendRow legend="بخش‌های خلاصه">
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => update({ fields: allFields })}
                            disabled={!canUpdate || isRunning}
                        >
                            انتخاب همه
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => update({ fields: [] })}
                            disabled={!canUpdate || isRunning}
                        >
                            هیچ‌کدام
                        </Button>
                    </LegendRow>

                    {offered.length === 0 ? (
                        <FieldDescription>
                            {isRunning
                                ? "بخش‌ها پس از پایان اجرا اینجا فهرست می‌شوند."
                                : "این اجرا هیچ بخش قابل‌قبولی تولید نکرد."}
                        </FieldDescription>
                    ) : (
                        <div className="flex flex-col gap-3">
                            {offered.map(({ field, reading, stored }) => (
                                <FieldDecisionRow
                                    key={field.name}
                                    config={field}
                                    proposed={reading.value}
                                    stored={stored.value}
                                    hasStored={stored.isFilled}
                                    checked={accepted.includes(field.name)}
                                    onToggle={(on) =>
                                        update({
                                            fields: on
                                                ? [...accepted, field.name]
                                                : accepted.filter(
                                                      (f) => f !== field.name,
                                                  ),
                                        })
                                    }
                                    disabled={!canUpdate}
                                />
                            ))}
                        </div>
                    )}
                </FieldSet>

                <Separator />

                <TagDecision
                    tags={tags}
                    currentTags={currentTags}
                    isRunning={isRunning}
                    onToggle={(slug, on) =>
                        update({
                            tags: on
                                ? [...tags, slug]
                                : tags.filter((s) => s !== slug),
                        })
                    }
                    onAll={() => update({ tags: baselineTags })}
                    onNone={() => update({ tags: [] })}
                    disabled={!canUpdate}
                />

                <div className="flex flex-wrap items-center justify-between gap-2">
                    <p className="text-muted-foreground text-xs">
                        {changedTags.length === 0 && droppedTags.length === 0
                            ? "برچسب‌های جلسه بدون تغییر می‌مانند."
                            : [
                                  changedTags.length > 0
                                      ? `${changedTags.length.toLocaleString("fa-IR")} برچسب افزوده`
                                      : null,
                                  droppedTags.length > 0
                                      ? `${droppedTags.length.toLocaleString("fa-IR")} برچسب حذف`
                                      : null,
                              ]
                                  .filter(Boolean)
                                  .join("، ")}
                    </p>

                    {/*
                        Nothing to decide while a run is going, so neither
                        button is offered rather than being offered greyed: a
                        running placeholder is a status, not a proposal, and
                        «کنار گذاشتن پیشنهاد» would clear the very stamp that
                        says a run is under way — letting a second one start
                        next to the first.
                    */}
                    {!isRunning && (
                        <div className="flex flex-wrap items-center gap-2">
                            {/* Always offered, even when accepting would decide
                                something: refusing the whole proposal is a normal
                                outcome and used to be reachable only by unticking
                                every box, which reads as a decision rather than a
                                refusal. */}
                            <Button
                                variant="outline"
                                onClick={onDismiss}
                                disabled={
                                    !canUpdate || isAccepting || isDismissing
                                }
                            >
                                {isDismissing
                                    ? "در حال بستن…"
                                    : "کنار گذاشتن پیشنهاد"}
                            </Button>
                            {hasSomethingToAccept && (
                                <Button
                                    onClick={() => onAccept(accepted, tags)}
                                    disabled={
                                        !canUpdate ||
                                        isAccepting ||
                                        isDismissing
                                    }
                                >
                                    {!isAccepting && (
                                        <IconCheck data-icon="inline-start" />
                                    )}
                                    {isAccepting ? "در حال ثبت…" : "ثبت تصمیم"}
                                </Button>
                            )}
                        </div>
                    )}
                </div>
            </div>
        </Alert>
    );
}

/** A legend with its two bulk actions on the same line. */
function LegendRow({
    legend,
    children,
}: {
    legend: string;
    children: React.ReactNode;
}) {
    return (
        <div className="flex items-center justify-between gap-2">
            <FieldLegend variant="legend">{legend}</FieldLegend>
            <div className="flex items-center gap-1">{children}</div>
        </div>
    );
}

/**
 * One field's claim, shown against what the record already says.
 *
 * The stored value is printed rather than hidden behind a toggle, because the
 * decision *is* a comparison and a comparison needs both sides. It is dimmed to
 * make the direction unambiguous: the proposal is what is being read, the
 * stored value is what it would replace.
 */
function FieldDecisionRow({
    config,
    proposed,
    stored,
    hasStored,
    checked,
    onToggle,
    disabled,
}: {
    config: SummaryFieldConfig;
    proposed: string | string[] | null;
    stored: string | string[] | null;
    hasStored: boolean;
    checked: boolean;
    onToggle: (on: boolean) => void;
    disabled: boolean;
}) {
    const Icon = config.icon;

    return (
        <Field orientation="horizontal" data-disabled={disabled || undefined}>
            <Checkbox
                checked={checked}
                onCheckedChange={(value) => onToggle(value === true)}
                disabled={disabled}
                aria-label={`پذیرش ${config.label}`}
            />
            <FieldContent>
                <FieldLabel>
                    <FieldTitle>
                        <span className="flex items-center gap-1.5">
                            <Icon />
                            {config.label}
                        </span>
                    </FieldTitle>
                </FieldLabel>
                <SummaryValueText value={proposed} isList={config.isList} />
                {hasStored && (
                    <FieldDescription>
                        <span className="font-medium">مقدار فعلی: </span>
                        <SummaryValueText
                            value={stored}
                            isList={config.isList}
                            muted
                        />
                    </FieldDescription>
                )}
            </FieldContent>
        </Field>
    );
}

/**
 * The tag set, as a full replacement rather than a diff.
 *
 * Pre-ticked with what the run proposed, because that is exactly the list the
 * request sends. The additions and removals against the meeting's current tags
 * are counted above the button, so somebody can see what a click will change
 * before clicking it — a full-sync list has no other way to make "untick one
 * tag" comprehensible.
 */
function TagDecision({
    tags,
    currentTags,
    isRunning,
    onToggle,
    onAll,
    onNone,
    disabled,
}: {
    tags: string[];
    currentTags: string[];
    isRunning: boolean;
    onToggle: (slug: string, on: boolean) => void;
    onAll: () => void;
    onNone: () => void;
    disabled: boolean;
}) {
    const { data: options = [] } = useTagOptions(undefined);

    const busy = disabled || isRunning;
    const nameBySlug = new Map(options.map((tag) => [tag.slug, tag.name]));

    /**
     * Only the tags this decision is actually about: what the run proposed,
     * and what the meeting already carries. Not the whole tag registry — the
     * request sends the meeting's tag set *in full*, so an unticked box means
     * "this meeting is not tagged that", and offering every tag in the hub made
     * that a statement about several hundred tags nobody was deciding anything
     * about. It also buried the only two lists that matter: the additions and
     * the removals. Adding a tag the run never suggested is a different job and
     * has its own place — the tags tab, which edits the meeting's tagging
     * directly.
     *
     * A slug the registry does not have yet still appears under its own name,
     * because the run's own suggestions have to be decidable and the accept
     * endpoint creates them.
     */
    const slugs = [...new Set([...tagSlugsOf(tags), ...currentTags])];

    return (
        <FieldSet>
            <LegendRow legend="برچسب‌های جلسه">
                <Button
                    variant="ghost"
                    size="sm"
                    onClick={onAll}
                    disabled={busy}
                >
                    انتخاب همه
                </Button>
                <Button
                    variant="ghost"
                    size="sm"
                    onClick={onNone}
                    disabled={busy}
                >
                    هیچ‌کدام
                </Button>
            </LegendRow>

            {isRunning ? (
                // The boxes are withheld rather than shown ticked, because
                // while a run is going the only thing on offer is the
                // meeting's *current* tags — a list that says nothing about
                // what the run proposed, next to a legend offering to change
                // it, is a control that cannot do what its buttons claim.
                <FieldDescription>
                    برچسب‌های پیشنهادی این اجرا پس از پایان همین‌جا می‌آیند.
                </FieldDescription>
            ) : slugs.length === 0 ? (
                <FieldDescription>
                    این اجرا برچسبی پیشنهاد نکرده است؛ تیک‌ها روی برچسب‌های فعلی
                    جلسه بسته می‌مانند.
                </FieldDescription>
            ) : (
                <div className="flex flex-wrap items-center gap-2">
                    {slugs.map((slug) => (
                        <Field
                            key={slug}
                            orientation="horizontal"
                            className="w-auto"
                            data-disabled={disabled || undefined}
                        >
                            <Checkbox
                                checked={tags.includes(slug)}
                                onCheckedChange={(value) =>
                                    onToggle(slug, value === true)
                                }
                                disabled={disabled}
                            />
                            <FieldLabel className="font-normal">
                                <Badge variant="outline">
                                    {nameBySlug.get(slug) ?? slug}
                                </Badge>
                                {!currentTags.includes(slug) && (
                                    <Badge variant="success">جدید</Badge>
                                )}
                            </FieldLabel>
                        </Field>
                    ))}
                </div>
            )}
        </FieldSet>
    );
}

/** One field's value: a paragraph or a bullet list, and never a blank. */
export function SummaryValueText({
    value,
    isList,
    muted = false,
}: {
    value: string | string[] | null;
    isList: boolean;
    muted?: boolean;
}) {
    if (isEmptyValue(value)) {
        return (
            <span className="text-muted-foreground text-xs">
                چیزی ثبت نشده است
            </span>
        );
    }

    if (isList) {
        return (
            <ul className="flex flex-col gap-1">
                {(value as string[]).map((item, index) => (
                    <li
                        // oxlint-disable-next-line react/no-array-index-key -- list items are model prose with no stable identity
                        key={index}
                        className={cn(
                            "flex items-start gap-1.5 text-sm leading-6",
                            muted && "text-muted-foreground",
                        )}
                    >
                        <IconPointFilled className="text-muted-foreground mt-1 shrink-0" />
                        <span>{item}</span>
                    </li>
                ))}
            </ul>
        );
    }

    return (
        <p
            className={cn(
                "text-sm leading-7",
                muted && "text-muted-foreground",
            )}
        >
            {value as string}
        </p>
    );
}
