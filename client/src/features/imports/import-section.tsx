import { useRef, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import {
    IconCircleCheck,
    IconDownload,
    IconFileSpreadsheet,
    IconLoader2,
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
import { ErrorBanner, ErrorSection } from "@/components/layout";
import { EmptyState } from "@/components/layout";
import { getApiError } from "@/lib/error-utils";
import { saveBlobResponse } from "@/lib/download";
import {
    confirmImport,
    dryRunImport,
    fetchImportEntities,
    fetchImportTemplate,
    type ImportEntity,
    type ImportPlan,
    type ImportRejectedRow,
} from "./api";

type Phase = "upload" | "preview" | "report";

const FORMATS = [
    { value: "xlsx", label: "اکسل (xlsx)" },
    { value: "csv", label: "CSV (سازگار با Excel)" },
] as const;

type Format = (typeof FORMATS)[number]["value"];

/** Number of rejected rows shown inline before «and N more». */
const MAX_SHOWN_REJECTIONS = 5;

function formatLabel(format: string): string {
    return FORMATS.find((f) => f.value === format)?.label ?? format;
}

/**
 * The settings «ورود اطلاعات» tab: upload a filled template, inspect the
 * dry-run preview (mapping + per-row statuses), then confirm to persist.
 * Nothing is written until the user confirms — the dry-run endpoint never
 * persists, and the confirm endpoint re-validates server-side.
 */
export function ImportSection() {
    const queryClient = useQueryClient();
    const fileInput = useRef<HTMLInputElement>(null);

    const [entityName, setEntityName] = useState<string>("");
    const [format, setFormat] = useState<Format>("xlsx");
    const [file, setFile] = useState<File | null>(null);
    const [phase, setPhase] = useState<Phase>("upload");
    const [plan, setPlan] = useState<ImportPlan | null>(null);
    const [outcome, setOutcome] = useState<{
        created: number;
        updated: number;
        rejected: ImportRejectedRow[];
    } | null>(null);

    const entitiesQuery = useQuery({
        queryKey: ["imports", "entities"],
        queryFn: async () => (await fetchImportEntities()).data.data,
    });

    const entities: ImportEntity[] = entitiesQuery.data ?? [];

    const dryRun = useMutation({
        mutationFn: async () => {
            if (!file) throw new Error("no file");
            return (await dryRunImport(entityName, file, format)).data.data;
        },
        onSuccess: (data) => {
            setPlan(data);
            setOutcome(null);
            setPhase("preview");
        },
    });

    const confirm = useMutation({
        mutationFn: async () => {
            if (!file) throw new Error("no file");
            return (await confirmImport(entityName, file, format)).data.data;
        },
        onSuccess: (data) => {
            setOutcome({
                created: data.created,
                updated: data.updated,
                rejected: data.rejected,
            });
            setPhase("report");
            // Employees (and any future entity) may have changed.
            void queryClient.invalidateQueries();
        },
    });

    const downloadTemplate = async () => {
        const response = await fetchImportTemplate(entityName, format);
        saveBlobResponse(
            response,
            `${entityName}-template.${format}`,
            format === "csv"
                ? "text/csv;charset=utf-8"
                : "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
        );
    };

    const reset = () => {
        setFile(null);
        setPlan(null);
        setOutcome(null);
        setPhase("upload");

        if (fileInput.current) {
            fileInput.current.value = "";
        }
    };

    const busy = dryRun.isPending || confirm.isPending;

    if (entitiesQuery.isLoading) {
        return (
            <Card>
                <CardContent className="flex items-center gap-2 py-10 text-sm text-muted-foreground">
                    <IconLoader2 className="size-4 animate-spin" />
                    در حال بارگذاری…
                </CardContent>
            </Card>
        );
    }

    if (entitiesQuery.isError) {
        return (
            <ErrorSection
                description="خطا در دریافت فهرست موجودیت‌ها."
                onRetry={() => void entitiesQuery.refetch()}
            />
        );
    }

    if (entities.length === 0) {
        return (
            <Card>
                <CardHeader>
                    <CardTitle>ورود اطلاعات</CardTitle>
                    <CardDescription>
                        داده‌ها را از فایل قالب به سیستم وارد کنید.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <EmptyState
                        icon={IconFileSpreadsheet}
                        message="موجودیتی برای ورود اطلاعات تعریف نشده است."
                        variant="compact"
                    />
                </CardContent>
            </Card>
        );
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle>ورود اطلاعات</CardTitle>
                <CardDescription>
                    فایل قالب را تکمیل کنید، پیش‌نمایش را بررسی کنید و سپس
                    تأیید کنید. تا پیش از تأیید، هیچ داده‌ای ذخیره نمی‌شود.
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
                {phase === "upload" && (
                    <>
                        <div className="space-y-2">
                            <Label>نوع داده</Label>
                            <RadioGroup
                                value={entityName}
                                onValueChange={(value) => {
                                    setEntityName(value);
                                    setFile(null);
                                    setPlan(null);
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
                                            id={`import-entity-${entity.entity}`}
                                        />
                                        <Label
                                            htmlFor={`import-entity-${entity.entity}`}
                                            className="font-normal cursor-pointer"
                                        >
                                            {entity.label}
                                        </Label>
                                    </div>
                                ))}
                            </RadioGroup>
                        </div>

                        <div className="space-y-2">
                            <Label>قالب فایل</Label>
                            <RadioGroup
                                value={format}
                                onValueChange={(value) =>
                                    setFormat(value as Format)
                                }
                                className="flex flex-wrap gap-4"
                            >
                                {FORMATS.map((option) => (
                                    <div
                                        key={option.value}
                                        className="flex items-center gap-2"
                                    >
                                        <RadioGroupItem
                                            value={option.value}
                                            id={`import-format-${option.value}`}
                                        />
                                        <Label
                                            htmlFor={`import-format-${option.value}`}
                                            className="font-normal cursor-pointer"
                                        >
                                            {option.label}
                                        </Label>
                                    </div>
                                ))}
                            </RadioGroup>
                        </div>

                        <div className="flex flex-wrap items-center gap-2">
                            <Button
                                variant="outline"
                                disabled={!entityName}
                                onClick={() => void downloadTemplate()}
                            >
                                <IconDownload className="size-4" />
                                دانلود قالب خالی
                            </Button>

                            <Button
                                variant="outline"
                                disabled={!entityName}
                                onClick={() => fileInput.current?.click()}
                            >
                                <IconUpload className="size-4" />
                                انتخاب فایل
                            </Button>
                            <input
                                ref={fileInput}
                                type="file"
                                accept=".xlsx,.csv"
                                className="hidden"
                                onChange={(event) => {
                                    const selected = event.target.files?.[0];
                                    setFile(selected ?? null);
                                    setPlan(null);
                                    setPhase("upload");
                                }}
                            />

                            {file && (
                                <span className="flex items-center gap-1.5 text-sm text-muted-foreground">
                                    <IconFileSpreadsheet className="size-4" />
                                    {file.name}
                                </span>
                            )}
                        </div>

                        {dryRun.isError && (
                            <ErrorBanner
                                message={
                                    getApiError(dryRun.error) ??
                                    "خطا در بررسی فایل."
                                }
                            />
                        )}

                        <div>
                            <Button
                                disabled={!file || !entityName || busy}
                                onClick={() => dryRun.mutate()}
                            >
                                {dryRun.isPending ? (
                                    <IconLoader2 className="size-4 animate-spin" />
                                ) : (
                                    <IconUpload className="size-4" />
                                )}
                                بررسی فایل
                            </Button>
                        </div>
                    </>
                )}

                {phase === "preview" && plan && (
                    <PlanPreview
                        plan={plan}
                        busy={busy}
                        onConfirm={() => confirm.mutate()}
                        onBack={reset}
                    />
                )}

                {phase === "report" && outcome && (
                    <OutcomeReport
                        outcome={outcome}
                        onDone={reset}
                    />
                )}
            </CardContent>
        </Card>
    );
}

/** The dry-run preview: mapping summary, counts, and rejection list. */
function PlanPreview({
    plan,
    busy,
    onConfirm,
    onBack,
}: {
    plan: ImportPlan;
    busy: boolean;
    onConfirm: () => void;
    onBack: () => void;
}) {
    const unknown = plan.mapping.unknown;

    return (
        <div className="space-y-6">
            <div className="flex flex-wrap items-center gap-2">
                <Badge variant={plan.valid ? "secondary" : "destructive"}>
                    {plan.valid ? "آماده ورود" : "نیازمند اصلاح"}
                </Badge>
                <Badge variant="outline">
                    {formatLabel(plan.source.format)}
                </Badge>
                {plan.source.template && (
                    <Badge variant="outline">قالب رسمی</Badge>
                )}
                <span className="text-sm text-muted-foreground">
                    {plan.source.file}
                </span>
            </div>

            <div className="grid grid-cols-3 gap-4 text-center">
                <StatBox label="ردیف فایل" value={plan.total} />
                <StatBox
                    label="قابل ورود"
                    value={plan.importable}
                    tone="success"
                />
                <StatBox
                    label="رد شده"
                    value={plan.rejected.length}
                    tone={plan.rejected.length > 0 ? "danger" : undefined}
                />
            </div>

            {plan.mapping.missing_required.length > 0 && (
                <ErrorBanner
                    message={`ستون‌های الزامی در فایل غایب‌اند: ${plan.mapping.missing_required.join("، ")}`}
                />
            )}

            {unknown.length > 0 && (
                <div className="rounded-lg bg-muted p-3 text-sm text-muted-foreground">
                    ستون‌های ناشناس (نادیده گرفته می‌شوند): {unknown.join("، ")}
                </div>
            )}

            {plan.rejected.length > 0 && (
                <div className="space-y-2">
                    <Label>ردیف‌های رد شده</Label>
                    <ul className="space-y-2">
                        {plan.rejected
                            .slice(0, MAX_SHOWN_REJECTIONS)
                            .map((rejection) => (
                                <li
                                    key={rejection.row}
                                    className="rounded-lg bg-destructive/10 p-3 text-sm text-destructive"
                                >
                                    <span className="font-medium">
                                        ردیف {rejection.row}:
                                    </span>{" "}
                                    {Object.values(rejection.errors)
                                        .flat()
                                        .join(" — ")}
                                </li>
                            ))}
                    </ul>
                    {plan.rejected.length > MAX_SHOWN_REJECTIONS && (
                        <p className="text-xs text-muted-foreground">
                            و {plan.rejected.length - MAX_SHOWN_REJECTIONS}{" "}
                            ردیف دیگر…
                        </p>
                    )}
                </div>
            )}

            <div className="flex flex-wrap gap-2">
                <Button
                    disabled={busy || !plan.valid || plan.importable === 0}
                    onClick={onConfirm}
                >
                    {busy ? (
                        <IconLoader2 className="size-4 animate-spin" />
                    ) : (
                        <IconCircleCheck className="size-4" />
                    )}
                    تأیید و ورود {plan.importable} ردیف
                </Button>
                <Button variant="ghost" onClick={onBack}>
                    بازگشت
                </Button>
            </div>
        </div>
    );
}

/** The post-confirm report: what landed and what was refused. */
function OutcomeReport({
    outcome,
    onDone,
}: {
    outcome: { created: number; updated: number; rejected: ImportRejectedRow[] };
    onDone: () => void;
}) {
    return (
        <div className="space-y-6">
            <div className="flex flex-wrap items-center gap-2">
                <Badge
                    variant={
                        outcome.rejected.length === 0
                            ? "secondary"
                            : "destructive"
                    }
                >
                    {outcome.rejected.length === 0
                        ? "ورود کامل شد"
                        : "ورود با موارد رد شده"}
                </Badge>
            </div>

            <div className="grid grid-cols-3 gap-4 text-center">
                <StatBox label="ایجاد شده" value={outcome.created} tone="success" />
                <StatBox label="به‌روزرسانی شده" value={outcome.updated} />
                <StatBox
                    label="رد شده"
                    value={outcome.rejected.length}
                    tone={outcome.rejected.length > 0 ? "danger" : undefined}
                />
            </div>

            {outcome.rejected.length > 0 && (
                <div className="space-y-2">
                    <Label>ردیف‌های رد شده</Label>
                    <ul className="space-y-2">
                        {outcome.rejected
                            .slice(0, MAX_SHOWN_REJECTIONS)
                            .map((rejection) => (
                                <li
                                    key={rejection.row}
                                    className="rounded-lg bg-destructive/10 p-3 text-sm text-destructive"
                                >
                                    <span className="font-medium">
                                        ردیف {rejection.row}:
                                    </span>{" "}
                                    {Object.values(rejection.errors)
                                        .flat()
                                        .join(" — ")}
                                </li>
                            ))}
                    </ul>
                    {outcome.rejected.length > MAX_SHOWN_REJECTIONS && (
                        <p className="text-xs text-muted-foreground">
                            و {outcome.rejected.length - MAX_SHOWN_REJECTIONS}{" "}
                            ردیف دیگر…
                        </p>
                    )}
                </div>
            )}

            <Button variant="outline" onClick={onDone}>
                ورود فایل دیگر
            </Button>
        </div>
    );
}

function StatBox({
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
