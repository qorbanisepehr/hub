import { useMemo } from "react";
import type { AnyFieldApi } from "@tanstack/react-form";
import { IconLoader2 } from "@tabler/icons-react";

import { Field, FieldError, FieldLabel } from "@/components/ui/field";
import { Button } from "@/components/ui/button";
import {
    Cascader,
    CascaderBreadcrumb,
    CascaderContent,
    CascaderEmpty,
    CascaderInput,
    CascaderItems,
    CascaderList,
    CascaderNav,
    CascaderPanel,
    CascaderStatus,
    CascaderTrigger,
    CascaderValue,
    type CascaderLabels,
    type CascaderNode,
} from "@/components/reui/cascader";
import { cn } from "@/lib/utils";
import { fetchFormOptionsByGroup } from "@/features/form-options/api";
import {
    useFormOptionsByGroup,
    useResolvedFormOptions,
} from "@/features/form-options/hooks/use-form-options";

/** Qualifies a plain child value in the tree: «{parentValue}::{childValue}». */
const HIERARCHY_SEPARATOR = "::";

/**
 * How the CHILD field stores its value:
 *
 * - `"prefixed"` (places): the child option's own value is globally unique and
 *   already embeds its parent («{province}-{cityCode}»). The tree uses it
 *   as-is, one field can hold the whole thing (`combinedField`), and the
 *   parent is re-derived by splitting the stored value.
 * - `"plain"` (e.g. دین/مذهب): the child option stores its own bare value,
 *   which may collide with a parent option's value (مذهب `jewish` == دین
 *   `jewish`). Child nodes are therefore qualified in the tree as
 *   «{parentValue}::{childValue}» and the parent lives in its own field.
 */
export type HierarchyChildValueMode = "plain" | "prefixed";

type OptionHierarchyFieldProps = {
    parentGroup: string;
    childGroup: string;
    parentLabel: string;
    childLabel: string;
    /** Two-field mode: the field bound to the parent option's value. */
    parentField?: AnyFieldApi;
    /** Two-field mode: the field bound to the child option's value. */
    childField?: AnyFieldApi;
    /**
     * `childValueMode: "prefixed"` only: ONE field storing the whole child
     * option value (the combined place string). Pass INSTEAD of the two
     * separate fields.
     */
    combinedField?: AnyFieldApi;
    childValueMode: HierarchyChildValueMode;
    label?: string;
    placeholder?: string;
    disabled?: boolean;
    /**
     * Opt in to «deep search across every level with path-annotated results».
     * Off by default: children load per-parent on demand instead of loading
     * the full hierarchy up front.
     */
    deepSearch?: boolean;
};

/**
 * A parent → child option hierarchy rendered as ONE cascader control, e.g.
 * استان → شهر (places) or دین → مذهب (ordinary groups wired by
 * `parent_value`). `PlaceCascader` is a thin facade over this component.
 *
 * The two value models live in `childValueMode` — see its docblock. Both keep
 * the tree index collision-free: reui dedupes nodes by value, and «plain»
 * child values can equal a parent's.
 */export function OptionHierarchyField({
    parentGroup,
    childGroup,
    parentLabel,
    childLabel,
    parentField,
    childField,
    combinedField,
    childValueMode,
    label,
    placeholder,
    disabled,
    deepSearch = false,
}: OptionHierarchyFieldProps) {
    const combined = combinedField !== undefined;
    if (
        process.env.NODE_ENV !== "production" &&
        (!childField !== !parentField ||
            (combined && (parentField || childField)))
    ) {
        throw new Error(
            "OptionHierarchyField requires either combinedField, or both parentField and childField.",
        );
    }
    // Non-null in two-field mode; guarded by the arity check above.
    const parent = parentField!;
    const child = childField!;
    const plain = childValueMode === "plain";

    // ── Current field values ────────────────────────────────────────────────
    // Combined mode derives the parent by splitting the stored child value.
    const childValue = (
        (combined
            ? combinedField!.state.value
            : child.state.value) as string | undefined
    ) ?? "";
    const parentValue = combined
        ? (plain ? "" : childValue.split("-")[0] ?? "")
        : ((parent.state.value as string | undefined) ?? "");

    const { data: parentOptions, isPending: parentPending } =
        useFormOptionsByGroup(parentGroup);
    // The full child group — only fetched when `deepSearch` is on. Children
    // otherwise load per-parent on demand; «deep» search only sees nodes the
    // index has loaded, so it needs the full hierarchy up front.
    const { data: childOptions } = useFormOptionsByGroup(
        childGroup,
        undefined,
        undefined,
        deepSearch,
    );
    // Resolve the saved child's real label for the trigger display, including
    // inactive options. Disabled until a stored value exists.
    const resolvedQuery = useResolvedFormOptions(
        childGroup,
        childValue ? [childValue] : [],
    );
    const resolvedChild = resolvedQuery.data?.[0] ?? null;

    // The cascader's committed value. Prefixed mode: the child's own value.
    // Plain mode: the collision-free «parent::child» composite — «""» while
    // nothing is committed, since reui treats an empty string as unselected.
    const treeValue = plain
        ? childValue
            ? `${parentValue}${HIERARCHY_SEPARATOR}${childValue}`
            : ""
        : childValue;

    const isInvalid =
        (!combined && parent.state.meta.isTouched && !parent.state.meta.isValid) ||
        ((combined ? combinedField!.state.meta : child.state.meta).isTouched &&
            !(combined ? combinedField!.state.meta : child.state.meta).isValid);

    // ── Tree construction ───────────────────────────────────────────────────
    const items = useMemo<CascaderNode[]>(() => {
        const parents = (parentOptions ?? []).map((option) => ({
            value: option.value,
            label: option.label,
            hasChildren: true,
        }));
        if (!deepSearch) return parents;

        const childrenOf = new Map<string, CascaderNode[]>();
        for (const option of childOptions ?? []) {
            const bucketKey = option.parent_value ?? "";
            const node: CascaderNode = plain
                ? {
                      value: `${bucketKey}${HIERARCHY_SEPARATOR}${option.value}`,
                      label: option.label,
                  }
                : { value: option.value, label: option.label };
            const bucket = childrenOf.get(bucketKey);
            if (bucket) bucket.push(node);
            else childrenOf.set(bucketKey, [node]);
        }
        return parents.map((parentNode) => ({
            value: parentNode.value,
            label: parentNode.label,
            hasChildren: parentNode.hasChildren,
            children: childrenOf.get(parentNode.value) ?? [],
        }));
    }, [parentOptions, childOptions, deepSearch, plain]);

    // The stored child → its tree node value.
    const childNodeValue = (ownValue: string, parentNodeValue: string) =>
        plain
            ? `${parentNodeValue}${HIERARCHY_SEPARATOR}${ownValue}`
            : ownValue;

    const handleValueChange = (value: string) => {
        if (plain) {
            const separator = value.indexOf(HIERARCHY_SEPARATOR);
            // A bare parent value never commits with `selectable="leaf"`
            // (parent rows navigate); ignore it as a defensive no-op.
            if (separator === -1) return;
            const nextParent = value.slice(0, separator);
            const nextChild = value.slice(HIERARCHY_SEPARATOR.length + separator);
            if (nextParent === parentValue && nextChild === childValue) return;
            parent.handleChange(nextParent);
            child.handleChange(nextChild);
            return;
        }
        if (value === childValue) return;
        if (combined) {
            // The child option's own value IS the combined place string.
            combinedField!.handleChange(value);
        } else {
            parent.handleChange(value.split("-")[0] ?? "");
            child.handleChange(value);
        }
    };

    // Rebuild the parent → child ancestor chain for a saved selection so the
    // trigger can label a value whose children haven't been fetched yet.
    const resolveValue = async (value: string) => {
        const committed = value || treeValue;
        if (!committed) return [];
        let ownParent = "";
        let ownChild = committed;
        if (plain) {
            const separator = committed.indexOf(HIERARCHY_SEPARATOR);
            // A bare parent value is not a committed selection (the child is
            // what commits); nothing to resolve.
            if (separator === -1) return [];
            ownParent = committed.slice(0, separator);
            ownChild = committed.slice(HIERARCHY_SEPARATOR.length + separator);
        } else {
            ownParent = committed.split("-")[0] ?? "";
        }
        const parentNode = (parentOptions ?? []).find(
            (option) => option.value === ownParent,
        );
        const childNode: CascaderNode = {
            value: childNodeValue(ownChild, ownParent),
            label: resolvedChild?.label ?? ownChild,
        };
        if (!parentNode) return [childNode];
        return [
            { value: parentNode.value, label: parentNode.label },
            childNode,
        ];
    };

    // Touch every bound field on blur; the required field (parent in plain
    // mode, child otherwise) is the one whose errors surface first.
    const onBlur = () => {
        parent?.handleBlur();
        child?.handleBlur();
        combinedField?.handleBlur();
    };

    const errorSource: AnyFieldApi | undefined = !combined
        ? parent.state.meta.isTouched && !parent.state.meta.isValid
            ? parent
            : child
        : combinedField;

    const unstyled =
        (!!parentValue && parentPending) ||
        (!!childValue && childPending(resolvedQuery.isPending, resolvedChild));

    const cascaderLabels = useMemo(
        () => buildHierarchyLabels(parentLabel, childLabel, label),
        [parentLabel, childLabel, label],
    );

    return (
        <Field data-invalid={isInvalid}>
            {label && <FieldLabel>{label}</FieldLabel>}
            <Cascader
                items={items}
                // ALWAYS a string: reui treats "" as nothing selected, and
                // passing `undefined` until the first commit would flip the
                // cascader from uncontrolled to controlled mid-life, stranding
                // its state (every later commit then looks like a no-op).
                value={treeValue}
                onValueChange={handleValueChange}
                resolveValue={resolveValue}
                searchScope={deepSearch ? "deep" : "level"}
                getChildren={
                    deepSearch
                        ? undefined
                        : hierarchyLazyChildren(childGroup, plain)
                }
                labels={cascaderLabels}
                disabled={disabled}
            >
                <CascaderTrigger
                    render={
                        <Button
                            variant="outline"
                            aria-label={placeholder ?? label ?? ""}
                            data-invalid={isInvalid || undefined}
                            className={cn(
                                "h-8 w-full justify-between font-normal",
                                isInvalid &&
                                    "border-destructive ring-3 ring-destructive/20",
                                !treeValue && "text-muted-foreground",
                            )}
                        />
                    }
                    onBlur={onBlur}
                >
                    <CascaderValue
                        placeholder={placeholder ?? `انتخاب ${parentLabel} و ${childLabel}`}
                    >
                        {() => {
                            if (!treeValue) {
                                return (
                                    <span className="text-muted-foreground truncate">
                                        {placeholder ??
                                            `انتخاب ${parentLabel} و ${childLabel}`}
                                    </span>
                                );
                            }
                            if (unstyled) {
                                return (
                                    <span className="flex items-center gap-2 text-muted-foreground truncate">
                                        <IconLoader2 className="size-3.5 animate-spin" />
                                        <span>در حال بارگذاری…</span>
                                    </span>
                                );
                            }
                            if (!childValue) {
                                return (
                                    <span className="truncate">
                                        {parentLabelOf(parentOptions, parentValue)}
                                    </span>
                                );
                            }
                            return (
                                <span className="truncate">
                                    <span className="text-muted-foreground">
                                        {parentLabelOf(parentOptions, parentValue)}
                                    </span>
                                    <span className="mx-1">—</span>
                                    <span>{resolvedChild?.label ?? childValue}</span>
                                </span>
                            );
                        }}
                    </CascaderValue>
                </CascaderTrigger>

                <CascaderContent className="w-72">
                    <CascaderPanel>
                        <CascaderNav>
                            <CascaderInput placeholder="جستجو…" />
                        </CascaderNav>
                        <CascaderBreadcrumb />
                        <CascaderEmpty />
                        <CascaderList>
                            <CascaderItems />
                        </CascaderList>
                        <CascaderStatus />
                    </CascaderPanel>
                </CascaderContent>
            </Cascader>
            {isInvalid && <FieldError errors={errorSource?.state.meta.errors} />}
        </Field>
    );
}

/**
 * Default (cheap) mode: fetch one parent's children on demand. Plain mode
 * qualifies child node values so they cannot collide with parent values.
 */
function hierarchyLazyChildren(
    childGroup: string,
    plain: boolean,
): (node: CascaderNode | null) => Promise<CascaderNode[]> {
    return async (node) => {
        const parentValue = node?.value;
        if (!parentValue) return [];
        const { data } = await fetchFormOptionsByGroup(childGroup, parentValue);
        return (data.data ?? []).map((option) => ({
            value: plain
                ? `${parentValue}${HIERARCHY_SEPARATOR}${option.value}`
                : option.value,
            label: option.label,
        }));
    };
}

function childPending(isPending: boolean, resolvedChild: unknown): boolean {
    // The resolve query reports pending only while it has no data; a genuinely
    // unknown stored value resolves to an empty set and settles for good.
    return isPending && resolvedChild === null;
}

function parentLabelOf(
    parentOptions: { value: string; label: string }[] | undefined,
    parentValue: string,
): string {
    return (
        parentOptions?.find((option) => option.value === parentValue)?.label ??
        parentValue
    );
}

function buildHierarchyLabels(
    parentLabel: string,
    childLabel: string,
    panelLabel?: string,
): CascaderLabels {
    return {
        search: (parent) =>
            parent ? `جستجوی ${parent}…` : `جستجوی ${parentLabel}…`,
        back: "بازگشت",
        loading: "در حال بارگذاری…",
        loadingMore: "در حال بارگذاری موارد بیشتر…",
        loadMore: "بارگذاری موارد بیشتر",
        error: "خطا در بارگذاری موارد",
        retry: "تلاش مجدد",
        empty: "نتیجه‌ای یافت نشد",
        selectedCount: (count) => `${count} انتخاب شده`,
        breadcrumbLabel: "مسیر",
        chipsLabel: "موارد انتخاب شده",
        removeChip: (label) => `حذف ${label}`,
        pathSeparator: " / ",
        rootLevel: parentLabel,
        itemCount: (count) => `${count} مورد`,
        branchAffordance: "باز کردن زیرشاخه",
        selectedState: "انتخاب شده",
        partiallySelectedState: "انتخاب جزئی",
        columnsLabel: "سطوح",
        actionsLabel: "عملیات",
        submenuAffordance: "باز کردن منو",
        panelLabel: panelLabel ?? `انتخاب ${parentLabel} و ${childLabel}`,
        keyboardHint: () => "",
        rootAnnouncement: (count) => `${parentLabel}، ${count} مورد`,
        expandedAnnouncement: (value, count) => `${value} باز شد، ${count} مورد`,
        collapsedAnnouncement: (value) => `${value} بسته شد`,
        levelAnnouncement: (parent, _depth, count) => `${parent}، ${count} مورد`,
        resultsAnnouncement: (count) => `${count} نتیجه`,
        maxReachedAnnouncement: (max) => `حداکثر ${max} مورد انتخاب شده است`,
        cascadeAnnouncement: (value, count, selecting) =>
            selecting
                ? `${value} و ${count} مورد زیرمجموعه انتخاب شد`
                : `${value} و ${count} مورد زیرمجموعه حذف شد`,
        searchingAnnouncement: "در حال جستجو…",
    };
}
