import { useRef, useState } from "react";
import {
    IconCircleCheck,
    IconCircleMinus,
    IconCircleX,
    IconFileUpload,
    IconFiles,
    IconLoader2,
    IconPaperclip,
    IconX,
} from "@tabler/icons-react";

import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
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
import { Skeleton } from "@/components/ui/skeleton";
import { Textarea } from "@/components/ui/textarea";
import { EmptyState } from "@/components/layout";
import { DocumentFileItem } from "@/components/documents/document-file-item";
import {
    useBulkUploadMeetingDocuments,
    useMeetingDocuments,
    useUploadMeetingDocument,
} from "../hooks";
import { getApiError } from "@/lib/error-utils";
import { formatBytes } from "@/lib/file-utils";
import type { BulkDocumentUploadReport } from "../types";

/**
 * The meeting's documents — the one place its registry numbers live now.
 *
 * A مصوبه's number no longer sits on the meeting row (vault idea 567): it is
 * written onto the scan's usage `metadata` when the document is uploaded, so
 * the row below is the number, the category and the file all at once, and
 * there is no second list to hold against it.
 *
 * The rows and the delete action come from the shared document components, so
 * this tab gets the same thumbnail, the same click-to-preview overlay and the
 * same deletion as an employee's documents for free — the registry is one more
 * place documents live, not a second implementation of them. Upload stays
 * local: that endpoint takes no document category, because a board meeting's
 * attachment is a meeting record rather than a section placement, so it does
 * not fit the shared uploader's contract and forcing it to would invent a
 * category the server never asked for.
 */
export function MeetingDocumentsTab({ meetingId }: { meetingId: string }) {
    const { data, isLoading } = useMeetingDocuments(meetingId);
    const upload = useUploadMeetingDocument(meetingId);

    const documents = data?.data ?? [];
    const capabilities = data?.capabilities;

    const [uploadOpen, setUploadOpen] = useState(false);
    const [bulkUploadOpen, setBulkUploadOpen] = useState(false);

    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between">
                <CardTitle className="flex items-center gap-2 text-lg">
                    <IconPaperclip />
                    اسناد و مصوبات
                    {documents.length > 0 && (
                        <Badge variant="outline">
                            {documents.length.toLocaleString("fa-IR")}
                        </Badge>
                    )}
                </CardTitle>
                {capabilities?.upload && (
                    <div className="flex items-center gap-2">
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={() => setBulkUploadOpen(true)}
                        >
                            <IconFiles data-icon="inline-start" />
                            بارگذاری گروهی
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            onClick={() => setUploadOpen(true)}
                        >
                            <IconFileUpload data-icon="inline-start" />
                            بارگذاری سند
                        </Button>
                    </div>
                )}
            </CardHeader>

            <CardContent className="flex flex-col gap-4">
                {isLoading ? (
                    <div className="flex flex-col gap-3">
                        <Skeleton className="h-10 w-full" />
                        <Skeleton className="h-10 w-5/6" />
                    </div>
                ) : documents.length === 0 ? (
                    <EmptyState
                        icon={IconPaperclip}
                        message="سندی برای این جلسه بارگذاری نشده است"
                    />
                ) : (
                    <ul className="divide-y">
                        {documents.map((doc) => (
                            <li key={doc.usage_id} className="py-2.5">
                                <DocumentFileItem
                                    uuid={meetingId}
                                    entity="board-meetings"
                                    doc={doc}
                                    subtitle={[
                                        typeof doc.metadata?.registry_number ===
                                        "string"
                                            ? doc.metadata.registry_number
                                            : null,
                                        doc.category?.name,
                                        doc.size > 0
                                            ? formatBytes(doc.size)
                                            : null,
                                    ]
                                        .filter(Boolean)
                                        .join(" — ")}
                                    // The backend advertises what may be
                                    // done; the component only renders
                                    // the delete affordance when it may.
                                    actionsEnabled={
                                        capabilities?.delete ?? false
                                    }
                                />
                            </li>
                        ))}
                    </ul>
                )}
            </CardContent>

            <MeetingUploadDialog
                meetingId={meetingId}
                open={uploadOpen}
                onOpenChange={setUploadOpen}
                isPending={upload.isPending}
            />

            <MeetingBulkUploadDialog
                meetingId={meetingId}
                open={bulkUploadOpen}
                onOpenChange={setBulkUploadOpen}
            />
        </Card>
    );
}

/** Upload dialog: file + required registry number (category is fixed server-side). */
function MeetingUploadDialog({
    meetingId,
    open,
    onOpenChange,
    isPending,
}: {
    meetingId: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    isPending: boolean;
}) {
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [file, setFile] = useState<File | null>(null);
    const [registryNumber, setRegistryNumber] = useState("");
    const [title, setTitle] = useState("");
    const [notes, setNotes] = useState("");
    const [error, setError] = useState<string | null>(null);

    const upload = useUploadMeetingDocument(meetingId);

    function reset() {
        setFile(null);
        setRegistryNumber("");
        setTitle("");
        setNotes("");
        setError(null);
        if (fileInputRef.current) {
            fileInputRef.current.value = "";
        }
    }

    function handleSubmit() {
        if (!file) {
            setError("انتخاب فایل الزامی است");
            return;
        }

        if (registryNumber.trim() === "") {
            setError("شمارهٔ مصوبه الزامی است");
            return;
        }

        const formData = new FormData();
        formData.append("file", file);
        formData.append("registry_number", registryNumber.trim());
        if (title.trim() !== "") {
            formData.append("title", title.trim());
        }
        if (notes.trim() !== "") {
            formData.append("notes", notes.trim());
        }

        setError(null);
        upload.mutate(formData, {
            onSuccess: () => {
                reset();
                onOpenChange(false);
            },
            onError: (err) =>
                setError(getApiError(err) ?? "بارگذاری ناموفق بود"),
        });
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>بارگذاری سند جلسه</DialogTitle>
                    <DialogDescription>
                        مصوبات و پیوست‌های جلسه در آرشیو اسناد هاب نگهداری
                        می‌شوند.
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-4 py-2">
                    <div className="grid gap-2">
                        <Label>فایل</Label>
                        <Input
                            ref={fileInputRef}
                            type="file"
                            accept=".pdf,.jpg,.jpeg,.png,.webp"
                            onChange={(e) =>
                                setFile(e.target.files?.[0] ?? null)
                            }
                        />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="meeting-doc-registry">
                            شمارهٔ مصوبه
                        </Label>
                        <Input
                            id="meeting-doc-registry"
                            dir="ltr"
                            placeholder="مثلاً ۱۰۰۱/ص/۱۴۰۴"
                            value={registryNumber}
                            onChange={(e) =>
                                setRegistryNumber(e.target.value)
                            }
                        />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="meeting-doc-title">
                            عنوان (اختیاری)
                        </Label>
                        <Input
                            id="meeting-doc-title"
                            value={title}
                            onChange={(e) => setTitle(e.target.value)}
                        />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="meeting-doc-notes">
                            یادداشت (اختیاری)
                        </Label>
                        <Textarea
                            id="meeting-doc-notes"
                            rows={2}
                            value={notes}
                            onChange={(e) => setNotes(e.target.value)}
                        />
                    </div>

                    {error && (
                        <p className="text-destructive text-sm">{error}</p>
                    )}
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        انصراف
                    </Button>
                    <Button
                        type="button"
                        onClick={handleSubmit}
                        disabled={
                            isPending ||
                            !file ||
                            registryNumber.trim() === ""
                        }
                    >
                        <IconFileUpload data-icon="inline-start" />
                        بارگذاری
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

/** Mirror of config `documents.board-meeting.max_files` — the server enforces it regardless. */
const BULK_MAX_FILES = 50;

interface BulkRow {
    key: number;
    file: File;
    registryNumber: string;
    title: string;
}

/**
 * Prefill the registry number from a file only when the name matches the
 * known archive shape (`1001(1401-01-17).pdf`, `1001-1(1401-01-17).pdf`). A
 * bare leading year like `1404-budget.pdf` must not be guessed as a number.
 */
function deriveRegistryNumber(fileName: string): string | null {
    const match = fileName.match(
        /^(\d{4})(?:-\d)?\([0-9]{4}-[0-9]{2}-[0-9]{2}\)\.\w+$/,
    );

    return match ? match[1] : null;
}

/**
 * Bulk import dialog: one server call, many files, each paired with its own
 * registry number. The server reports per-file outcomes (imported / duplicate
 * / failed), and the dialog shows that report instead of a single success —
 * a batch that mostly landed is a normal result with its exceptions named.
 */
function MeetingBulkUploadDialog({
    meetingId,
    open,
    onOpenChange,
}: {
    meetingId: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const fileInputRef = useRef<HTMLInputElement>(null);
    const nextKey = useRef(0);
    const [rows, setRows] = useState<BulkRow[]>([]);
    const [error, setError] = useState<string | null>(null);
    const [report, setReport] = useState<BulkDocumentUploadReport | null>(null);

    const upload = useBulkUploadMeetingDocuments(meetingId);

    function reset() {
        setRows([]);
        setReport(null);
        setError(null);
        if (fileInputRef.current) {
            fileInputRef.current.value = "";
        }
    }

    function addFiles(files: FileList | null) {
        if (!files || files.length === 0) {
            return;
        }

        const incoming = Array.from(files);

        if (rows.length + incoming.length > BULK_MAX_FILES) {
            setError(
                `در هر بارگذاری گروهی حداکثر ${BULK_MAX_FILES.toLocaleString("fa-IR")} فایل مجاز است`,
            );

            return;
        }

        setRows((previous) => [
            ...previous,
            ...incoming.map((file) => ({
                key: nextKey.current++,
                file,
                registryNumber: deriveRegistryNumber(file.name) ?? "",
                title: "",
            })),
        ]);
        setError(null);
    }

    function removeRow(key: number) {
        setRows((previous) => previous.filter((row) => row.key !== key));
    }

    function updateRow(
        key: number,
        patch: Partial<Pick<BulkRow, "registryNumber" | "title">>,
    ) {
        setRows((previous) =>
            previous.map((row) =>
                row.key === key ? { ...row, ...patch } : row,
            ),
        );
    }

    function handleSubmit() {
        if (rows.some((row) => row.registryNumber.trim() === "")) {
            setError("شمارهٔ مصوبه برای همهٔ فایلها الزامی است");
            return;
        }

        const formData = new FormData();

        rows.forEach((row, index) => {
            formData.append(`entries[${index}][file]`, row.file);
            formData.append(
                `entries[${index}][registry_number]`,
                row.registryNumber.trim(),
            );
            if (row.title.trim() !== "") {
                formData.append(`entries[${index}][title]`, row.title.trim());
            }
        });

        setError(null);
        upload.mutate(formData, {
            onSuccess: (result) => setReport(result.data.data),
            onError: (err) =>
                setError(getApiError(err) ?? "بارگذاری گروهی ناموفق بود"),
        });
    }

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                onOpenChange(next);
                if (!next) {
                    reset();
                }
            }}
        >
            {report === null ? (
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>بارگذاری گروهی اسناد</DialogTitle>
                        <DialogDescription>
                            چندین مصوبه را یکجا برای این جلسه بارگذاری کنید؛
                            شمارهٔ مصوبهٔ هر فایل جداگانه ثبت می‌شود و نتیجهٔ
                            هر فایل در پایان گزارش داده می‌شود.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4 py-2">
                        <div className="grid gap-2">
                            <Label>فایل‌ها</Label>
                            <Input
                                ref={fileInputRef}
                                type="file"
                                multiple
                                accept=".pdf,.jpg,.jpeg,.png,.webp"
                                onChange={(event) => addFiles(event.target.files)}
                            />
                            <p className="text-xs text-muted-foreground">
                                پس از انتخاب، شمارهٔ مصوبهٔ هر فایل را وارد
                                کنید.
                            </p>
                        </div>

                        {rows.length > 0 && (
                            <div className="flex max-h-64 flex-col gap-3 overflow-y-auto">
                                {rows.map((row) => (
                                    <div
                                        key={row.key}
                                        className="grid gap-2 rounded-lg border p-3"
                                    >
                                        <div className="flex items-center justify-between gap-2">
                                            <span
                                                className="truncate text-sm font-medium"
                                                dir="ltr"
                                            >
                                                {row.file.name}
                                            </span>
                                            <div className="flex shrink-0 items-center gap-2">
                                                <span className="text-xs text-muted-foreground">
                                                    {formatBytes(row.file.size)}
                                                </span>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    className="size-7"
                                                    onClick={() =>
                                                        removeRow(row.key)
                                                    }
                                                >
                                                    <IconX className="size-4" />
                                                </Button>
                                            </div>
                                        </div>
                                        <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                            <div className="grid gap-1">
                                                <Label>شمارهٔ مصوبه</Label>
                                                <Input
                                                    dir="ltr"
                                                    placeholder="۱۰۰۱/ص/۱۴۰۴"
                                                    value={row.registryNumber}
                                                    onChange={(event) =>
                                                        updateRow(row.key, {
                                                            registryNumber:
                                                                event.target
                                                                    .value,
                                                        })
                                                    }
                                                />
                                            </div>
                                            <div className="grid gap-1">
                                                <Label>عنوان (اختیاری)</Label>
                                                <Input
                                                    value={row.title}
                                                    onChange={(event) =>
                                                        updateRow(row.key, {
                                                            title: event.target
                                                                .value,
                                                        })
                                                    }
                                                />
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}

                        {error && (
                            <p className="text-sm text-destructive">{error}</p>
                        )}
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            انصراف
                        </Button>
                        <Button
                            type="button"
                            onClick={handleSubmit}
                            disabled={upload.isPending || rows.length === 0}
                        >
                            {upload.isPending ? (
                                <IconLoader2
                                    data-icon="inline-start"
                                    className="size-4 animate-spin"
                                />
                            ) : (
                                <IconFileUpload data-icon="inline-start" />
                            )}
                            بارگذاری گروهی
                        </Button>
                    </DialogFooter>
                </DialogContent>
            ) : (
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>گزارش بارگذاری</DialogTitle>
                        <DialogDescription>
                            {[
                                `${report.imported.length.toLocaleString("fa-IR")} فایل بارگذاری شد`,
                                report.skipped.length > 0
                                    ? `${report.skipped.length.toLocaleString("fa-IR")} تکراری رد شد`
                                    : null,
                                report.failed.length > 0
                                    ? `${report.failed.length.toLocaleString("fa-IR")} ناموفق بود`
                                    : null,
                            ]
                                .filter(Boolean)
                                .join(" · ")}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="flex max-h-64 flex-col gap-2 overflow-y-auto py-2">
                        {report.imported.map((doc, index) => (
                            <div
                                key={doc.usage_id}
                                className="flex items-center justify-between gap-2 rounded-lg border p-2.5"
                            >
                                <div className="flex min-w-0 items-center gap-2">
                                    <IconCircleCheck className="size-4 shrink-0 text-emerald-500" />
                                    <span
                                        className="truncate text-sm font-medium"
                                        dir="ltr"
                                    >
                                        {typeof doc.metadata
                                            ?.registry_number === "string"
                                            ? doc.metadata.registry_number
                                            : `سند ${index + 1}`}
                                    </span>
                                </div>
                                {typeof doc.metadata?.title === "string" && (
                                    <span className="shrink-0 truncate text-xs text-muted-foreground">
                                        {doc.metadata.title}
                                    </span>
                                )}
                            </div>
                        ))}

                        {report.skipped.map((row) => (
                            <div
                                key={`skipped-${row.file}-${
                                    typeof row.metadata.registry_number ===
                                    "string"
                                        ? row.metadata.registry_number
                                        : "no-number"
                                }`}
                                className="flex items-center justify-between gap-2 rounded-lg border p-2.5"
                            >
                                <div className="flex min-w-0 items-center gap-2">
                                    <IconCircleMinus className="size-4 shrink-0 text-amber-500" />
                                    <span
                                        className="truncate text-sm"
                                        dir="ltr"
                                    >
                                        {row.file}
                                    </span>
                                </div>
                                <span className="shrink-0 text-xs text-muted-foreground">
                                    تکراری
                                </span>
                            </div>
                        ))}

                        {report.failed.map((row) => (
                            <div
                                key={`failed-${row.file}-${
                                    typeof row.metadata.registry_number ===
                                    "string"
                                        ? row.metadata.registry_number
                                        : "no-number"
                                }`}
                                className="flex items-center justify-between gap-2 rounded-lg border p-2.5"
                            >
                                <div className="flex min-w-0 items-center gap-2">
                                    <IconCircleX className="size-4 shrink-0 text-red-500" />
                                    <span
                                        className="truncate text-sm"
                                        dir="ltr"
                                    >
                                        {row.file}
                                    </span>
                                </div>
                                <span className="shrink-0 text-xs text-muted-foreground">
                                    {row.reason}
                                </span>
                            </div>
                        ))}

                        {report.imported.length === 0 &&
                            report.skipped.length === 0 &&
                            report.failed.length === 0 && (
                                <p className="text-sm text-muted-foreground">
                                    چیزی بارگذاری نشد.
                                </p>
                            )}
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            onClick={() => onOpenChange(false)}
                        >
                            پایان
                        </Button>
                    </DialogFooter>
                </DialogContent>
            )}
        </Dialog>
    );
}
