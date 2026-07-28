import { useState } from "react";
import { IconPlus } from "@tabler/icons-react";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";

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
import { DocumentThumbnail } from "@/components/shared/document-thumbnail";
import { ConfirmDeleteButton } from "@/components/ui/confirm-delete-button";
import { useQuestionnaireDocuments } from "@/features/recruitment/hooks/use-questionnaire-documents";
import { DOC_CATEGORY_SLUGS, getRecordKeyLabel } from "@/features/recruitment/constants";
import { api } from "@/lib/api";
import type { Document } from "@/features/documents/types";

const CATEGORY_LABELS: Record<string, string> = {
    [DOC_CATEGORY_SLUGS.NATIONAL_CARD]: "کارت ملی",
    [DOC_CATEGORY_SLUGS.BIRTH_CERTIFICATE]: "شناسنامه",
};

type SectionProps = {
    uuid?: string;
};

const EXTRA_DOC_OPTIONS = [
    { slug: DOC_CATEGORY_SLUGS.OTHER_DOCUMENTS, label: "سایر مدارک" },
    { slug: DOC_CATEGORY_SLUGS.ACADEMIC_DEGREE, label: "مدرک تحصیلی" },
    { slug: DOC_CATEGORY_SLUGS.LANGUAGE_CERTIFICATE, label: "گواهینامه زبان" },
    { slug: DOC_CATEGORY_SLUGS.COURSE_CERTIFICATES, label: "گواهینامه دوره" },
    { slug: DOC_CATEGORY_SLUGS.COVER_LETTER, label: "نامه پوششی" },
];

const CATEGORY_KNOWN_KEYS: Record<string, string[]> = {
    [DOC_CATEGORY_SLUGS.NATIONAL_CARD]: ["front", "back"],
    [DOC_CATEGORY_SLUGS.BIRTH_CERTIFICATE]: ["front", "back"],
};

type ExtraDocEntry = {
    slug: string;
    label: string;
    notes: string;
};

export function DocumentsSection({ uuid }: SectionProps) {
    const [extraDocs, setExtraDocs] = useState<ExtraDocEntry[]>([]);
    const [pickSlug, setPickSlug] = useState<string>(DOC_CATEGORY_SLUGS.OTHER_DOCUMENTS);
    const [pickNotes, setPickNotes] = useState("");

    const { getDocumentsBySlugExcept } = useQuestionnaireDocuments(uuid);
    const queryClient = useQueryClient();

    const deleteMutation = useMutation({
        mutationFn: (doc: Document) => api.delete(`/documents/${doc.id}`),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ["questionnaire-documents", uuid] });
            toast.success("مدرک حذف شد.");
        },
        onError: () => toast.error("خطا در حذف مدرک."),
    });

    const orphanedEntries = Object.entries(CATEGORY_KNOWN_KEYS)
        .map(([slug, knownKeys]) => {
            const orphans = getDocumentsBySlugExcept(slug, knownKeys);
            return { slug, label: CATEGORY_LABELS[slug] ?? slug, orphans };
        })
        .filter((e) => e.orphans.length > 0);

    if (!uuid) return null;

    function handleAddExtra() {
        const opt = EXTRA_DOC_OPTIONS.find((o) => o.slug === pickSlug);
        if (!opt) return;
        setExtraDocs((prev) => [...prev, { slug: opt.slug, label: opt.label, notes: pickNotes }]);
        setPickSlug(DOC_CATEGORY_SLUGS.OTHER_DOCUMENTS);
        setPickNotes("");
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle>بارگذاری مدارک</CardTitle>
            </CardHeader>
            <CardContent className="space-y-6">
                <p className="text-sm text-muted-foreground">
                    مدارک مورد نیاز را بارگذاری کنید. فرمت‌های مجاز: PDF، JPEG، PNG، WebP.
                </p>

                {/* ── مدارک ثابت ── */}
                <div className="space-y-4">
                    <span className="text-sm font-medium">مدارک هویتی</span>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <FileUploadField
                            uuid={uuid}
                            categorySlug={DOC_CATEGORY_SLUGS.NATIONAL_CARD}
                            label="کارت ملی — رو"
                            accept="image/jpeg,image/png,image/webp,.pdf"
                            recordKey="front"
                        />
                        <FileUploadField
                            uuid={uuid}
                            categorySlug={DOC_CATEGORY_SLUGS.NATIONAL_CARD}
                            label="کارت ملی — پشت"
                            accept="image/jpeg,image/png,image/webp,.pdf"
                            recordKey="back"
                        />
                        <FileUploadField
                            uuid={uuid}
                            categorySlug={DOC_CATEGORY_SLUGS.BIRTH_CERTIFICATE}
                            label="شناسنامه — صفحه اول"
                            accept="image/jpeg,image/png,image/webp,.pdf"
                            recordKey="front"
                        />
                        <FileUploadField
                            uuid={uuid}
                            categorySlug={DOC_CATEGORY_SLUGS.BIRTH_CERTIFICATE}
                            label="شناسنامه — صفحه دوم"
                            accept="image/jpeg,image/png,image/webp,.pdf"
                            recordKey="back"
                        />
                        <FileUploadField
                            uuid={uuid}
                            categorySlug={DOC_CATEGORY_SLUGS.RESUME}
                            label="رزومه"
                            accept=".pdf,image/jpeg,image/png,image/webp"
                        />
                    </div>

                    {orphanedEntries.map(({ slug, label, orphans }) => (
                        <div key={`orphan-${slug}`} className="space-y-2">
                            <span className="text-xs text-muted-foreground">
                                سایر مدارک بارگذاری‌شده — {label}
                            </span>
                            <div className="flex flex-wrap gap-3">
                                {orphans.map((doc) => (
                                    <div key={doc.id} className="flex flex-col items-start gap-0.5">
                                        <DocumentThumbnail
                                            document={doc}
                                            variant="compact"
                                            size="sm"
                                            showName
                                        />
                                        <div className="flex items-center gap-1 px-1">
                                            <span className="text-[10px] text-muted-foreground">
                                                {getRecordKeyLabel(doc.record_key) ?? doc.record_key}
                                            </span>
                                            <ConfirmDeleteButton
                                                iconOnly
                                                onConfirm={() => deleteMutation.mutate(doc)}
                                                label="حذف"
                                            />
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    ))}
                </div>

                {/* ── مدارک تکمیلی ── */}
                <div className="space-y-4">
                    <div className="flex items-center justify-between">
                        <span className="text-sm font-medium">مدارک تکمیلی</span>
                        <div className="flex items-center gap-2">
                            <Select
                                value={pickSlug}
                                onValueChange={(v) => v != null && setPickSlug(v)}
                                itemToStringLabel={(val) =>
                                    EXTRA_DOC_OPTIONS.find((o) => o.slug === val)?.label ?? val
                                }
                            >
                                <SelectTrigger className="w-48 h-8 text-xs">
                                    <SelectValue placeholder="نوع مدرک" />
                                </SelectTrigger>
                                <SelectContent>
                                    {EXTRA_DOC_OPTIONS.map((opt) => (
                                        <SelectItem key={opt.slug} value={opt.slug}>
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
                            {extraDocs.map((entry, i) => (
                                <FileUploadField
                                    key={`${entry.slug}-${i}`}
                                    uuid={uuid}
                                    categorySlug={entry.slug}
                                    label={entry.label + (entry.notes ? ` — ${entry.notes}` : "")}
                                    accept=".pdf,image/jpeg,image/png,image/webp"
                                    multiple
                                    maxFiles={5}
                                />
                            ))}
                        </div>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}
