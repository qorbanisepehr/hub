import { useRef, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import {
    IconCircleCheck,
    IconFiles,
    IconLoader2,
    IconRefresh,
    IconUpload,
} from "@tabler/icons-react";

import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import { Label } from "@/components/ui/label";
import {
    RadioGroup,
    RadioGroupItem,
} from "@/components/ui/radio-group";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/components/ui/table";
import { Textarea } from "@/components/ui/textarea";
import { ErrorBanner } from "@/components/layout";
import { getApiError } from "@/lib/error-utils";
import {
    ARCHIVE_DOCUMENT_PATTERN,
    confirmDocumentBundle,
    dryRunDocumentBundle,
    fetchImportEntities,
    type DocumentBundleImported,
    type DocumentBundleOutcome,
    type DocumentBundlePlan,
    type DocumentBundlePlanRow,
    type DocumentBundleRejected,
    type ImportEntity,
} from "./api";

type Phase = "upload" | "preview" | "report";

const FILE_TYPES_ACCEPTED = ".pdf,.jpg,.jpeg,.png,.webp";

const STATUS_LABEL: Record<DocumentBundlePlanRow["status"], string> = {
    ready: "آمادهٔ ورود",
    duplicate: "تکراری",
    pattern: "نامطابق با الگو",
    no_meeting: "جلسه یافت نشد",
};

/**
 * The document-bundle arm of «ورود اطلاعات»: pick many scanned files whose
 * names carry the pattern contract, review how each one maps (file →
 * registry number → meeting date) before anything is written, then confirm.
 * Every file is reported — nothing lands silently.
 */
export function DocumentBundleImport() {
    const queryClient = useQueryClient();
    const fileInput = useRef<HTMLInputElement>(null);

    const [entityName, setEntityName] = useState<string>("");
    const [files, setFiles] = useState<File[]>([]);
    const [pattern, setPattern] = useState<string>(ARCHIVE_DOCUMENT_PATTERN);
    const [phase, setPhase] = useState<Phase>("upload");
    const [plan, setPlan] = useState<DocumentBundlePlan | null>(null);
    const [outcome, setOutcome] = useState<DocumentBundleOutcome | null>(null);
    const [capNotice, setCapNotice] = useState<string | null>(null);

    const entitiesQuery = useQuery({
        queryKey: ["imports", "entities"],
        queryFn: async () => (await fetchImportEntities()).data.data,
    });

    const entities: ImportEntity[] =
        (entitiesQuery.data ?? []).filter((e) => (e.kind ?? "tabular") === "document") ?? [];

    const maxFiles =
        entities.find((entity) => entity.entity === entityName)?.max_files ?? 50;

    const dryRun = useMutation({
        mutationFn: async () => {
            if (files.length === 0) throw new Error("no files");
            return (await dryRunDocumentBundle(entityName, files, pattern)).data.data;
        },
        onSuccess: (data) => {
            setPlan(data);
            setOutcome(null);
            setPhase("preview");
        },
    });

    const confirm = useMutation({
        mutationFn: async () => {
            if (files.length === 0) throw new Error("no files");
            return (await confirmDocumentBundle(entityName, files, pattern)).data.data;
        },
        onSuccess: (data) => {
            setOutcome(data);
            setPhase("report");
            // Meetings may have gained مصوب — refresh their document lists.
            void queryClient.invalidateQueries({ queryKey: ["board-meeting-documents"] });
        },
    });

    const reset = () => {
        setFiles([]);
        setPlan(null);
        setOutcome(null);
        setCapNotice(null);
        setPhase("upload");

        if (fileInput.current) {
            fileInput.current.value = "";
        }
    };

    const busy = dryRun.isPending || confirm.isPending;

    return (
        <Card>
            <CardHeader>
                <CardTitle>ورود گروهی اسناد از آرشیو</CardTitle>
                <CardDescription>
                    نام هر فایل، بر اساس الگو، جلسه و شمارهٔ ثبت مصوبه را تعیین
                    می‌کند. پیش‌نمایش نشان می‌دهد هر فایل به کدام جلسه و با کدام
                    شماره وارد می‌شود — تا پیش از تأیید، هیچ فایلی ذخیره
                    نمی‌شود.
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
                {phase === "upload" && (
                    <>
                        <div className="space-y-2">
                            <Label>موجودیت</Label>
                            <RadioGroup
                                value={entityName}
                                onValueChange={(value) => {
                                    setEntityName(value);
                                    setPlan(null);
                                    setCapNotice(null);
                                }}
                                className="flex flex-wrap gap-4"
                            >
                                {entities.map((entity) => (
                                    <div
                                        key={entity.entity}
                                        className="flex items-center gap-2"
                                    >
                                        <RadioGroupItem
                                            value={entity.entity}
                                            id={`bundle-entity-${entity.entity}`}
                                        />
                                        <Label
                                            htmlFor={`bundle-entity-${entity.entity}`}
                                            className="font-normal cursor-pointer"
                                        >
                                            {entity.label}
                                        </Label>
                                    </div>
                                ))}
                            </RadioGroup>
                        </div>

                        <div className="space-y-2">
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <Label>الگوی نام فایل</Label>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    disabled={pattern === ARCHIVE_DOCUMENT_PATTERN}
                                    onClick={() => setPattern(ARCHIVE_DOCUMENT_PATTERN)}
                                >
                                    <IconRefresh className="size-4" />
                                    الگوی پیش‌فرض
                                </Button>
                            </div>
                            <Textarea
                                value={pattern}
                                onChange={(event) => setPattern(event.target.value)}
                                dir="ltr"
                                rows={2}
                                className="font-mono text-xs"
                            />
                            <p className="text-xs text-muted-foreground">
                                گروه‌های نامدار: number (شماره)، date (تاریخ شمسی
                                yyyy-MM-dd)، ordinal (اختیاری، پسوند -N). مثال
                                آرشیو: 1002-2(1401-10-12).pdf
                            </p>
                        </div>

                        <div className="flex flex-wrap items-center gap-2">
                            <Button
                                variant="outline"
                                disabled={!entityName}
                                onClick={() => fileInput.current?.click()}
                            >
                                <IconFiles className="size-4" />
                                انتخاب فایل‌ها
                            </Button>
                            <input
                                ref={fileInput}
                                type="file"
                                multiple
                                accept={FILE_TYPES_ACCEPTED}
                                className="hidden"
                                onChange={(event) => {
                                    const picked = Array.from(event.target.files ?? []);

                                    setFiles(picked.slice(0, maxFiles));
                                    setPlan(null);
                                    setPhase("upload");
                                    setCapNotice(
                                        picked.length > maxFiles
                                            ? `حداکثر ${maxFiles} فایل در هر اجرا مجاز است؛ ${picked.length - maxFiles} فایل نادیده گرفته شد.`
                                            : null,
                                    );
                                }}
                            />

                            {files.length > 0 && (
                                <span className="flex items-center gap-1.5 text-sm text-muted-foreground">
                                    <IconFiles className="size-4" />
                                    {files.length} فایل انتخاب شد
                                </span>
                            )}

                            <span className="text-xs text-muted-foreground">
                                حداکثر {maxFiles} فایل در هر اجرا
                            </span>
                        </div>

                        {capNotice && (
                            <p className="text-xs text-amber-600">{capNotice}</p>
                        )}

                        {files.length > 0 && (
                            <ul className="space-y-1 rounded-lg bg-muted p-3 text-xs text-muted-foreground">
                                {files.map((file) => (
                                    <li key={`${file.name}-${file.size}`} className="truncate">
                                        {file.name}
                                    </li>
                                ))}
                            </ul>
                        )}

                        {dryRun.isError && (
                            <ErrorBanner
                                message={
                                    getApiError(dryRun.error) ??
                                    "خطا در بررسی فایل‌ها."
                                }
                            />
                        )}

                        <div>
                            <Button
                                disabled={!entityName || files.length === 0 || busy}
                                onClick={() => dryRun.mutate()}
                            >
                                {dryRun.isPending ? (
                                    <IconLoader2 className="size-4 animate-spin" />
                                ) : (
                                    <IconUpload className="size-4" />
                                )}
                                بررسی فایل‌ها
                            </Button>
                        </div>
                    </>
                )}

                {phase === "preview" && plan && (
                    <BundlePlanPreview
                        plan={plan}
                        busy={busy}
                        onConfirm={() => confirm.mutate()}
                        onBack={reset}
                    />
                )}

                {phase === "report" && outcome && (
                    <BundleOutcomeReport outcome={outcome} onDone={reset} />
                )}
            </CardContent>
        </Card>
    );
}

/**
 * The dry-run preview: how each file maps onto the registry — the «what and
 * how» the user asked for, before anything is written.
 */
function BundlePlanPreview({
    plan,
    busy,
    onConfirm,
    onBack,
}: {
    plan: DocumentBundlePlan;
    busy: boolean;
    onConfirm: () => void;
    onBack: () => void;
}) {
    return (
        <div className="space-y-6">
            <div className="flex flex-wrap items-center gap-2">
                {plan.importable > 0 && (
                    <Badge variant="secondary">آمادهٔ ورود</Badge>
                )}
                {plan.rejected > 0 && (
                    <Badge variant="destructive">نیازمند بررسی</Badge>
                )}
                <span className="text-sm text-muted-foreground">
                    {plan.pattern}
                </span>
            </div>

            <div className="grid grid-cols-3 gap-4 text-center">
                <BundleStatBox label="کل فایل‌ها" value={plan.total} />
                <BundleStatBox label="قابل ورود" value={plan.importable} tone="success" />
                <BundleStatBox
                    label="رد شده"
                    value={plan.rejected}
                    tone={plan.rejected > 0 ? "danger" : undefined}
                />
            </div>

            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>نام فایل</TableHead>
                        <TableHead>شمارهٔ ثبت</TableHead>
                        <TableHead>جلسه</TableHead>
                        <TableHead>وضعیت</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {plan.rows.map((row) => (
                        <BundlePlanRowView key={row.file} row={row} />
                    ))}
                </TableBody>
            </Table>

            <div className="flex flex-wrap gap-2">
                <Button
                    disabled={busy || plan.importable === 0}
                    onClick={onConfirm}
                >
                    {busy ? (
                        <IconLoader2 className="size-4 animate-spin" />
                    ) : (
                        <IconCircleCheck className="size-4" />
                    )}
                    تأیید و ورود {plan.importable} فایل
                </Button>
                <Button variant="ghost" onClick={onBack}>
                    بازگشت
                </Button>
            </div>
        </div>
    );
}

function BundlePlanRowView({ row }: { row: DocumentBundlePlanRow }) {
    const rejected = row.status !== "ready";
    const duplicate = row.status === "duplicate";

    return (
        <TableRow>
            <TableCell className="max-w-64 truncate font-medium">{row.file}</TableCell>
            <TableCell dir="ltr" className="text-left">
                {row.number ?? "—"}
            </TableCell>
            <TableCell>{row.meeting?.label ?? row.date ?? "—"}</TableCell>
            <TableCell>
                <div className="space-y-1">
                    <Badge
                        variant={
                            rejected
                                ? duplicate
                                    ? "outline"
                                    : "destructive"
                                : "secondary"
                        }
                        className={
                            duplicate ? "text-amber-600" : undefined
                        }
                    >
                        {STATUS_LABEL[row.status]}
                    </Badge>
                    {row.reason && (
                        <p className="text-xs text-muted-foreground">{row.reason}</p>
                    )}
                </div>
            </TableCell>
        </TableRow>
    );
}

/** The post-confirm report: every file accounted for. */
function BundleOutcomeReport({
    outcome,
    onDone,
}: {
    outcome: DocumentBundleOutcome;
    onDone: () => void;
}) {
    const { imported, skipped, failed } = outcome;

    return (
        <div className="space-y-6">
            <div className="flex flex-wrap items-center gap-2">
                <Badge variant={failed.length === 0 ? "secondary" : "destructive"}>
                    {failed.length === 0
                        ? imported.length > 0
                            ? "ورود انجام شد"
                            : "ورودی ثبت نشد"
                        : "ورود با خطا"}
                </Badge>
            </div>

            <div className="grid grid-cols-3 gap-4 text-center">
                <BundleStatBox label="وارد شده" value={imported.length} tone="success" />
                <BundleStatBox
                    label="رد شده"
                    value={skipped.length}
                    tone={skipped.length > 0 ? "danger" : undefined}
                />
                <BundleStatBox
                    label="ناموفق"
                    value={failed.length}
                    tone={failed.length > 0 ? "danger" : undefined}
                />
            </div>

            {imported.length > 0 && (
                <div className="space-y-2">
                    <Label>وارد شده</Label>
                    <ul className="space-y-2">
                        {imported.map((item) => (
                            <BundleImportedRow key={item.source_file} item={item} />
                        ))}
                    </ul>
                </div>
            )}

            {skipped.length > 0 && (
                <div className="space-y-2">
                    <Label>رد شده</Label>
                    <ul className="space-y-2">
                        {skipped.map((item) => (
                            <BundleRejectedRow
                                key={item.file + item.reason_code}
                                item={item}
                                tone="warning"
                            />
                        ))}
                    </ul>
                </div>
            )}

            {failed.length > 0 && (
                <div className="space-y-2">
                    <Label>ناموفق</Label>
                    <ul className="space-y-2">
                        {failed.map((item) => (
                            <BundleRejectedRow
                                key={item.file + item.reason_code}
                                item={item}
                                tone="danger"
                            />
                        ))}
                    </ul>
                </div>
            )}

            <Button variant="outline" onClick={onDone}>
                وارد کردن دستهٔ دیگر
            </Button>
        </div>
    );
}

function BundleImportedRow({ item }: { item: DocumentBundleImported }) {
    const registryNumber =
        typeof item.document.metadata.registry_number === "string"
            ? item.document.metadata.registry_number
            : null;

    return (
        <li className="rounded-lg bg-emerald-50 p-3 text-sm text-emerald-700">
            <span className="font-medium">{item.source_file}</span>
            {item.meeting && <span> → جلسهٔ {item.meeting.label}</span>}
            {registryNumber && <span> ({registryNumber})</span>}
        </li>
    );
}

function BundleRejectedRow({
    item,
    tone,
}: {
    item: DocumentBundleRejected;
    tone: "warning" | "danger";
}) {
    return (
        <li
            className={`rounded-lg p-3 text-sm ${
                tone === "warning"
                    ? "bg-amber-50 text-amber-700"
                    : "bg-destructive/10 text-destructive"
            }`}
        >
            <span className="font-medium">{item.file}</span>
            <span className="text-xs"> — {item.reason}</span>
        </li>
    );
}

function BundleStatBox({
    label,
    value,
    tone,
}: {
    label: string;
    value: number;
    tone?: "success" | "danger";
}) {
    return (
        <div className="rounded-lg border p-3">
            <div
                className={`text-2xl font-bold ${
                    tone === "success"
                        ? "text-emerald-600"
                        : tone === "danger"
                          ? "text-destructive"
                          : ""
                }`}
            >
                {value}
            </div>
            <div className="mt-1 text-xs text-muted-foreground">{label}</div>
        </div>
    );
}