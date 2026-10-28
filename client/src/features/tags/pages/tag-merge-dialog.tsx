import { useEffect, useState } from "react";
import { IconCheck } from "@tabler/icons-react";

import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import { ScrollArea } from "@/components/ui/scroll-area";
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
export function TagMergeDialog({
    open,
    onOpenChange,
    proposal,
    isAccepting,
    onAccept,
}: TagMergeDialogProps) {
    const [selected, setSelected] = useState<Set<number>>(new Set());

    // Re-seed on every new proposal. Depending on `proposal` rather than the
    // derived list is what makes this run once per run, not once per render.
    useEffect(() => {
        setSelected(
            new Set((proposal?.groups ?? []).map((_, index) => index)),
        );
    }, [proposal]);

    const groups = proposal?.groups ?? [];
    const nothingToMerge = groups.length === 0;

    const toggle = (index: number) => {
        setSelected((current) => {
            const next = new Set(current);
            if (next.has(index)) {
                next.delete(index);
            } else {
                next.add(index);
            }
            return next;
        });
    };

    const accept = () => {
        onAccept(
            groups
                .filter((_, index) => selected.has(index))
                .map((group) => ({
                    canonical: group.canonical.slug,
                    duplicates: group.duplicates.map((tag) => tag.slug),
                })),
        );
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>ادغام برچسب‌های هم‌معنا</DialogTitle>
                    <DialogDescription>
                        برچسب‌هایی که به یک مفهوم اشاره می‌کنند در یک برچسب جمع
                        می‌شوند؛ اتصال‌های بقیه به برچسب باقی‌مانده منتقل و
                        خودشان حذف می‌شوند.
                    </DialogDescription>
                </DialogHeader>

                {nothingToMerge ? (
                    <p className="text-muted-foreground py-6 text-center text-sm">
                        برچسب هم‌معنایی برای ادغام پیدا نشد.
                    </p>
                ) : (
                    <ScrollArea className="max-h-[60vh] pe-3">
                        <ul className="grid gap-3">
                            {groups.map((group, index) => (
                                <li
                                    key={group.canonical.slug}
                                    className="rounded-lg border p-3"
                                >
                                    <label className="flex items-start gap-3">
                                        <Checkbox
                                            className="mt-1"
                                            checked={selected.has(index)}
                                            onCheckedChange={() =>
                                                toggle(index)
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
                                                {group.duplicates.map(
                                                    (tag) => (
                                                        <Badge
                                                            key={tag.slug}
                                                            variant="secondary"
                                                            className="line-through"
                                                        >
                                                            {tag.name}
                                                        </Badge>
                                                    ),
                                                )}
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
                    </ScrollArea>
                )}

                <DialogFooter>
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
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
