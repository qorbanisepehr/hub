import { useMemo, useReducer } from "react";
import { IconPlus, IconTrash } from "@tabler/icons-react";
import { useQuery } from "@tanstack/react-query";

import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { FileUploadField } from "@/components/documents";
import { DocumentFileItem } from "@/components/documents";
import { useEmployeeDocuments } from "@/features/employees/hooks/use-employee-documents";
import type { EmployeeDocument } from "@/features/employees/hooks/use-employee-documents";
import { EmployeeDocumentTrashModal } from "./employee-document-trash-modal";
import { EmployeeDocumentReplaceModal } from "./employee-document-replace-modal";
import {
    DOC_CATEGORY_SLUGS,
    getFieldKeyLabel,
} from "@/features/questionnaire/constants";
import { fetchDocumentCategories } from "@/features/documents/api";
import type { DocumentCategory } from "@/features/documents/types";
import { documentKeys } from "@/lib/query-keys";

type SectionProps = {
    employeeId: number;
};

const EXTRA_DOC_SLUGS = new Set<string>([
    DOC_CATEGORY_SLUGS.ACADEMIC_DEGREE,
    DOC_CATEGORY_SLUGS.LANGUAGE_CERTIFICATE,
    DOC_CATEGORY_SLUGS.COURSE_CERTIFICATES,
    DOC_CATEGORY_SLUGS.SKILL_CERTIFICATE,
    DOC_CATEGORY_SLUGS.EMPLOYMENT_CERTIFICATE,
    DOC_CATEGORY_SLUGS.RESEARCH_DOCUMENTS,
    DOC_CATEGORY_SLUGS.COVER_LETTER,
    DOC_CATEGORY_SLUGS.OTHER_DOCUMENTS,
]);

const CATEGORY_KNOWN_FIELD_KEYS: Record<string, string[]> = {
    [DOC_CATEGORY_SLUGS.NATIONAL_CARD]: ["front", "back"],
    [DOC_CATEGORY_SLUGS.BIRTH_CERTIFICATE]: ["page-1", "page-2", "page-3"],
};

type ExtraDocEntry = {
    key: string;
    slug: string;
    label: string;
    notes: string;
};

type DocSectionState = {
    addedEntries: ExtraDocEntry[];
    pickSlug: string;
    pickNotes: string;
    trashOpen: boolean;
    replaceTarget: EmployeeDocument | null;
};

type DocSectionAction =
    | { type: "SET_PICK_SLUG"; slug: string }
    | { type: "SET_PICK_NOTES"; notes: string }
    | { type: "ADD_EXTRA_ENTRY"; label: string }
    | { type: "SET_TRASH_OPEN"; open: boolean }
    | { type: "SET_REPLACE_TARGET"; doc: EmployeeDocument | null };

function docSectionReducer(
    state: DocSectionState,
    action: DocSectionAction,
): DocSectionState {
    switch (action.type) {
        case "SET_PICK_SLUG":
            return { ...state, pickSlug: action.slug };
        case "SET_PICK_NOTES":
            return { ...state, pickNotes: action.notes };
        case "ADD_EXTRA_ENTRY": {
            const key = `${state.pickSlug}::${state.pickNotes}`;
            if (state.addedEntries.some((e) => e.key === key)) {
                return {
                    ...state,
                    pickSlug: DOC_CATEGORY_SLUGS.OTHER_DOCUMENTS,
                    pickNotes: "",
                };
            }
            return {
                ...state,
                addedEntries: [
                    ...state.addedEntries,
                    {
                        key,
                        slug: state.pickSlug,
                        label: action.label,
                        notes: state.pickNotes,
                    },
                ],
                pickSlug: DOC_CATEGORY_SLUGS.OTHER_DOCUMENTS,
                pickNotes: "",
            };
        }
        case "SET_TRASH_OPEN":
            return { ...state, trashOpen: action.open };
        case "SET_REPLACE_TARGET":
            return { ...state, replaceTarget: action.doc };
    }
}

function flattenCategoryMap(cats: DocumentCategory[]): Map<string, string> {
    const map = new Map<string, string>();
    for (const cat of cats) {
        map.set(cat.slug, cat.name);
        for (const [slug, name] of flattenCategoryMap(cat.children ?? [])) {
            map.set(slug, name);
        }
    }
    return map;
}

function deriveExtraEntries(
    documents: EmployeeDocument[],
    labels: Map<string, string>,
): ExtraDocEntry[] {
    const entries = new Map<string, ExtraDocEntry>();
    for (const doc of documents) {
        const slug = doc.category?.slug;
        if (!slug || !EXTRA_DOC_SLUGS.has(slug)) {
            continue;
        }
        const notes = doc.notes ?? "";
        const key = `${slug}::${notes}`;
        if (entries.has(key)) continue;
        entries.set(key, {
            key,
            slug,
            label: labels.get(slug) ?? slug,
            notes,
        });
    }
    return [...entries.values()];
}

export function DocumentsSection({ employeeId }: SectionProps) {
    const [state, dispatch] = useReducer(docSectionReducer, {
        addedEntries: [],
        pickSlug: DOC_CATEGORY_SLUGS.OTHER_DOCUMENTS,
        pickNotes: "",
        trashOpen: false,
        replaceTarget: null,
    });

    const uuid = String(employeeId);
    const { documents, getDocumentsBySlugExcept, capabilities } =
        useEmployeeDocuments(employeeId);

    const { data: categories } = useQuery({
        queryKey: documentKeys.categories("personnel"),
        queryFn: async () => {
            const { data } = await fetchDocumentCategories("personnel");
            return data.data;
        },
    });

    const categoryLabels = useMemo(
        () => flattenCategoryMap(categories ?? []),
        [categories],
    );

    const extraDocOptions = useMemo(
        () =>
            (categories ?? [])
                .flatMap((c) => [c, ...(c.children ?? [])])
                .filter((c) => EXTRA_DOC_SLUGS.has(c.slug))
                .map((c) => ({ slug: c.slug, label: c.name })),
        [categories],
    );

    const serverExtraEntries = useMemo(
        () => deriveExtraEntries(documents, categoryLabels),
        [documents, categoryLabels],
    );

    const extraDocs = useMemo(() => {
        const merged = [...serverExtraEntries];
        const seen = new Set(merged.map((e) => e.key));
        for (const entry of state.addedEntries) {
            if (seen.has(entry.key)) continue;
            merged.push(entry);
            seen.add(entry.key);
        }
        return merged;
    }, [serverExtraEntries, state.addedEntries]);

    const pickLabel =
        extraDocOptions.find((o) => o.slug === state.pickSlug)?.label ?? state.pickSlug;

    function handleAddExtra() {
        if (!extraDocOptions.some((o) => o.slug === state.pickSlug)) return;
        dispatch({ type: "ADD_EXTRA_ENTRY", label: pickLabel });
    }

    const orphanedEntries = Object.entries(CATEGORY_KNOWN_FIELD_KEYS)
        .map(([slug, knownKeys]) => {
            const orphans = getDocumentsBySlugExcept(slug, knownKeys);
            return { slug, label: categoryLabels.get(slug) ?? slug, orphans };
        })
        .filter((e) => e.orphans.length > 0);

    return (
        <Card>
            <CardHeader>
                <div className="flex items-center justify-between gap-3">
                    <CardTitle>بارگذاری مدارک</CardTitle>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => dispatch({ type: "SET_TRASH_OPEN", open: true })}
                    >
                        <IconTrash className="size-3.5 ms-1" />
                        سطل زباله
                    </Button>
                </div>
            </CardHeader>
            <CardContent className="space-y-6">
                <EmployeeDocumentTrashModal
                    open={state.trashOpen}
                    onOpenChange={(open) => dispatch({ type: "SET_TRASH_OPEN", open })}
                    employeeId={employeeId}
                />
                <EmployeeDocumentReplaceModal
                    open={state.replaceTarget !== null}
                    onOpenChange={(next) => {
                        if (!next) dispatch({ type: "SET_REPLACE_TARGET", doc: null });
                    }}
                    employeeId={employeeId}
                    doc={state.replaceTarget}
                />
                <p className="text-sm text-muted-foreground">
                    مدارک مورد نیاز را بارگذاری کنید. فرمت‌های مجاز: PDF، JPEG،
                    PNG، WebP.
                </p>

                <div className="space-y-4">
                    <span className="text-sm font-medium">مدارک هویتی</span>
                    <div>
                        <span className="text-sm font-medium">کارت ملی</span>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <FileUploadField
                                uuid={uuid}
                                entity="employees"
                                categorySlug={DOC_CATEGORY_SLUGS.NATIONAL_CARD}
                                label="کارت ملی — رو"
                                accept="image/jpeg,image/png,image/webp,.pdf"
                                fieldKey="front"
                                required
                                replaceEnabled={capabilities.replace}
                                onReplace={(doc) => dispatch({ type: "SET_REPLACE_TARGET", doc })}
                            />
                            <FileUploadField
                                uuid={uuid}
                                entity="employees"
                                categorySlug={DOC_CATEGORY_SLUGS.NATIONAL_CARD}
                                label="کارت ملی — پشت"
                                accept="image/jpeg,image/png,image/webp,.pdf"
                                fieldKey="back"
                                required
                                replaceEnabled={capabilities.replace}
                                onReplace={(doc) => dispatch({ type: "SET_REPLACE_TARGET", doc })}
                            />
                        </div>
                    </div>
                    <div>
                        <span className="text-sm font-medium">شناسنامه</span>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <FileUploadField
                                uuid={uuid}
                                entity="employees"
                                categorySlug={
                                    DOC_CATEGORY_SLUGS.BIRTH_CERTIFICATE
                                }
                                label="شناسنامه — صفحه اول"
                                accept="image/jpeg,image/png,image/webp,.pdf"
                                fieldKey="page-1"
                                required
                                replaceEnabled={capabilities.replace}
                                onReplace={(doc) => dispatch({ type: "SET_REPLACE_TARGET", doc })}
                            />
                            <FileUploadField
                                uuid={uuid}
                                entity="employees"
                                categorySlug={
                                    DOC_CATEGORY_SLUGS.BIRTH_CERTIFICATE
                                }
                                label="شناسنامه — صفحه دوم"
                                accept="image/jpeg,image/png,image/webp,.pdf"
                                fieldKey="page-2"
                                required
                                replaceEnabled={capabilities.replace}
                                onReplace={(doc) => dispatch({ type: "SET_REPLACE_TARGET", doc })}
                            />
                            <FileUploadField
                                uuid={uuid}
                                entity="employees"
                                categorySlug={
                                    DOC_CATEGORY_SLUGS.BIRTH_CERTIFICATE
                                }
                                label="شناسنامه — صفحه آخر"
                                accept="image/jpeg,image/png,image/webp,.pdf"
                                fieldKey="page-3"
                                required
                                replaceEnabled={capabilities.replace}
                                onReplace={(doc) => dispatch({ type: "SET_REPLACE_TARGET", doc })}
                            />
                        </div>
                    </div>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <FileUploadField
                                uuid={uuid}
                                entity="employees"
                                categorySlug={DOC_CATEGORY_SLUGS.RESUME}
                                label="رزومه"
                                multiple
                                maxFiles={5}
                                accept=".pdf,image/jpeg,image/png,image/webp"
                                replaceEnabled={capabilities.replace}
                                onReplace={(doc) => dispatch({ type: "SET_REPLACE_TARGET", doc })}
                        />
                    </div>

                    {orphanedEntries.map(({ slug, label, orphans }) => (
                        <div key={`orphan-${slug}`} className="space-y-2">
                            <span className="text-xs text-muted-foreground">
                                سایر مدارک بارگذاری‌شده — {label}
                            </span>
                            <div className="flex flex-wrap gap-3">
                                {orphans.map((doc) => (
                                    <DocumentFileItem
                                        key={doc.usage_id}
                                        uuid={uuid}
                                        entity="employees"
                                        doc={doc}
                                        layout="compact"
                                        thumbnailSize="size-16"
                                        label={
                                            getFieldKeyLabel(doc.field_key) ??
                                            doc.field_key
                                        }
                                        onReplace={
                                            capabilities.replace
                                                ? () => dispatch({ type: "SET_REPLACE_TARGET", doc })
                                                : undefined
                                        }
                                    />
                                ))}
                            </div>
                        </div>
                    ))}
                </div>

                <div className="space-y-4">
                    <div className="flex items-center justify-between">
                        <span className="text-sm font-medium">
                            مدارک تکمیلی
                        </span>
                        <div className="flex items-center gap-2">
                            <Select
                                value={state.pickSlug}
                                onValueChange={(v) =>
                                    v != null && dispatch({ type: "SET_PICK_SLUG", slug: v })
                                }
                                itemToStringLabel={(val) =>
                                    extraDocOptions.find(
                                        (o) => o.slug === val,
                                    )?.label ?? val
                                }
                            >
                                <SelectTrigger className="w-48 h-8 text-xs">
                                    <SelectValue placeholder="نوع مدرک" />
                                </SelectTrigger>
                                <SelectContent>
                                    {extraDocOptions.map((opt) => (
                                        <SelectItem
                                            key={opt.slug}
                                            value={opt.slug}
                                        >
                                            {opt.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Input
                                value={state.pickNotes}
                                onChange={(e) =>
                                    dispatch({ type: "SET_PICK_NOTES", notes: e.target.value })
                                }
                                placeholder="توضیحات (اختیاری)"
                                className="h-8 text-xs w-40"
                            />
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={handleAddExtra}
                            >
                                <IconPlus className="size-3.5 ms-1" />
                                افزودن
                            </Button>
                        </div>
                    </div>

                    {extraDocs.length > 0 && (
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            {extraDocs.map((entry) => (
                                <FileUploadField
                                    key={entry.key}
                                    uuid={uuid}
                                    entity="employees"
                                    categorySlug={entry.slug}
                                    label={
                                        entry.label +
                                        (entry.notes ? ` — ${entry.notes}` : "")
                                    }
                                    accept=".pdf,image/jpeg,image/png,image/webp"
                                    multiple
                                    maxFiles={5}
                                    notes={entry.notes}
                                    replaceEnabled={capabilities.replace}
                                    onReplace={(doc) => dispatch({ type: "SET_REPLACE_TARGET", doc })}
                                />
                            ))}
                        </div>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}
