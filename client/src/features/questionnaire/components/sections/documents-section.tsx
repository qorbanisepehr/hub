import { useMemo, useState } from "react";
import { IconPlus } from "@tabler/icons-react";
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
import { FileUploadField } from "@/components/shared/file-upload-field";
import { DocumentFileItem } from "@/components/shared/document-file-item";
import { useQuestionnaireDocuments } from "@/features/questionnaire/hooks/use-questionnaire-documents";
import type { QuestionnaireDocument } from "@/features/questionnaire/hooks/use-questionnaire-documents";
import {
    getFieldKeyLabel,
    DOC_CATEGORY_SLUGS,
} from "@/features/questionnaire/constants";
import { fetchDocumentCategories } from "@/features/documents/api";
import type { DocumentCategory } from "@/features/documents/types";
import { documentKeys } from "@/lib/query-keys";

type SectionProps = {
    uuid?: string;
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
    documents: QuestionnaireDocument[],
    labels: Map<string, string>,
): ExtraDocEntry[] {
    const entries = new Map<string, ExtraDocEntry>();
    for (const doc of documents) {
        const slug = doc.category?.slug;
        if (!slug || !EXTRA_DOC_SLUGS.has(slug)) continue;
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

export function DocumentsSection({ uuid }: SectionProps) {
    const [addedEntries, setAddedEntries] = useState<ExtraDocEntry[]>([]);
    const [pickSlug, setPickSlug] = useState<string>(
        DOC_CATEGORY_SLUGS.OTHER_DOCUMENTS,
    );
    const [pickNotes, setPickNotes] = useState("");

    const { documents, getDocumentsBySlugExcept } =
        useQuestionnaireDocuments(uuid);

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
        for (const entry of addedEntries) {
            if (seen.has(entry.key)) continue;
            merged.push(entry);
            seen.add(entry.key);
        }
        return merged;
    }, [serverExtraEntries, addedEntries]);

    if (!uuid) return null;

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
            return { slug, label: categoryLabels.get(slug) ?? slug, orphans };
        })
        .filter((e) => e.orphans.length > 0);

    return (
        <Card>
            <CardHeader>
                <CardTitle>بارگذاری مدارک</CardTitle>
            </CardHeader>
            <CardContent className="space-y-6">
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
                                categorySlug={DOC_CATEGORY_SLUGS.NATIONAL_CARD}
                                label="کارت ملی — رو"
                                accept="image/jpeg,image/png,image/webp,.pdf"
                                fieldKey="front"
                            />
                            <FileUploadField
                                uuid={uuid}
                                categorySlug={DOC_CATEGORY_SLUGS.NATIONAL_CARD}
                                label="کارت ملی — پشت"
                                accept="image/jpeg,image/png,image/webp,.pdf"
                                fieldKey="back"
                            />
                        </div>
                    </div>
                    <div>
                        <span className="text-sm font-medium">شناسنامه</span>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <FileUploadField
                                uuid={uuid}
                                categorySlug={
                                    DOC_CATEGORY_SLUGS.BIRTH_CERTIFICATE
                                }
                                label="شناسنامه — صفحه اول"
                                accept="image/jpeg,image/png,image/webp,.pdf"
                                fieldKey="page-1"
                            />
                            <FileUploadField
                                uuid={uuid}
                                categorySlug={
                                    DOC_CATEGORY_SLUGS.BIRTH_CERTIFICATE
                                }
                                label="شناسنامه — صفحه دوم"
                                accept="image/jpeg,image/png,image/webp,.pdf"
                                fieldKey="page-2"
                            />
                            <FileUploadField
                                uuid={uuid}
                                categorySlug={
                                    DOC_CATEGORY_SLUGS.BIRTH_CERTIFICATE
                                }
                                label="شناسنامه — صفحه آخر"
                                accept="image/jpeg,image/png,image/webp,.pdf"
                                fieldKey="page-3"
                            />
                        </div>
                    </div>
                    <div>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <FileUploadField
                                uuid={uuid}
                                categorySlug={DOC_CATEGORY_SLUGS.RESUME}
                                label="رزومه"
                                multiple
                                maxFiles={5}
                                accept=".pdf,image/jpeg,image/png,image/webp"
                            />
                            {/* <FileUploadField
                                    key={entry.key}
                                    uuid={uuid}
                                    categorySlug={entry.slug}
                                    label={
                                        entry.label +
                                        (entry.notes ? ` — ${entry.notes}` : "")
                                    }
                                    accept=".pdf,image/jpeg,image/png,image/webp"
                                    multiple
                                    maxFiles={5}
                                    notes={entry.notes}
                                /> */}
                        </div>
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
                                        doc={doc}
                                        layout="compact"
                                        thumbnailSize="size-16"
                                        label={
                                            getFieldKeyLabel(doc.field_key) ??
                                            doc.field_key
                                        }
                                    />
                                ))}
                            </div>
                        </div>
                    ))}
                </div>

                {/* ── مدارک تکمیلی ── */}
                <div className="space-y-4">
                    <div className="flex items-center justify-between">
                        <span className="text-sm font-medium">
                            مدارک تکمیلی
                        </span>
                        <div className="flex items-center gap-2">
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
                                value={pickNotes}
                                onChange={(e) => setPickNotes(e.target.value)}
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
                                    categorySlug={entry.slug}
                                    label={
                                        entry.label +
                                        (entry.notes ? ` — ${entry.notes}` : "")
                                    }
                                    accept=".pdf,image/jpeg,image/png,image/webp"
                                    multiple
                                    maxFiles={5}
                                    notes={entry.notes}
                                />
                            ))}
                        </div>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}
