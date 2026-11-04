import { useEffect, useState } from "react";
import { IconCheck } from "@tabler/icons-react";

import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { ResponsiveDialog } from "@/components/ui/responsive-dialog";
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
 */
export function toAcceptPayload(
    groups: NonNullable<TagMergeProposalDto["groups"]>,
    selectedSlugs: ReadonlySet<string>,
): AcceptTagMergeGroup[] {
    return groups
        .filter((group) => selectedSlugs.has(group.canonical.slug))
        .map((group) => ({
            canonical: group.canonical.slug,
            duplicates: group.duplicates.map((tag) => tag.slug),
        }));
}

export function TagMergeDialog({
    open,
    onOpenChange,
    proposal,
    isAccepting,
    onAccept,
}: TagMergeDialogProps) {
    const [selected, setSelected] = useState<Set<string>>(new Set());

    // Re-seed on every new proposal. Depending on `proposal` rather than the
    // derived list is what makes this run once per run, not once per render.
    useEffect(() => {
        setSelected(
            new Set((proposal?.groups ?? []).map((group) => group.canonical.slug)),
        );
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

    const accept = () => {
        onAccept(toAcceptPayload(groups, selected));
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
                <p className="text-muted-foreground py-6 text-center text-sm">
                    برچسب هم‌معنایی برای ادغام پیدا نشد.
                </p>
            ) : (
                <ul className="grid gap-3">
                    {groups.map((group) => (
                        <li
                            key={group.canonical.slug}
                            className="rounded-lg border p-3"
                        >
                            <label className="flex items-start gap-3">
                                <Checkbox
                                    className="mt-1"
                                    checked={selected.has(group.canonical.slug)}
                                    onCheckedChange={() =>
                                        toggle(group.canonical.slug)
                                    }
                                />
                                <div className="grid gap-2">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <Badge>
                                            {group.canonical.name}
                                        </Badge>
                                        <span className="text-muted-foreground text-xs">
                                            {(
                                                group.canonical
                                                    .usage_count ?? 0
                                            ).toLocaleString("fa-IR")}{" "}
                                            استفاده
                                        </span>
                                        <span className="text-muted-foreground">
                                            ←
                                        </span>
                                        {group.duplicates.map((tag) => (
                                            <Badge
                                                key={tag.slug}
                                                variant="secondary"
                                                className="line-through"
                                            >
                                                {tag.name}
                                            </Badge>
                                        ))}
                                    </div>
                                    {group.reason !== "" && (
                                        <p className="text-muted-foreground text-xs">
                                            {group.reason}
                                        </p>
                                    )}
                                </div>
                            </label>
                        </li>
                    ))}
                </ul>
            )}
        </ResponsiveDialog>
    );
}
