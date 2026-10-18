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
 * Which Word template this meeting is exported with.
 *
 * Opens only when the domain has more than one template. With none, or exactly
 * one, the export button is a single click — the alternative is a dialog whose
 * only content is one row and a confirm button, which is a cost paid on the
 * common path to make a rare choice possible.
 *
 * EACH TEMPLATE SAYS WHICH KIND IT IS. A `content` template decides what the
 * document says and a `style` template only supplies the layout, so a person
 * picking between two files whose names both say "official" otherwise has no way
 * to tell which of them will actually use the meeting's data.
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
