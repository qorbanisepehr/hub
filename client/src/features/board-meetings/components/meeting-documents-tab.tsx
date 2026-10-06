import { useRef, useState } from "react";
import { IconFileUpload, IconPaperclip } from "@tabler/icons-react";

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
import { useEntityDocuments } from "@/hooks/use-entity-documents";
import { useUploadMeetingDocument } from "../hooks";
import { getApiError } from "@/lib/error-utils";
import { formatBytes } from "@/lib/file-utils";
import type { BoardMeetingApprovalDto } from "../types";

/**
 * The meeting's documents, which is also where its registry numbers live.
 *
 * One tab rather than two, because they are one thing: every approval either
 * has a document in the hub or is waiting for one, and a page that showed the
 * registry numbers in one place and their scans in another made a reader hold
 * two lists in their head to answer "is this approval on file?". A document
 * that belongs to an approval carries its registry number, and a number with
 * nothing attached says so rather than disappearing.
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
export function MeetingDocumentsTab({
    meetingId,
    approvals,
}: {
    meetingId: string;
    approvals: BoardMeetingApprovalDto[];
}) {
    const { documents, isLoading, capabilities } = useEntityDocuments(
        "board-meetings",
        meetingId,
    );
    const upload = useUploadMeetingDocument(meetingId);

    const [uploadOpen, setUploadOpen] = useState(false);

    // The registry number a document is the scan of. The approval carries the
    // document id, not the other way round, so the join is built here rather
    // than sent twice in the payload.
    const numberByDocumentId = new Map(
        approvals
            .filter((approval) => approval.document_id !== null)
            .map((approval) => [approval.document_id as number, approval]),
    );

    const attachedDocumentIds = new Set(numberByDocumentId.keys());
    const missingDocument = approvals.filter(
        (approval) => !attachedDocumentIds.has(approval.document_id ?? -1),
    );

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
                {capabilities.upload && (
                    <Button
                        type="button"
                        size="sm"
                        onClick={() => setUploadOpen(true)}
                    >
                        <IconFileUpload data-icon="inline-start" />
                        بارگذاری سند
                    </Button>
                )}
            </CardHeader>

            <CardContent className="flex flex-col gap-4">
                {isLoading ? (
                    <div className="flex flex-col gap-3">
                        <Skeleton className="h-10 w-full" />
                        <Skeleton className="h-10 w-5/6" />
                    </div>
                ) : documents.length === 0 && missingDocument.length === 0 ? (
                    <EmptyState
                        icon={IconPaperclip}
                        message="سندی برای این جلسه بارگذاری نشده است"
                    />
                ) : (
                    <>
                        {documents.length > 0 && (
                            <ul className="divide-y">
                                {documents.map((doc) => {
                                    const approval = numberByDocumentId.get(
                                        doc.document_id ?? doc.id,
                                    );

                                    return (
                                        <li
                                            key={doc.usage_id}
                                            className="py-2.5"
                                        >
                                            <DocumentFileItem
                                                uuid={meetingId}
                                                entity="board-meetings"
                                                doc={doc}
                                                subtitle={[
                                                    approval?.number,
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
                                                    capabilities.delete
                                                }
                                            />
                                        </li>
                                    );
                                })}
                            </ul>
                        )}

                        {missingDocument.length > 0 && (
                            <div className="flex flex-col gap-2">
                                <p className="text-muted-foreground text-sm">
                                    مصوباتی که هنوز سندی برایشان بارگذاری نشده
                                    است:
                                </p>
                                <ul className="flex flex-wrap gap-1.5">
                                    {missingDocument.map((approval) => (
                                        <li key={approval.number}>
                                            <Badge variant="outline">
                                                <span dir="ltr">
                                                    {approval.number}
                                                </span>
                                                {approval.title && (
                                                    <span className="text-muted-foreground">
                                                        {" "}
                                                        — {approval.title}
                                                    </span>
                                                )}
                                            </Badge>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}
                    </>
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
                        <IconFileUpload data-icon="inline-start" />
                        بارگذاری
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
