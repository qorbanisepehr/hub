import { useMemo, useState } from "react";
import {
    IconFileTypeDoc,
    IconFileTypeTxt,
    IconFileTypePdf,
    IconBraces,
    IconStar,
} from "@tabler/icons-react";
import { toast } from "sonner";

import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Label } from "@/components/ui/label";
import { Spinner } from "@/components/ui/spinner";
import { ResponsiveDialog } from "@/components/ui/responsive-dialog";
import {
    EXPORT_MIME_TYPES,
    exportDateStamp,
    saveBlobResponse,
} from "@/lib/download";
import {
    exportBoardMeetingSheet,
    type BoardMeetingSheetFormat,
} from "../api";
import {
    useTemplateFields,
    useTemplates,
} from "../hooks/use-templates";

/**
 * What this one export should be: which format, which template, which sections.
 *
 * REPLACES TWO DIALOGS. One asked for a Word template and nothing else; the other
 * asked for a section list and nothing else. So somebody who wanted a Word export
 * without the agenda had to open one, choose, close, and open the other — two
 * dialogs for one question, and neither of them could answer it completely.
 *
 * THE CHOICES TRAVEL TOGETHER because they are halves of one decision. A template
 * that lays the sheet out and a list of sections saying what goes in it are the same
 * question asked twice, and letting a dialog set one without the other produces the
 * state nobody meant: a letterhead with a section list that does not match it.
 *
 * IT STARTS FROM THE DEFAULTS AND CHANGES EVERY TIME. Nothing here is saved, which
 * is the difference between "print this one differently" and "from now on" — and
 * the server takes the whole list per request precisely so the first is possible.
 *
 * IT REUSES THE TWO EXISTING QUERIES rather than adding a third endpoint for its
 * own seed: the template library and the field catalogue already say what this
 * domain has, and an endpoint that restated them would be a second place for that
 * answer to live and go stale.
 */
export function MeetingExportDialog({
    open,
    onOpenChange,
    meetingId,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    meetingId: number | string;
}) {
    const { data: templates } = useTemplates();
    const { data: fields } = useTemplateFields();

    const sections = useMemo(() => fields?.sections ?? [], [fields]);
    const [format, setFormat] = useState<BoardMeetingSheetFormat>("docx");
    const [template, setTemplate] = useState<string | null>(null);
    const [picked, setPicked] = useState<string[]>([]);
    const [isExporting, setIsExporting] = useState(false);

    // The domain's own order is the default, so the dialog opens on what the export
    // button alone would have produced and the person changes it only if they mean to.
    const chosen = picked.length > 0 ? picked : sections.map((s) => s.key);

    // Only the templates this format can actually use. A Markdown template offered
    // beside a Word button is a template that silently does not apply.
    const usable = (templates ?? []).filter((t) => t.format === format);

    // WHENEVER THE FORMAT CHANGES, THE TEMPLATE DOES TOO. Keeping the previous
    // format's slug would send a template the server cannot use for this route, and
    // the refusal would arrive as a download that did not happen.
    const chooseFormat = (next: BoardMeetingSheetFormat) => {
        setFormat(next);

        const stillUsable = (templates ?? []).find(
            (t) => t.slug === template && t.format === next,
        );

        setTemplate(stillUsable ? stillUsable.slug : null);
    };

    const submit = async () => {
        setIsExporting(true);

        try {
            const response = await exportBoardMeetingSheet(meetingId, {
                format,
                template: template ?? undefined,
                sections: chosen,
            });

            saveBlobResponse(
                response,
                `board-meeting-${exportDateStamp()}.${format}`,
                EXPORT_MIME_TYPES[format],
            );

            onOpenChange(false);
        } catch {
            toast.error("ساخت فایل انجام نشد.", {
                description: "لطفاً دوباره تلاش کنید.",
            });
        } finally {
            setIsExporting(false);
        }
    };

    return (
        <ResponsiveDialog
            open={open}
            onOpenChange={onOpenChange}
            title="خروجی گرفتن"
            description="این انتخاب‌ها فقط برای همین یک خروجی است و جایی ذخیره نمی‌شود."
            footer={
                <>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                        disabled={isExporting}
                    >
                        انصراف
                    </Button>
                    <Button
                        type="button"
                        onClick={() => void submit()}
                        disabled={isExporting}
                    >
                        {isExporting ? (
                            <Spinner className="size-4" />
                        ) : (
                            <IconFileTypePdf className="size-4" />
                        )}
                        دریافت فایل
                    </Button>
                </>
            }
        >
            <div className="grid gap-5">
                <fieldset className="grid gap-2">
                    <legend className="text-sm font-medium">قالب فایل</legend>

                    <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                        {(
                            [
                                ["docx", "Word", IconFileTypeDoc],
                                ["pdf", "PDF", IconFileTypePdf],
                                ["md", "Markdown", IconFileTypeTxt],
                                ["json", "JSON", IconBraces],
                            ] as const
                        ).map(([key, label, Icon]) => (
                            <button
                                key={key}
                                type="button"
                                onClick={() => chooseFormat(key)}
                                aria-pressed={format === key}
                                className={`flex flex-col items-center gap-1 rounded-lg border px-2 py-3 text-xs transition-colors ${
                                    format === key
                                        ? "border-primary bg-primary/10 font-medium"
                                        : "border-border hover:bg-muted"
                                }`}
                            >
                                <Icon className="size-5" />
                                {label}
                            </button>
                        ))}
                    </div>
                </fieldset>

                {/* THE TEMPLATE IS OPTIONAL AND SAYS SO. With none configured this is
                    one line of text rather than an empty list, because "there is
                    nothing to choose" and "here is nothing to choose from" are
                    different messages and only one of them is true. */}
                <fieldset className="grid gap-2">
                    <legend className="text-sm font-medium">قالب سند</legend>

                    {usable.length === 0 ? (
                        <p className="text-xs text-muted-foreground">
                            برای این فرمت قالبی ثبت نشده؛ سند تولیدی استفاده
                            می‌شود.
                        </p>
                    ) : (
                        <ul className="grid gap-1.5">
                            <li>
                                <label
                                    className={`flex cursor-pointer items-center justify-between gap-2 rounded-lg border px-3 py-2 text-xs ${
                                        template === null
                                            ? "border-primary bg-primary/10"
                                            : "border-border"
                                    }`}
                                >
                                    <input
                                        type="radio"
                                        name="export-template"
                                        className="sr-only"
                                        checked={template === null}
                                        onChange={() => setTemplate(null)}
                                    />

                                    {/* SAYS WHICH OF THE TWO THIS IS. "پیش‌فرض" on
                                        this row would claim the server falls back to
                                        no template, which is the opposite of what
                                        a configured default means. */}
                                    <span>
                                        بدون قالب — سند تولیدی
                                    </span>

                                    {usable.length > 0 && (
                                        <span className="text-[10px] text-muted-foreground">
                                            بدون انتخاب، قالب پیش‌فرضِ
                                            همین فرمت به کار می‌رود
                                        </span>
                                    )}
                                </label>
                            </li>

                            {usable.map((option) => (
                                <li key={option.slug}>
                                    <label
                                        className={`flex cursor-pointer items-center justify-between gap-2 rounded-lg border px-3 py-2 text-xs ${
                                            template === option.slug
                                                ? "border-primary bg-primary/10"
                                                : "border-border"
                                        }`}
                                    >
                                        <input
                                            type="radio"
                                            name="export-template"
                                            className="sr-only"
                                            checked={template === option.slug}
                                            onChange={() =>
                                                setTemplate(option.slug)
                                            }
                                        />

                                        <span className="truncate">
                                            {option.name}
                                        </span>

                                        {option.is_default && (
                                            <Badge variant="info">
                                                <IconStar className="size-3" />
                                                پیش‌فرض
                                            </Badge>
                                        )}
                                    </label>
                                </li>
                            ))}
                        </ul>
                    )}
                </fieldset>

                <fieldset className="grid gap-2">
                    <legend className="text-sm font-medium">بخش‌ها</legend>

                    <p className="text-xs text-muted-foreground">
                        همهٔ بخش‌ها به ترتیب همیشگی چاپ می‌شوند مگر آنکه چیزی را
                        بردارید.
                    </p>

                    <ul className="grid gap-1.5">
                        {sections.map((section) => (
                            <li
                                key={section.key}
                                className="flex items-center gap-2 rounded-md px-1 py-0.5"
                            >
                                <Checkbox
                                    id={`section-${section.key}`}
                                    checked={picked.includes(section.key)}
                                    onCheckedChange={(checked) =>
                                        setPicked((current) =>
                                            checked === true
                                                ? [...current, section.key]
                                                : current.filter(
                                                      (key) =>
                                                          key !== section.key,
                                                  ),
                                        )
                                    }
                                />

                                <Label
                                    htmlFor={`section-${section.key}`}
                                    className="flex-1 cursor-pointer text-sm"
                                >
                                    {section.label}
                                </Label>
                            </li>
                        ))}
                    </ul>

                    {picked.length > 0 && (
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            className="justify-self-start"
                            onClick={() => setPicked([])}
                        >
                            برگرداندن به همه
                        </Button>
                    )}
                </fieldset>
            </div>
        </ResponsiveDialog>
    );
}