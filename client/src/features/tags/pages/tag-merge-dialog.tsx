import { useEffect, useState } from "react";
import {
    IconArrowRight,
    IconCheck,
    IconChevronDown,
    IconInfoCircle,
    IconSparkles,
    IconTargetArrow,
} from "@tabler/icons-react";

import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { ResponsiveDialog } from "@/components/ui/responsive-dialog";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { Spinner } from "@/components/ui/spinner";
import type { AcceptTagMergeGroup, TagMergeProposalDto } from "../types";

interface TagMergeDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    proposal: TagMergeProposalDto | null;
    isAccepting: boolean;
    onAccept: (groups: AcceptTagMergeGroup[]) => void;
}

/**
 * The Review half of tag consolidation (D22/D25's propose-then-accept shape).
 *
 * The dialog is purely a decision surface: it holds the selection, not the
 * proposal. Everything it shows arrived in the response to "propose", and what
 * it hands back is the chosen groups as slugs — the server re-validates them,
 * so a stale group fails closed rather than merging something unintended.
 *
 * Every group starts selected. Accepting all of it is the common case, and
 * un-ticking one is cheaper than ticking many.
 *
 * The footer states the consequence before it is agreed to — how many tags
 * disappear and how many attachments move — because «ادغام» alone does not say
 * that a group's duplicates cease to exist. The explanation is derived from the
 * same selection the button acts on, so the two cannot disagree.
 */
export function toAcceptPayload(
    groups: NonNullable<TagMergeProposalDto["groups"]>,
    selectedSlugs: ReadonlySet<string>,
    canonicalOverrides?: Readonly<Record<string, string>>,
): AcceptTagMergeGroup[] {
    return groups
        .filter((group) => selectedSlugs.has(group.canonical.slug))
        .map((group) => {
            // A group whose keeper was changed by hand: the chosen tag stays
            // and every other member — the model's canonical included — folds
            // into it. The server re-validates all of them either way.
            const chosen = canonicalOverrides?.[group.canonical.slug];

            if (chosen !== undefined && chosen !== group.canonical.slug) {
                return {
                    canonical: chosen,
                    duplicates: [
                        group.canonical.slug,
                        ...group.duplicates
                            .map((tag) => tag.slug)
                            .filter((slug) => slug !== chosen),
                    ],
                };
            }

            return {
                canonical: group.canonical.slug,
                duplicates: group.duplicates.map((tag) => tag.slug),
            };
        });
}

export function TagMergeDialog({
    open,
    onOpenChange,
    proposal,
    isAccepting,
    onAccept,
}: TagMergeDialogProps) {
    const [selected, setSelected] = useState<Set<string>>(new Set());
    const [overrides, setOverrides] = useState<Record<string, string>>({});

    // Re-seed on every new proposal. Depending on `proposal` rather than the
    // derived list is what makes this run once per run, not once per render.
    useEffect(() => {
        setSelected(
            new Set((proposal?.groups ?? []).map((group) => group.canonical.slug)),
        );
        setOverrides({});
    }, [proposal]);

    const groups = proposal?.groups ?? [];
    const nothingToMerge = groups.length === 0;

    const toggle = (slug: string) => {
        setSelected((current) => {
            const next = new Set(current);
            if (next.has(slug)) {
                next.delete(slug);
            } else {
                next.add(slug);
            }
            return next;
        });
    };

    const chosen = groups.filter((group) => selected.has(group.canonical.slug));
    // Counted from the payload the button sends, not from the model's proposal:
    // a group whose keeper was swapped removes one more tag and moves that
    // tag's attachments too, and the footer must not disagree with the act.
    const payload = toAcceptPayload(groups, selected, overrides);
    const tagsRemoved = payload.reduce(
        (total, group) => total + group.duplicates.length,
        0,
    );
    const tagsKept = payload.filter(
        (group) => group.duplicates.length > 0,
    ).length;
    const attachmentsMoving = chosen.reduce((total, group) => {
        const keep = overrides[group.canonical.slug] ?? group.canonical.slug;
        const members = [group.canonical, ...group.duplicates];

        return (
            total +
            members.reduce(
                (sum, tag) =>
                    tag.slug === keep ? sum : sum + (tag.usage_count ?? 0),
                0,
            )
        );
    }, 0);

    const accept = () => {
        onAccept(toAcceptPayload(groups, selected, overrides));
    };

    return (
        <ResponsiveDialog
            open={open}
            onOpenChange={onOpenChange}
            size="2xl"
            title="ادغام برچسب‌های هم‌معنا"
            description="برچسب‌هایی که به یک مفهوم اشاره می‌کنند در یک برچسب جمع می‌شوند؛ اتصال‌های بقیه به برچسب باقی‌مانده منتقل و خودشان حذف می‌شوند."
            footer={
                <>
                    {!nothingToMerge && (
                        <output className="text-muted-foreground me-auto text-xs">
                            {tagsRemoved > 0
                                ? `${tagsKept.toLocaleString("fa-IR")} برچسب می‌ماند، ${tagsRemoved.toLocaleString("fa-IR")} برچسب حذف و ${attachmentsMoving.toLocaleString("fa-IR")} اتصال منتقل می‌شود.`
                                : "هیچ گروهی برای ادغام انتخاب نشده است."}
                        </output>
                    )}
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                        disabled={isAccepting}
                    >
                        انصراف
                    </Button>
                    {!nothingToMerge && (
                        <Button
                            type="button"
                            onClick={accept}
                            disabled={isAccepting || selected.size === 0}
                        >
                            {isAccepting ? (
                                <Spinner />
                            ) : (
                                <IconCheck className="size-4" />
                            )}
                            ادغام {selected.size.toLocaleString("fa-IR")} گروه
                        </Button>
                    )}
                </>
            }
        >
            {nothingToMerge ? (
                <div className="flex flex-col items-center gap-2 py-8 text-center">
                    <span className="bg-success/10 text-success grid size-10 place-items-center rounded-full">
                        <IconSparkles className="size-5" />
                    </span>
                    <p className="text-sm font-medium">
                        برچسب هم‌معنایی برای ادغام پیدا نشد.
                    </p>
                    <p className="text-muted-foreground max-w-sm text-xs">
                        واژه‌نامه در وضعیت یکدستی است؛ هر وقت برچسب تازه‌ای شبیه
                        به یکی از برچسب‌های موجود ساخته شد، اینجا پیشنهاد می‌شود.
                    </p>
                </div>
            ) : (
                <div className="flex flex-col gap-3">
                    {proposal !== null && (
                        <p className="text-muted-foreground flex flex-wrap items-center gap-1.5 text-xs">
                            <IconInfoCircle className="size-3.5" />
                            پیشنهاد
                            <span dir="ltr" className="font-medium">
                                {proposal.provider}
                                {proposal.model ? `/${proposal.model}` : ""}
                            </span>
                            است آن هم بر پایهٔ فهرست فعلی برچسب‌ها؛ پذیرفتن هر
                            گروه را سرور دوباره با وضعیت زنده بررسی می‌کند.
                        </p>
                    )}
                    {proposal?.term ? (
                        <p className="text-muted-foreground flex flex-wrap items-center gap-1.5 text-xs">
                            <IconTargetArrow className="size-3.5" />
                            فقط برچسب‌های مرتبط با
                            <span className="text-foreground font-medium">
                                «{proposal.term}»
                            </span>
                            بررسی شده‌اند؛ بقیهٔ واژه‌نامه در این پیشنهاد نیست.
                        </p>
                    ) : null}

                    <ul className="grid gap-2">
                        {groups.map((group) => (
                            <li
                                key={group.canonical.slug}
                                className="hover:border-ring rounded-lg border transition-colors"
                            >
                                <label className="flex cursor-pointer items-start gap-3 p-3">
                                    <Checkbox
                                        className="mt-1"
                                        checked={selected.has(
                                            group.canonical.slug,
                                        )}
                                        onCheckedChange={() =>
                                            toggle(group.canonical.slug)
                                        }
                                    />
                                    <GroupBody
                                        group={group}
                                        canonical={
                                            overrides[group.canonical.slug] ??
                                            group.canonical.slug
                                        }
                                        onCanonicalChange={(value) =>
                                            setOverrides((current) => ({
                                                ...current,
                                                [group.canonical.slug]: value,
                                            }))
                                        }
                                    />
                                </label>
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </ResponsiveDialog>
    );
}

function GroupBody({
    group,
    canonical,
    onCanonicalChange,
}: {
    group: NonNullable<TagMergeProposalDto["groups"]>[number];
    canonical: string;
    onCanonicalChange: (slug: string) => void;
}) {
    const [open, setOpen] = useState(false);

    const members = [group.canonical, ...group.duplicates];
    const keeper =
        members.find((tag) => tag.slug === canonical) ?? group.canonical;
    const removed = members.filter((tag) => tag.slug !== keeper.slug);
    const swapped = keeper.slug !== group.canonical.slug;

    return (
        <div className="grid min-w-0 flex-1 gap-2">
            <div className="flex flex-wrap items-center gap-2">
                <Badge>{keeper.name}</Badge>
                <span className="text-muted-foreground text-xs">
                    {(keeper.usage_count ?? 0).toLocaleString("fa-IR")} استفاده
                </span>
                <IconArrowRight className="text-muted-foreground size-3.5" />
                {removed.map((tag) => (
                    <Badge
                        key={tag.slug}
                        variant="secondary"
                        className="line-through"
                    >
                        {tag.name}
                    </Badge>
                ))}
                {removed.length > 1 && (
                    <span className="text-muted-foreground text-xs">
                        {removed.length.toLocaleString("fa-IR")} برچسب
                    </span>
                )}
                {swapped && (
                    <Badge variant="warning" className="text-[10px]">
                        برچسب باقی‌مانده تغییر کرد
                    </Badge>
                )}
            </div>

            {group.reason !== "" && (
                <p className="text-muted-foreground text-xs">{group.reason}</p>
            )}

            <div className="flex flex-wrap items-center gap-2">
                {removed.length > 0 && (
                    <span className="text-muted-foreground inline-flex items-center gap-1 text-xs">
                        {removed
                            .reduce(
                                (sum, tag) => sum + (tag.usage_count ?? 0),
                                0,
                            )
                            .toLocaleString("fa-IR")}
                        اتصال منتقل می‌شود
                    </span>
                )}
                {members.length > 1 && (
                    <Select
                        value={keeper.slug}
                        onValueChange={(value) => {
                            if (value) {
                                onCanonicalChange(value);
                            }
                        }}
                    >
                        <SelectTrigger
                            size="sm"
                            className="text-muted-foreground h-6 w-auto gap-1 px-1.5 text-xs"
                            aria-label="انتخاب برچسب باقی‌مانده"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {members.map((tag) => (
                                <SelectItem key={tag.slug} value={tag.slug}>
                                    {tag.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                )}
                <Button
                    type="button"
                    variant="ghost"
                    size="xs"
                    className="text-muted-foreground h-6 px-1.5 text-xs"
                    onClick={(event) => {
                        // The whole card is a label for the checkbox; a click on
                        // this button must not also tick the group.
                        event.preventDefault();
                        setOpen((value) => !value);
                    }}
                >
                    جزئیات
                    <IconChevronDown
                        className={
                            open
                                ? "size-3.5 rotate-180 transition-transform"
                                : "size-3.5 transition-transform"
                        }
                    />
                </Button>
            </div>

            {open && (
                <dl className="bg-muted/40 grid gap-1 rounded-md p-2 text-xs">
                    <div className="flex items-center justify-between gap-2">
                        <dt className="text-muted-foreground">برچسب می‌ماند</dt>
                        <dd dir="ltr" className="font-medium">
                            {group.canonical.slug}
                        </dd>
                    </div>
                    <div className="flex items-start justify-between gap-2">
                        <dt className="text-muted-foreground">حذف می‌شوند</dt>
                        <dd dir="ltr" className="text-end">
                            {group.duplicates.map((tag) => tag.slug).join("، ")}
                        </dd>
                    </div>
                </dl>
            )}
        </div>
    );
}
