"use client";

import { useMemo, useState } from "react";
import { IconPlus } from "@tabler/icons-react";
import { useQuery } from "@tanstack/react-query";
import * as React from "react";

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
import { FileUploadField } from "./file-upload-field";
import { DocumentFileItem } from "./document-file-item";
import {
    getFieldKeyLabel,
    DOC_CATEGORY_SLUGS,
} from "@/features/questionnaire/constants";
import { fetchDocumentCategories } from "@/features/documents/api";
import type { DocumentCategory } from "@/features/documents/types";
import { documentKeys } from "@/lib/query-keys";
import type { EntityDocument } from "@/hooks/use-entity-documents";

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

/**
 * Personnel-only categories additionally offered in the employee documents
 * step's «سایر مدارک» picker (contract, financial, supplementary-insurance
 * and social-security document groups).
 */
export const PERSONNEL_EXTRA_DOC_SLUGS = new Set<string>([
    DOC_CATEGORY_SLUGS.CONTRACT,
    DOC_CATEGORY_SLUGS.PAYSLIP,
    DOC_CATEGORY_SLUGS.SALARY_DEDUCTION_LETTER,
    DOC_CATEGORY_SLUGS.SALARY_DECREE,
    DOC_CATEGORY_SLUGS.INITIAL_SALARY,
    DOC_CATEGORY_SLUGS.SALARY_CHANGE,
    DOC_CATEGORY_SLUGS.FINANCIAL_AFFIDAVIT,
    DOC_CATEGORY_SLUGS.SUPPLEMENTARY_INSURANCE_FORM,
    DOC_CATEGORY_SLUGS.INSURANCE_HISTORY,
    DOC_CATEGORY_SLUGS.INSURANCE_HISTORY_RIAL,
    DOC_CATEGORY_SLUGS.INSURANCE_HISTORY_SUMMARY,
    DOC_CATEGORY_SLUGS.INSURANCE_HISTORY_RIAL_SUMMARY,
    DOC_CATEGORY_SLUGS.INSURANCE_HISTORY_OVERALL,
    DOC_CATEGORY_SLUGS.INSURANCE_LAST_JOB_TITLES,
]);

const CATEGORY_KNOWN_FIELD_KEYS: Record<string, string[]> = {
    [DOC_CATEGORY_SLUGS.NATIONAL_CARD]: ["front", "back"],
    [DOC_CATEGORY_SLUGS.BIRTH_CERTIFICATE]: [
        "page-1",
        "page-2",
        "page-3",
        "page-4",
        "page-extra",
    ],
};

type ExtraDocEntry = {
    key: string;
    slug: string;
    label: string;
    notes: string;
};

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
    documents: EntityDocument[],
    labels: Map<string, string>,
    slugs: Set<string>,
): ExtraDocEntry[] {
    const entries = new Map<string, ExtraDocEntry>();
    for (const doc of documents) {
        const slug = doc.category?.slug;
        if (!slug || !slugs.has(slug)) continue;
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

export type EntityDocumentsSectionProps = {
    uuid: string;
    /** Grant entity the uploads/delete target (e.g. "questionnaire", "employees"). */
    entity: string;
    documents: EntityDocument[];
    getDocumentsBySlugExcept: (
        slug: string,
        knownKeys: string[],
    ) => EntityDocument[];
    /** Optional header actions (e.g. a trash button) rendered beside the title. */
    headerActions?: React.ReactNode;
    /** Optional modals (e.g. trash/replace) rendered above the form. */
    modals?: React.ReactNode;
    /** Optional fixed fields rendered alongside the resume in the identity block. */
    extraFixedFields?: React.ReactNode;
    /** When enabled, current documents render a "replace" action. */
    replaceEnabled?: boolean;
    onReplace?: (doc: EntityDocument) => void;
    /** Label resolution for orphaned documents. Defaults to field-key label. */
    orphanLabel?: (doc: EntityDocument) => string;
    /**
     * Category slugs offered in the «سایر مدارک» picker. Defaults to the
     * applicant set; the employee documents step passes the personnel set.
     */
    extraDocSlugs?: Set<string>;
};

export function EntityDocumentsSection({
    uuid,
    entity,
    documents,
    getDocumentsBySlugExcept,
    headerActions,
    modals,
    extraFixedFields,
    replaceEnabled,
    onReplace,
    orphanLabel,
    extraDocSlugs = EXTRA_DOC_SLUGS,
}: EntityDocumentsSectionProps) {
    const [addedEntries, setAddedEntries] = useState<ExtraDocEntry[]>([]);
    const [pickSlug, setPickSlug] = useState<string>(
        DOC_CATEGORY_SLUGS.OTHER_DOCUMENTS,
    );
    const [pickNotes, setPickNotes] = useState("");

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

    const extraDocOptions = useMemo(() => {
        const options: { slug: string; label: string }[] = [];
        for (const category of categories ?? []) {
            if (extraDocSlugs.has(category.slug)) {
                options.push({ slug: category.slug, label: category.name });
            }
            for (const child of category.children ?? []) {
                if (extraDocSlugs.has(child.slug)) {
                    options.push({ slug: child.slug, label: child.name });
                }
            }
        }
        return options;
    }, [categories, extraDocSlugs]);

    const serverExtraEntries = useMemo(
        () => deriveExtraEntries(documents, categoryLabels, extraDocSlugs),
        [documents, categoryLabels, extraDocSlugs],
    );

    const extraDocs = useMemo(() => {
        const merged = [...serverExtraEntries];
        const seen = new Set(merged.map((e) => e.key));
        for (const entry of addedEntries) {
            if (seen.has(entry.key)) continue;
            merged.push(entry);
            seen.add(entry.key);
        }
        return merged;
    }, [serverExtraEntries, addedEntries]);

    const pickLabel =
        extraDocOptions.find((o) => o.slug === pickSlug)?.label ?? pickSlug;

    function handleAddExtra() {
        if (!extraDocOptions.some((o) => o.slug === pickSlug)) return;
        const key = `${pickSlug}::${pickNotes}`;
        setAddedEntries((prev) =>
            prev.some((e) => e.key === key)
                ? prev
                : [
                      ...prev,
                      {
                          key,
                          slug: pickSlug,
                          label: pickLabel,
                          notes: pickNotes,
                      },
                  ],
        );
        setPickSlug(DOC_CATEGORY_SLUGS.OTHER_DOCUMENTS);
        setPickNotes("");
    }

    const orphanedEntries = Object.entries(CATEGORY_KNOWN_FIELD_KEYS)
        .map(([slug, knownKeys]) => {
            const orphans = getDocumentsBySlugExcept(slug, knownKeys);
            return {
                slug,
                label: categoryLabels.get(slug) ?? slug,
                orphans,
            };
        })
        .filter((e) => e.orphans.length > 0);

    const resolveOrphanLabel =
        orphanLabel ??
        ((doc: EntityDocument) =>
            getFieldKeyLabel(doc.field_key) ?? doc.field_key);

    return (
        <Card>
            <CardHeader>
                {headerActions ? (
                    <div className="flex items-center justify-between gap-3">
                        <CardTitle>بارگذاری مدارک</CardTitle>
                        {headerActions}
                    </div>
                ) : (
                    <CardTitle>بارگذاری مدارک</CardTitle>
                )}
            </CardHeader>
            <CardContent className="space-y-6">
                {modals}

                <p className="text-sm text-muted-foreground">
                    مدارک مورد نیاز را بارگذاری کنید. فرمت‌های مجاز: PDF، JPEG،
                    PNG، WebP.
                </p>

                {/* ── مدارک ثابت ── */}
                <div className="space-y-4">
                    <span className="text-sm font-medium">مدارک هویتی</span>
                    <div>
                        <span className="text-sm font-medium">کارت ملی</span>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <FileUploadField
                                uuid={uuid}
                                entity={entity}
                                categorySlug={DOC_CATEGORY_SLUGS.NATIONAL_CARD}
                                label="کارت ملی — رو"
                                accept="image/jpeg,image/png,image/webp,.pdf"
                                fieldKey="front"
                                required
                                replaceEnabled={replaceEnabled}
                                onReplace={onReplace}
                            />
                            <FileUploadField
                                uuid={uuid}
                                entity={entity}
                                categorySlug={DOC_CATEGORY_SLUGS.NATIONAL_CARD}
                                label="کارت ملی — پشت"
                                accept="image/jpeg,image/png,image/webp,.pdf"
                                fieldKey="back"
                                required
                                replaceEnabled={replaceEnabled}
                                onReplace={onReplace}
                            />
                        </div>
                    </div>
                    <div>
                        <span className="text-sm font-medium">شناسنامه</span>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <FileUploadField
                                uuid={uuid}
                                entity={entity}
                                categorySlug={
                                    DOC_CATEGORY_SLUGS.BIRTH_CERTIFICATE
                                }
                                label="شناسنامه — صفحه اول"
                                accept="image/jpeg,image/png,image/webp,.pdf"
                                fieldKey="page-1"
                                required
                                replaceEnabled={replaceEnabled}
                                onReplace={onReplace}
                            />
                            <FileUploadField
                                uuid={uuid}
                                entity={entity}
                                categorySlug={
                                    DOC_CATEGORY_SLUGS.BIRTH_CERTIFICATE
                                }
                                label="شناسنامه — صفحه دوم"
                                accept="image/jpeg,image/png,image/webp,.pdf"
                                fieldKey="page-2"
                                required
                                replaceEnabled={replaceEnabled}
                                onReplace={onReplace}
                            />
                            <FileUploadField
                                uuid={uuid}
                                entity={entity}
                                categorySlug={
                                    DOC_CATEGORY_SLUGS.BIRTH_CERTIFICATE
                                }
                                label="شناسنامه — صفحه سوم"
                                accept="image/jpeg,image/png,image/webp,.pdf"
                                fieldKey="page-3"
                                required
                                replaceEnabled={replaceEnabled}
                                onReplace={onReplace}
                            />
                            <FileUploadField
                                uuid={uuid}
                                entity={entity}
                                categorySlug={
                                    DOC_CATEGORY_SLUGS.BIRTH_CERTIFICATE
                                }
                                label="شناسنامه — صفحه چهارم"
                                accept="image/jpeg,image/png,image/webp,.pdf"
                                fieldKey="page-4"
                                required
                                replaceEnabled={replaceEnabled}
                                onReplace={onReplace}
                            />
                            <FileUploadField
                                uuid={uuid}
                                entity={entity}
                                categorySlug={
                                    DOC_CATEGORY_SLUGS.BIRTH_CERTIFICATE
                                }
                                label="شناسنامه — صفحه پنجم"
                                accept="image/jpeg,image/png,image/webp,.pdf"
                                fieldKey="page-extra"
                                replaceEnabled={replaceEnabled}
                                onReplace={onReplace}
                            />
                        </div>
                    </div>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        {extraFixedFields}
                        <FileUploadField
                            uuid={uuid}
                            entity={entity}
                            categorySlug={DOC_CATEGORY_SLUGS.RESUME}
                            label="رزومه"
                            multiple
                            maxFiles={5}
                            accept=".pdf,image/jpeg,image/png,image/webp"
                            replaceEnabled={replaceEnabled}
                            onReplace={onReplace}
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
                                        entity={entity}
                                        doc={doc}
                                        layout="compact"
                                        thumbnailSize="size-16"
                                        label={resolveOrphanLabel(doc)}
                                        onReplace={
                                            replaceEnabled ? onReplace : undefined
                                        }
                                    />
                                ))}
                            </div>
                        </div>
                    ))}
                </div>

                {/* ── مدارک تکمیلی ── */}
                <div className="space-y-4">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <span className="text-sm font-medium">
                            مدارک تکمیلی
                        </span>
                        <div className="flex w-full flex-wrap items-center gap-2 sm:w-auto">
                            <Select
                                value={pickSlug}
                                onValueChange={(v) =>
                                    v != null && setPickSlug(v)
                                }
                                itemToStringLabel={(val) =>
                                    extraDocOptions.find((o) => o.slug === val)
                                        ?.label ?? val
                                }
                            >
                                <SelectTrigger className="h-8 w-full text-xs sm:w-48">
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
                                value={pickNotes}
                                onChange={(e) => setPickNotes(e.target.value)}
                                placeholder="توضیحات (اختیاری)"
                                className="h-8 w-full text-xs sm:w-40"
                            />
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                className="sm:ms-auto"
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
                                    entity={entity}
                                    categorySlug={entry.slug}
                                    label={
                                        entry.label +
                                        (entry.notes
                                            ? ` — ${entry.notes}`
                                            : "")
                                    }
                                    accept=".pdf,image/jpeg,image/png,image/webp"
                                    multiple
                                    maxFiles={5}
                                    notes={entry.notes}
                                    replaceEnabled={replaceEnabled}
                                    onReplace={onReplace}
                                />
                            ))}
                        </div>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}