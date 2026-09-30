import { useState } from "react";
import {
    IconAlertCircle,
    IconChevronDown,
    IconRobot,
    IconSparkles,
    IconTextScanAi,
} from "@tabler/icons-react";

import {
    Alert,
    AlertDescription,
    AlertTitle,
} from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { ResponsiveDialog } from "@/components/ui/responsive-dialog";
import {
    Field,
    FieldDescription,
    FieldLabel,
    FieldLegend,
    FieldSet,
} from "@/components/ui/field";
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { Spinner } from "@/components/ui/spinner";
import { ToggleGroup, ToggleGroupItem } from "@/components/ui/toggle-group";
import { Separator } from "@/components/ui/separator";
import { EmptyState } from "@/components/layout";
import { usePermission } from "@/features/auth/components/permission-guard";
import { PERMISSIONS } from "@/lib/permissions";
import { useAiModels } from "@/features/ai/hooks";
import {
    SUMMARY_FIELDS,
    SUMMARY_STATUS_VARIANTS,
    fieldLabel,
    reasonText,
    statusText,
} from "../summary-copy";
import { hasContent, readFields } from "../summary-read";
import { SummaryDecision, SummaryValueText } from "./summary-decision";
import type {
    MeetingSummaryArtifact,
    MeetingSummaryFieldName,
    SummaryWriteMode,
} from "../types";

/** The value the model picker stores for "whatever the agent is bound to". */
const AGENT_DEFAULT = "agent";

/**
 * Option labels keyed by the value that goes on the wire — and handed to
 * `SelectValue` as its `items` map, which is the point rather than redundancy.
 * Base UI resolves a select's displayed label from the items its `SelectItem`s
 * have registered, and the popup is not mounted until the menu opens, so on a
 * closed trigger nothing is registered and it falls back to printing the raw
 * value. The picker was showing `review` and `5` where it should have shown
 * «پیشنهاد بده» and the model's name.
 */
const MODE_LABELS: Record<SummaryWriteMode, string> = {
    review: "پیشنهاد بده",
    apply: "مستقیم بنویس",
};

const MODEL_DEFAULT_LABEL = "مدل پیش‌فرضِ ایجنت";

/** `provider/model` — the shape a run's attribution reads like. */
function modelLabel(model: {
    provider_name?: string | null;
    display_label: string;
}): string {
    return model.provider_name
        ? `${model.provider_name}/${model.display_label}`
        : model.display_label;
}

export interface SummaryRunPayload {
    fields?: MeetingSummaryFieldName[];
    mode?: SummaryWriteMode;
    model_id?: number | null;
    /** Reconcile with the stored record rather than writing the fields fresh. */
    merge?: boolean;
}

/**
 * The smart-analysis tab: what a run of the model produced for this meeting,
 * what it proposed that is still undecided, and the controls that start or
 * redo a run.
 *
 * A run *proposes*; it does not write. So this tab has two jobs that are not
 * the same job — showing what the meeting is, and offering an undecided claim
 * for a verdict — and keeping them apart is most of the design. The decision
 * panel is the only callout on the screen because it is the only thing on
 * screen that is a question.
 */
export function MeetingSummarySection({
    summary,
    tags,
    onGenerate,
    onAccept,
    isGenerating,
    isAccepting,
}: {
    summary: MeetingSummaryArtifact | null;
    tags: string[];
    onGenerate: (payload: SummaryRunPayload) => void;
    onAccept: (fields: MeetingSummaryFieldName[], tags: string[]) => void;
    isGenerating: boolean;
    isAccepting: boolean;
}) {
    const canUpdate = usePermission([PERMISSIONS.BOARD_MEETING_UPDATE]);

    const candidate = summary?.pending ?? null;
    const isInFlight = summary?.status === "pending";
    const hasSummary = hasContent(summary);

    return (
        <div className="flex flex-col gap-4">
            <div className="flex flex-wrap items-start justify-between gap-2">
                <RunControls
                    canRun={canUpdate}
                    isGenerating={isGenerating}
                    onGenerate={onGenerate}
                />

                {summary !== null && !isInFlight && (
                    <Badge variant={SUMMARY_STATUS_VARIANTS[summary.status]}>
                        {statusText(summary.status)}
                    </Badge>
                )}
            </div>

            {isInFlight && (
                <Alert>
                    <Spinner />
                    <AlertTitle>در حال تولید خلاصه</AlertTitle>
                    <AlertDescription>
                        نتیجه همین‌جا به‌روز می‌شود؛ لازم نیست صفحه را تازه کنید.
                    </AlertDescription>
                </Alert>
            )}

            {!isInFlight && summary?.error != null && (
                <Alert variant="destructive">
                    <IconAlertCircle />
                    <AlertTitle>تولید خلاصه انجام نشد</AlertTitle>
                    <AlertDescription>
                        {reasonText(summary.error)}
                    </AlertDescription>
                </Alert>
            )}

            {candidate !== null && (
                <SummaryDecision
                    current={summary ?? candidate}
                    candidate={candidate}
                    currentTags={tags}
                    onAccept={onAccept}
                    isAccepting={isAccepting}
                />
            )}

            {!isInFlight && candidate === null && !hasSummary && !isGenerating && (
                <EmptyState
                    icon={IconRobot}
                    message={
                        summary === null
                            ? "هنوز خلاصه‌ای برای این جلسه تولید نشده است."
                            : "این جلسه خلاصهٔ پذیرفته‌شده‌ای ندارد."
                    }
                />
            )}

            {hasSummary && (
                <div className="flex flex-col gap-5">
                    {readFields(summary)
                        // The tag field is deliberately not a content section
                        // here. `suggested_tags` records what a run proposed,
                        // while the meeting's tags live on the pivot, and after
                        // a review-then-accept the two are allowed to differ
                        // because a person may have unticked some. Printing the
                        // model's last proposal as if it were the meeting's
                        // tagging would put two different answers to "what is
                        // this tagged with" on one screen. The field keeps its
                        // regenerate action; the tags have their own tab.
                        .filter((reading) => reading.name !== "suggested_tags")
                        .map((reading) => {
                            const config = SUMMARY_FIELDS.find(
                                (f) => f.name === reading.name,
                            );

                            if (config === undefined) {
                                return null;
                            }

                            const Icon = config.icon;
                            // A field whose state says `ok` but whose timestamp
                            // is null is a value a later run could not replace,
                            // so what is on screen is older than the run that
                            // last reported. Worth saying rather than hiding.
                            const isStale =
                                reading.state?.status === "ok" &&
                                reading.state.generated_at === null;

                            return (
                                <section
                                    key={reading.name}
                                    className="flex flex-col gap-1.5"
                                >
                                    <div className="flex items-center gap-2">
                                        <h4 className="flex items-center gap-1.5 text-sm font-medium">
                                            <Icon />
                                            {config.label}
                                        </h4>

                                        {reading.state?.status === "failed" && (
                                            <Badge variant="outline">
                                                {reasonText(
                                                    reading.state.error,
                                                ) ?? "تولید نشد"}
                                            </Badge>
                                        )}

                                        {isStale && (
                                            <Badge variant="warning">
                                                <IconAlertCircle data-icon="inline-start" />
                                                از اجرای بعدی به‌روز نشده
                                            </Badge>
                                        )}

                                        {canUpdate && (
                                            <Button
                                                variant="ghost"
                                                size="icon-xs"
                                                className="ms-auto"
                                                disabled={isGenerating}
                                                title={`بازتولید ${config.label}`}
                                                onClick={() =>
                                                    onGenerate({
                                                        fields: [
                                                            reading.name as MeetingSummaryFieldName,
                                                        ],
                                                        mode: "review",
                                                    })
                                                }
                                            >
                                                <IconTextScanAi />
                                            </Button>
                                        )}
                                    </div>

                                    <div className="ps-6">
                                        {reading.isFilled ? (
                                            <SummaryValueText
                                                value={reading.value}
                                                isList={config.isList}
                                            />
                                        ) : (
                                            <p className="text-muted-foreground text-sm">
                                                {reading.state === null
                                                    ? "هنوز تولید نشده است."
                                                    : "این اجرا چیزی برای این بخش ننوشت."}
                                            </p>
                                        )}
                                    </div>
                                </section>
                            );
                        })}

                    <Separator />

                    <p className="text-muted-foreground text-xs">
                        {summary?.provider && summary.model
                            ? `مدل: ${summary.provider}/${summary.model}`
                            : "مدل اجرا مشخص نیست"}
                        {summary?.generated_at
                            ? ` · ${summary.generated_at}`
                            : ""}
                    </p>
                </div>
            )}
        </div>
    );
}

/**
 * Mode, model, and the two ways to ask for a run.
 *
 * The mode is a two-way choice, so it is a segmented `ToggleGroup` rather than
 * a dropdown: both answers are short, they are the difference between "I will
 * read this" and "overwrite it", and a menu that hides two options behind a
 * click is the wrong control for a decision somebody should be able to see
 * they are making.
 *
 * The model stays a `Select` because it can be twenty rows. It is only offered
 * to a user who may read the registry at all — the endpoint is behind
 * `ai.provider.view`, and a picker that appears and then fails is worse than no
 * picker.
 */
function RunControls({
    canRun,
    isGenerating,
    onGenerate,
}: {
    canRun: boolean;
    isGenerating: boolean;
    onGenerate: (payload: SummaryRunPayload) => void;
}) {
    const [mode, setMode] = useState<SummaryWriteMode[]>(["review"]);
    const [modelId, setModelId] = useState<string>(AGENT_DEFAULT);

    const canPickModel = usePermission([PERMISSIONS.AI_PROVIDER_VIEW]);
    const { data: models } = useAiModels(canPickModel);

    const modelLabels: Record<string, string> = {
        [AGENT_DEFAULT]: MODEL_DEFAULT_LABEL,
        ...Object.fromEntries(
            (models?.data ?? []).map((model) => [
                String(model.id),
                modelLabel(model),
            ]),
        ),
    };

    const selectedMode = (mode[0] ?? "review") as SummaryWriteMode;

    return (
        <div className="flex flex-wrap items-center gap-2">
            <ToggleGroup
                variant="outline"
                size="sm"
                value={mode}
                onValueChange={(value) => {
                    // Deselecting the only selected item would leave no mode at
                    // all, and the request requires one; keeping the last choice
                    // is the only reading that still means something.
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
                    // On the root, not on `SelectValue`: this is what tells
                    // Base UI to print the item label rather than its raw
                    // value. Without it a closed trigger shows `5`, because
                    // the popup items are not registered until it opens.
                    items={modelLabels}
                    onValueChange={(value) => {
                        if (value !== null) {
                            setModelId(value);
                        }
                    }}
                >
                    <SelectTrigger size="sm" className="w-64">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectGroup>
                            <SelectItem value={AGENT_DEFAULT}>
                                {MODEL_DEFAULT_LABEL}
                            </SelectItem>
                            {(models?.data ?? []).map((model) => (
                                <SelectItem
                                    key={model.id}
                                    value={String(model.id)}
                                >
                                    {modelLabel(model)}
                                </SelectItem>
                            ))}
                        </SelectGroup>
                    </SelectContent>
                </Select>
            )}

            <Button
                onClick={() =>
                    onGenerate({
                        mode: selectedMode,
                        model_id:
                            modelId === AGENT_DEFAULT ? null : Number(modelId),
                    })
                }
                disabled={!canRun || isGenerating}
            >
                {isGenerating ? (
                    <Spinner data-icon="inline-start" />
                ) : (
                    <IconSparkles data-icon="inline-start" />
                )}
                تولید خلاصه
            </Button>

            <RegenerateFields canRun={canRun} onGenerate={onGenerate} />
        </div>
    );
}

/**
 * D26's multi-field regeneration: pick the fields that came out wrong, pay for
 * those, leave the rest alone.
 *
 * Which is the point of the whole per-field design — a bad `key_decisions`
 * with a good `executive_summary` is an ordinary outcome, and re-running the
 * whole summary to fix it would throw away the part that was right. A full
 * regeneration is the separate button next to it, and this one starts empty
 * every time: a remembered selection would silently re-run fields the person
 * has since stopped caring about.
 */
function RegenerateFields({
    canRun,
    onGenerate,
}: {
    canRun: boolean;
    onGenerate: (payload: SummaryRunPayload) => void;
}) {
    const [open, setOpen] = useState(false);
    const [picked, setPicked] = useState<MeetingSummaryFieldName[]>([]);
    const [merge, setMerge] = useState(false);

    function submit() {
        if (picked.length === 0) {
            return;
        }

        // Always a proposal. This button is usually pressed *because* somebody
        // is reading the tab, and replacing the value under their cursor is the
        // one outcome that cannot be reviewed.
        onGenerate({ fields: picked, mode: "review", merge });
        setPicked([]);
        setMerge(false);
        setOpen(false);
    }

    return (
        <ResponsiveDialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);

                if (!next) {
                    setPicked([]);
                }
            }}
            title="بازتولید بخش‌های انتخابی"
            description="فقط همین بخش‌ها دوباره ساخته می‌شوند و به‌صورت پیشنهاد ثبت می‌شوند؛ بقیهٔ خلاصه دست‌نخورده می‌ماند."
            trigger={
                <Button variant="outline" disabled={!canRun}>
                    <IconChevronDown data-icon="inline-end" />
                    بازتولید بخش‌ها
                </Button>
            }
            footer={
                <>
                    <Button variant="outline" onClick={() => setOpen(false)}>
                        انصراف
                    </Button>
                    <Button onClick={submit} disabled={picked.length === 0}>
                        بازتولید
                        {picked.length > 0 &&
                            ` (${picked.length.toLocaleString("fa-IR")})`}
                    </Button>
                </>
            }
        >
            <FieldSet>
                <FieldLegend variant="label">بخش‌ها</FieldLegend>
                <div className="flex flex-col gap-2">
                    {SUMMARY_FIELDS.map((field) => {
                        const Icon = field.icon;

                        return (
                            <Field key={field.name} orientation="horizontal">
                                <Checkbox
                                    checked={picked.includes(field.name)}
                                    onCheckedChange={(checked) =>
                                        setPicked((prev) =>
                                            checked === true
                                                ? [...prev, field.name]
                                                : prev.filter(
                                                      (f) =>
                                                          f !== field.name,
                                                  ),
                                        )
                                    }
                                />
                                <FieldLabel className="font-normal">
                                    <span className="flex items-center gap-2">
                                        <Icon />
                                        {fieldLabel(field.name)}
                                    </span>
                                </FieldLabel>
                            </Field>
                        );
                    })}
                </div>
                {picked.length === 0 && (
                    <FieldDescription>
                        دست‌کم یک بخش را انتخاب کنید.
                    </FieldDescription>
                )}

                {/*
                    The two ways to re-run a field are different requests, not
                    a difficulty setting, so they are two named choices rather
                    than a checkbox whose meaning depends on what is ticked
                    above. Merging is the one that needs the record to already
                    hold something, so it is offered anyway and the server
                    simply reconciles against nothing on a first run.
                */}
                <Field orientation="horizontal">
                    <Checkbox
                        checked={merge}
                        onCheckedChange={(checked) =>
                            setMerge(checked === true)
                        }
                    />
                    <FieldLabel className="font-normal">
                        <span className="flex flex-col gap-0.5">
                            <span>ترکیب با نسخهٔ فعلی</span>
                            <FieldDescription>
                                {merge
                                    ? "مدل نسخهٔ پذیرفته‌شده را هم می‌بیند و آن را با صورتجلسه تطبیق می‌دهد."
                                    : "فیلدهای انتخابی از نو و فقط از روی صورتجلسه نوشته می‌شوند."}
                            </FieldDescription>
                        </span>
                    </FieldLabel>
                </Field>
            </FieldSet>
        </ResponsiveDialog>
    );
}
