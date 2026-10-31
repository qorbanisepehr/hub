import { useEffect, useMemo, useState } from "react";

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
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group";
import { ScrollArea } from "@/components/ui/scroll-area";
import { Spinner } from "@/components/ui/spinner";

import type { AcceptTagMergeGroup, TagDto } from "../types";

export interface TagMergeSelection {
    canonical: TagDto | null;
    duplicates: TagDto[];
    canonicalLabel: string;
    duplicateLabels: readonly string[];
    selected: TagDto[];
}

export function resolveTagMergeSelection(
    tags: readonly TagDto[],
    canonicalSlug: string,
    duplicateSlugs: ReadonlySet<string>,
): TagMergeSelection {
    const canonical = tags.find((tag) => tag.slug === canonicalSlug) ?? null;
    const duplicateList = tags.filter(
        (tag) => duplicateSlugs.has(tag.slug) && tag.slug !== canonicalSlug,
    );
    return {
        canonical,
        duplicates: duplicateList,
        canonicalLabel: canonical?.name ?? canonicalSlug,
        duplicateLabels: duplicateList.map((tag) => tag.name),
        selected: canonical ? [canonical, ...duplicateList] : duplicateList,
    };
}

export function filterTags(
    tags: readonly TagDto[],
    query: string,
): TagDto[] {
    const q = query.trim().toLowerCase();
    if (!q) return [...tags];
    return tags.filter(
        (tag) =>
            tag.name.toLowerCase().includes(q) ||
            tag.slug.toLowerCase().includes(q),
    );
}

export function mergeSelectionToGroup(
    selection: TagMergeSelection,
): AcceptTagMergeGroup | null {
    if (selection.duplicates.length === 0 || selection.canonical === null) return null;
    return {
        canonical: selection.canonical.slug,
        duplicates: selection.duplicates.map((tag) => tag.slug),
    };
}

interface ManualTagMergeDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    tags: TagDto[];
    isAccepting: boolean;
    mode?: "manual" | "bulk" | "single";
    onAccept: (groups: AcceptTagMergeGroup[]) => void;
    defaultCanonicalSlug?: string | null;
    defaultDuplicateSlugs?: readonly string[] | null;
    singleSourceSlug?: string | null;
    onSelectionChange?: (selection: TagMergeSelection) => void;
}

export function ManualTagMergeDialog({
    open,
    onOpenChange,
    tags,
    isAccepting,
    mode = "manual",
    onAccept,
    defaultCanonicalSlug,
    defaultDuplicateSlugs,
    singleSourceSlug,
    onSelectionChange,
}: ManualTagMergeDialogProps) {
    const [query, setQuery] = useState("");
    const [canonical, setCanonical] = useState("");
    const [selected, setSelected] = useState<Set<string>>(new Set());

    const isSingle = Boolean(singleSourceSlug);

    useEffect(() => {
        if (!open) return;
        setQuery("");
        if (isSingle) {
            setCanonical(
                defaultCanonicalSlug && tags.some((tag) => tag.slug === defaultCanonicalSlug)
                    ? defaultCanonicalSlug
                    : "",
            );
            setSelected(new Set());
            return;
        }
        const highestUsageCanonical = tags
            .toSorted((a, b) => (b.usage_count ?? 0) - (a.usage_count ?? 0))[0]?.slug ?? "";
        const preferredCanonical =
            defaultCanonicalSlug && tags.some((tag) => tag.slug === defaultCanonicalSlug)
                ? defaultCanonicalSlug
                : mode === "bulk" && highestUsageCanonical
                    ? highestUsageCanonical
                    : tags[0]?.slug ?? "";
        setCanonical(preferredCanonical);

        if (mode === "bulk") {
            setSelected(
                new Set(
                    tags
                        .map((tag) => tag.slug)
                        .filter((slug) => slug !== preferredCanonical),
                ),
            );
        } else {
            setSelected(
                new Set(
                    (defaultDuplicateSlugs ?? [])
                        .filter((slug): slug is string => typeof slug === "string")
                        .filter((slug) => slug !== preferredCanonical),
                ),
            );
        }
    }, [open, defaultCanonicalSlug, defaultDuplicateSlugs, tags, mode, isSingle]);

    const filtered = useMemo(() => filterTags(tags, query), [tags, query]);

    const singleSourceTag = useMemo(
        () => tags.find((tag) => tag.slug === singleSourceSlug) ?? null,
        [tags, singleSourceSlug],
    );

    const singleCandidates = useMemo(
        () => filtered.filter((tag) => tag.slug !== singleSourceSlug),
        [filtered, singleSourceSlug],
    );

    const canonicalTag = useMemo(
        () => tags.find((tag) => tag.slug === canonical) ?? null,
        [tags, canonical],
    );
    const candidateTags = useMemo(
        () => filtered.filter((tag) => tag.slug !== canonical),
        [filtered, canonical],
    );

    useEffect(() => {
        if (mode !== "bulk" || isSingle) return;
        setSelected(
            new Set(
                tags
                    .map((tag) => tag.slug)
                    .filter((slug) => slug !== canonical),
            ),
        );
    }, [mode, canonical, tags, isSingle]);

    useEffect(() => {
        if (mode === "bulk" || isSingle) return;
        setSelected((current) => {
            if (!current.has(canonical)) return current;
            const next = new Set(current);
            next.delete(canonical);
            return next;
        });
    }, [canonical, mode, isSingle]);

    const selection = useMemo(
        () => resolveTagMergeSelection(tags, canonical, selected),
        [tags, canonical, selected],
    );

    useEffect(() => {
        if (isSingle) {
            const targetTag = canonicalTag;
            const duplicates = singleSourceTag ? [singleSourceTag] : [];
            onSelectionChange?.({
                canonical: targetTag,
                duplicates,
                canonicalLabel: targetTag?.name ?? canonical,
                duplicateLabels: duplicates.map((tag) => tag.name),
                selected: targetTag ? [targetTag, ...duplicates] : duplicates,
            });
            return;
        }
        onSelectionChange?.(selection);
    }, [isSingle, canonical, canonicalTag, singleSourceTag, onSelectionChange, selection]);

    const handleCheckedChange = (slug: string, checked: boolean) => {
        setSelected((current) => {
            const next = new Set(current);
            if (checked) next.add(slug);
            else next.delete(slug);
            return next;
        });
    };

    const handleAccept = () => {
        if (isSingle) {
            if (canonical) onAccept([{ canonical, duplicates: [singleSourceSlug as string] }]);
            return;
        }
        const group = mergeSelectionToGroup(selection);
        if (group) onAccept([group]);
    };

    const submitDisabled = isSingle
        ? canonical === "" || isAccepting
        : selection.duplicates.length === 0 || canonical === "" || isAccepting;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {isSingle
                            ? "ادغام برچسب جاری"
                            : mode === "bulk"
                              ? "ادغام برچسب‌های انتخاب‌شده"
                              : "ادغام برچسب‌ها"}
                    </DialogTitle>
                    <DialogDescription>
                        {isSingle
                            ? "برچسب جاری را به یک برچسب دیگر ادغام کنید؛ برچسب جاری حذف و ارجاع‌هایش منتقل می‌شوند."
                            : mode === "bulk"
                              ? "همهٔ برچسب‌های انتخاب‌شده در یک برچسب مقصد ادغام می‌شوند."
                              : "یک برچسب مقصد برگزینید و برچسب‌های تکراری را به آن ادغام کنید."}
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-4 py-2">
                    {isSingle ? (
                        <>
                            <div className="rounded-md border bg-muted/40 p-3">
                                <p className="text-sm font-medium">
                                    برچسب جاری: {singleSourceTag?.name ?? singleSourceSlug}
                                </p>
                                <p dir="ltr" className="text-xs text-muted-foreground font-mono">
                                    {singleSourceSlug}
                                </p>
                            </div>

                            <div className="grid gap-2">
                                <div className="relative">
                                    <div className="pointer-events-none absolute start-3 top-1/2 -translate-y-1/2">
                                        <svg className="size-4 text-muted-foreground" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                            <path d="m21 21-4.35-4.35M11 18a7 7 0 1 1 0-14 7 7 0 0 1 0 14Z" stroke="currentColor" strokeWidth="2" strokeLinecap="round" />
                                        </svg>
                                    </div>
                                    <Input
                                        value={query}
                                        onChange={(e) => setQuery(e.target.value)}
                                        placeholder="جستجوی عنوان یا شناسه…"
                                        className="ps-9"
                                        dir="ltr"
                                    />
                                </div>

                                <div className="rounded-md border bg-card">
                                    <ScrollArea className="h-64 w-full">
                                        {singleCandidates.length === 0 ? (
                                            <p className="text-muted-foreground py-4 text-center text-sm">
                                                نتیجه‌ای یافت نشد.
                                            </p>
                                        ) : (
                                            <RadioGroup
                                                value={canonical}
                                                onValueChange={(value) => {
                                                    if (value) setCanonical(value);
                                                }}
                                                className="grid gap-1 p-2"
                                            >
                                                {singleCandidates.map((tag) => (
                                                    <div
                                                        key={tag.slug}
                                                        className="flex items-center gap-3 rounded-md px-2 py-1.5 hover:bg-muted/40"
                                                    >
                                                        <RadioGroupItem
                                                            value={tag.slug}
                                                            id={`single-tag-${tag.slug}`}
                                                        />
                                                        <Label
                                                            htmlFor={`single-tag-${tag.slug}`}
                                                            className="flex flex-1 cursor-pointer items-center gap-3"
                                                        >
                                                            <span className="truncate font-medium">
                                                                {tag.name}
                                                            </span>
                                                            <span
                                                                dir="ltr"
                                                                className="ms-auto text-xs text-muted-foreground font-mono shrink-0"
                                                            >
                                                                {tag.slug}
                                                            </span>
                                                        </Label>
                                                    </div>
                                                ))}
                                            </RadioGroup>
                                        )}
                                    </ScrollArea>
                                </div>
                            </div>
                        </>
                    ) : (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="manual-canonical">برچسب مقصد</Label>
                                <Select value={canonical} onValueChange={(value) => { if (value) setCanonical(value); }}>
                                    <SelectTrigger id="manual-canonical" className="w-full">
                                        <SelectValue placeholder="برچسب مقصد…" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {tags.map((tag) => (
                                            <SelectItem key={tag.slug} value={tag.slug}>
                                                <div className="flex flex-col">
                                                    <span>{tag.name}</span>
                                                    <span className="text-xs text-muted-foreground" dir="ltr">
                                                        {tag.slug}
                                                    </span>
                                                </div>
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="grid gap-2">
                                <div className="relative">
                                    <div className="pointer-events-none absolute start-3 top-1/2 -translate-y-1/2">
                                        <svg className="size-4 text-muted-foreground" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                            <path d="m21 21-4.35-4.35M11 18a7 7 0 1 1 0-14 7 7 0 0 1 0 14Z" stroke="currentColor" strokeWidth="2" strokeLinecap="round" />
                                        </svg>
                                    </div>
                                    <Input
                                        value={query}
                                        onChange={(e) => setQuery(e.target.value)}
                                        placeholder="جستجوی عنوان یا شناسه…"
                                        className="ps-9"
                                        dir="ltr"
                                    />
                                </div>

                                <div className="rounded-md border bg-card">
                                    <ScrollArea className="h-64 w-full">
                                        <ul className="grid gap-2 p-2">
                                            {candidateTags.length === 0 ? (
                                                <li className="text-muted-foreground py-4 text-center text-sm">
                                                    نتیجه‌ای یافت نشد.
                                                </li>
                                            ) : (
                                                candidateTags.map((tag) => {
                                                    const checked = selected.has(tag.slug);

                                                    return (
                                                        <li
                                                            key={tag.slug}
                                                            className="flex items-center gap-3 rounded-md px-2 py-1.5 hover:bg-muted/40"
                                                        >
                                                            {mode === "manual" ? (
                                                                <Checkbox
                                                                    checked={checked}
                                                                    onCheckedChange={() => handleCheckedChange(tag.slug, !checked)}
                                                                    aria-label={tag.name}
                                                                />
                                                            ) : null}
                                                            <div className="flex min-w-0 flex-1 items-baseline gap-2">
                                                                <span className="truncate font-medium">
                                                                    {tag.name}
                                                                </span>
                                                                <span
                                                                    dir="ltr"
                                                                    className="text-xs text-muted-foreground font-mono shrink-0"
                                                                >
                                                                    {tag.slug}
                                                                </span>
                                                            </div>
                                                        </li>
                                                    );
                                                })
                                            )}
                                        </ul>
                                    </ScrollArea>
                                </div>
                            </div>

                            <p className="text-muted-foreground text-sm">
                                مقصد: {canonicalTag?.name ?? "—"} · برای ادغام:{" "}
                                {selection.duplicates.length}
                            </p>
                        </>
                    )}
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                        disabled={isAccepting}
                    >
                        انصراف
                    </Button>
                    <Button type="button" onClick={handleAccept} disabled={submitDisabled}>
                        {isAccepting ? <Spinner /> : null}
                        ادغام
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}