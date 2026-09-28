import { useRef, useState } from "react";
import {
    IconDownload,
    IconFileUpload,
    IconPaperclip,
} from "@tabler/icons-react";

import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import { ConfirmDeleteButton } from "@/components/ui/confirm-delete-button";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Skeleton } from "@/components/ui/skeleton";
import { Textarea } from "@/components/ui/textarea";
import { EmptyState } from "@/components/layout";
import {
    useDeleteMeetingDocument,
    useMeetingDocuments,
    useUploadMeetingDocument,
} from "../hooks";
import type { MeetingDocumentDto } from "../types";
import { getApiError } from "@/lib/error-utils";
import { formatBytes } from "@/lib/file-utils";

/**
 * The meeting's documents (K4): list + upload + delete, driven by the
 * backend-advertised capabilities — the UI only offers what the API allows.
 */
export function MeetingDocumentsCard({ meetingId }: { meetingId: string }) {
    const { data, isLoading } = useMeetingDocuments(meetingId);
    const upload = useUploadMeetingDocument(meetingId);
    const remove = useDeleteMeetingDocument(meetingId);

    const [uploadOpen, setUploadOpen] = useState(false);

    const documents = data?.data ?? [];
    const capabilities = data?.capabilities;

    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0">
                <CardTitle className="flex items-center gap-2 text-lg">
                    <IconPaperclip className="size-5" />
                    مدارک جلسه
                    {documents.length > 0 && (
                        <Badge variant="outline">
                            {documents.length.toLocaleString("fa-IR")}
                        </Badge>
                    )}
                </CardTitle>
                {capabilities?.upload && (
                    <Button
                        type="button"
                        size="sm"
                        onClick={() => setUploadOpen(true)}
                    >
                        <IconFileUpload className="size-4" />
                        بارگذاری مدرک
                    </Button>
                )}
            </CardHeader>
            <CardContent>
                {isLoading ? (
                    <div className="space-y-2">
                        <Skeleton className="h-12 w-full" />
                        <Skeleton className="h-12 w-full" />
                    </div>
                ) : documents.length > 0 ? (
                    <ul className="divide-y">
                        {documents.map((doc) => (
                            <MeetingDocumentRow
                                key={doc.usage_id}
                                doc={doc}
                                canDownload={capabilities?.download ?? false}
                                canDelete={capabilities?.delete ?? false}
                                downloadPending={false}
                                deletePending={remove.isPending}
                                onDownload={() =>
                                    window.open(doc.download_url, "_blank")
                                }
                                onDelete={() =>
                                    remove.mutate(doc.usage_id)
                                }
                            />
                        ))}
                    </ul>
                ) : (
                    <EmptyState
                        icon={IconPaperclip}
                        message="مدرکی برای این جلسه بارگذاری نشده است"
                    />
                )}
            </CardContent>

            <MeetingUploadDialog
                meetingId={meetingId}
                open={uploadOpen}
                onOpenChange={setUploadOpen}
                isPending={upload.isPending}
            />
        </Card>
    );
}

function MeetingDocumentRow({
    doc,
    canDownload,
    canDelete,
    downloadPending,
    deletePending,
    onDownload,
    onDelete,
}: {
    doc: MeetingDocumentDto;
    canDownload: boolean;
    canDelete: boolean;
    downloadPending: boolean;
    deletePending: boolean;
    onDownload: () => void;
    onDelete: () => void;
}) {
    return (
        <li className="flex items-center justify-between gap-3 py-2.5">
            <div className="min-w-0">
                <p className="truncate text-sm font-medium">
                    {doc.structure_name}
                </p>
                <p className="text-xs text-muted-foreground">
                    {doc.category?.name}
                    {doc.notes ? ` — ${doc.notes}` : ""}
                    {doc.size > 0 ? ` — ${formatBytes(doc.size)}` : ""}
                </p>
            </div>
            <div className="flex shrink-0 items-center gap-1">
                {canDownload && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon-sm"
                        disabled={downloadPending}
                        onClick={onDownload}
                    >
                        <IconDownload className="size-4" />
                    </Button>
                )}
                {canDelete && (
                    <ConfirmDeleteButton
                        iconOnly
                        onConfirm={onDelete}
                        isPending={deletePending}
                    />
                )}
            </div>
        </li>
    );
}

/** Upload dialog: file + optional notes (category is fixed server-side). */
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
    const [notes, setNotes] = useState("");
    const [error, setError] = useState<string | null>(null);

    const upload = useUploadMeetingDocument(meetingId);

    function reset() {
        setFile(null);
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

        const formData = new FormData();
        formData.append("file", file);
        if (notes.trim() !== "") {
            formData.append("notes", notes.trim());
        }

        setError(null);
        upload.mutate(formData, {
            onSuccess: () => {
                reset();
                onOpenChange(false);
            },
            onError: (err) => setError(getApiError(err) ?? "بارگذاری ناموفق بود"),
        });
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>بارگذاری مدرک جلسه</DialogTitle>
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
                        disabled={isPending || !file}
                    >
                        <IconFileUpload className="size-4" />
                        بارگذاری
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
