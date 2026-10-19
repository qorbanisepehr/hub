import { useState } from "react";
import { IconFileTypeDoc, IconStar } from "@tabler/icons-react";
import { toast } from "sonner";

import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Spinner } from "@/components/ui/spinner";
import { ResponsiveDialog } from "@/components/ui/responsive-dialog";
import { EXPORT_MIME_TYPES, exportDateStamp, saveBlobResponse } from "@/lib/download";
import { exportBoardMeetingDocx } from "../word-templates-api";
import { useWordTemplates } from "../hooks/use-word-templates";
import type { WordTemplateDto } from "../word-templates-api";

/**
 * Which template this meeting is exported with.
 *
 * Opens only when the domain has more than one template. With none, or exactly
 * one, the export button is a single click — the alternative is a dialog whose
 * only content is one row and a confirm button, which is a cost paid on the
 * common path to make a rare choice possible.
 *
 * The format is on each row, not the kind. The kind is READ OFF THE FILE at upload
 * and told to the person then, because it is not a choice — a file with
 * `{{content}}` in it wants the meeting and one without it does not — so asking
 * again here would be asking a question whose answer the uploader already has.
 * What genuinely varies between two files both called "official" is what they
 * are: a letterhead and a Markdown payload are not interchangeable, and the
 * picker cannot pretend otherwise.
 */
export function WordTemplatePickerDialog({
    open,
    onOpenChange,
    meetingId,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    meetingId: number | string;
}) {
    const { data: templates } = useWordTemplates();
    const [pending, setPending] = useState<string | null>(null);

    const choose = async (template: WordTemplateDto) => {
        setPending(template.slug);

        try {
            const response = await exportBoardMeetingDocx(
                meetingId,
                template.slug,
            );

            saveBlobResponse(
                response,
                `board-meeting-${exportDateStamp()}.docx`,
                EXPORT_MIME_TYPES.docx,
            );

            onOpenChange(false);
        } catch {
            toast.error("ساخت فایل انجام نشد.", {
                description: "لطفاً دوباره تلاش کنید.",
            });
        } finally {
            setPending(null);
        }
    };

    return (
        <ResponsiveDialog
            open={open}
            onOpenChange={onOpenChange}
            title="قالب Word"
            description="صورتجلسه در انتهای این فایل افزوده می‌شود و جای‌نگهدارهای آن پر می‌شوند."
            footer={
                <Button
                    type="button"
                    variant="outline"
                    onClick={() => onOpenChange(false)}
                >
                    انصراف
                </Button>
            }
        >
            <ul className="grid gap-2">
                {(templates ?? []).map((template) => (
                    <li key={template.slug}>
                        <button
                            type="button"
                            disabled={pending !== null}
                            onClick={() => void choose(template)}
                            className="flex w-full items-center justify-between gap-3 rounded-lg border px-3 py-2 text-start transition-colors hover:bg-accent disabled:opacity-60"
                        >
                            <div className="flex min-w-0 flex-col">
                                <div className="flex flex-wrap items-center gap-1.5">
                                    <IconFileTypeDoc className="size-4 shrink-0" />
                                    <span className="truncate text-sm font-medium">
                                        {template.name}
                                    </span>

                                    {/* What the file IS, not decoration on it: two
                                        templates both named "official" can be a
                                        letterhead and a Markdown payload, and this
                                        is the only thing on the row that says so. */}
                                    <Badge variant="outline" dir="ltr">
                                        {template.format}
                                    </Badge>

                                    {template.is_default && (
                                        <Badge variant="info">
                                            <IconStar className="size-3" />
                                            پیش‌فرض
                                        </Badge>
                                    )}
                                </div>
                            </div>

                            {pending === template.slug && (
                                <Spinner className="size-4 shrink-0" />
                            )}
                        </button>
                    </li>
                ))}

                {(templates ?? []).length === 0 && (
                    <li className="py-6 text-center text-sm text-muted-foreground">
                        هنوز قالبی بارگذاری نشده است.
                    </li>
                )}
            </ul>
        </ResponsiveDialog>
    );
}
