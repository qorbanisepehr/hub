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
import { getApiError } from "@/lib/error-utils";
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

const FORMATS = [
    { key: "docx", label: "Word", icon: IconFileTypeDoc },
    { key: "pdf", label: "PDF", icon: IconFileTypePdf },
    { key: "md", label: "Markdown", icon: IconFileTypeTxt },
    { key: "json", label: "JSON", icon: IconBraces },
] as const;

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
 * THE LIBRARY IS ASKED FOR BY FORMAT. The server scopes templates by format, so a
 * client that fetched once and filtered locally would see only Word templates — which
 * is how the Markdown and JSON libraries stayed invisible. The format is part of the
 * question, and changing it asks again.
 *
 * IT STARTS FROM THE DEFAULTS AND CHANGES EVERY TIME. Nothing here is saved, which
 * is the difference between "print this one differently" and "from now on" — and
 * the server takes the whole list per request precisely so the first is possible.
 * "No template" is a real third choice, not the absence of an answer: the server
 * distinguishes a missing name (use the default) from the opt-out flag.
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
    const [format, setFormat] = useState<BoardMeetingSheetFormat>("docx");
    // undefined follows the format's default; null is the explicit "no template";
    // a string is the template the person picked. Keeping the three apart is what
    // lets "just this once, without the letterhead" be expressible.
    const [template, setTemplate] = useState<string | null | undefined>(
        undefined,
    );
    const [picked, setPicked] = useState<string[] | null>(null);
    const [isExporting, setIsExporting] = useState(false);

    const { data: fields, isLoading: fieldsLoading } = useTemplateFields();
    const { data: templates, isLoading: templatesLoading } = useTemplates(
        format,
        "boardmeeting",
        format !== "pdf",
    );

    const sections = useMemo(() => fields?.sections ?? [], [fields]);
    const allKeys = useMemo(() => sections.map((s) => s.key), [sections]);

    // null is "all sections", the domain's own order; an array is an explicit
    // choice, including an empty one. Without the distinction, unchecking the last
    // box silently meant "print everything" while the screen showed nothing checked.
    const chosen = picked ?? allKeys;

    const defaultTemplate = templates?.find((t) => t.is_default) ?? null;
    const selected =
        template === undefined ? (defaultTemplate?.slug ?? null) : template;

    const ActiveIcon =
        FORMATS.find((option) => option.key === format)?.icon ?? IconFileTypePdf;

    // The template list and the section list are the dialog's two inputs; exporting
    // before either arrives would send "no template" and "no sections" by accident.
    const isPreparing =
        fieldsLoading || (format !== "pdf" && templatesLoading);

    // Whenever the format changes, follow the new format's default rather than
    // carrying a slug the server cannot use for this route.
    const chooseFormat = (next: BoardMeetingSheetFormat) => {
        setFormat(next);
        setTemplate(undefined);
    };

    // Section choices are for one export, so reopening starts from the domain's
    // list again. (An empty `picked` now means "print nothing", so leaving it
    // behind would reopen the dialog on a warning nobody asked for.)
    const close = (next: boolean) => {
        if (!next) {
            setPicked(null);
        }

        onOpenChange(next);
    };

    const submit = async () => {
        setIsExporting(true);

        try {
            const response = await exportBoardMeetingSheet(meetingId, {
                format,
                ...(selected === null
                    ? { withoutTemplate: true }
                    : { template: selected }),
                sections: chosen,
            });

            saveBlobResponse(
                response,
                `board-meeting-${exportDateStamp()}.${format}`,
                EXPORT_MIME_TYPES[format],
            );

            close(false);
        } catch (error) {
            // The server's own sentence, verbatim: a JSON template that does not
            // parse comes back as a 422 that names the thing to fix, and replacing
            // it with "export failed" would throw that away.
            toast.error(getApiError(error));
        } finally {
            setIsExporting(false);
        }
    };

    return (
        <ResponsiveDialog
            open={open}
            onOpenChange={close}
            title="خروجی گرفتن"
            description="این انتخاب‌ها فقط برای همین یک خروجی است و جایی ذخیره نمی‌شود."
            footer={
                <>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => close(false)}
                        disabled={isExporting}
                    >
                        انصراف
                    </Button>
                    <Button
                        type="button"
                        onClick={() => void submit()}
                        disabled={isExporting || isPreparing}
                    >
                        {isExporting ? (
                            <Spinner className="size-4" />
                        ) : (
                            <ActiveIcon className="size-4" />
                        )}
                        دریافت فایل
                    </Button>
                </>
            }
        >
            <div className="grid gap-5">
                <fieldset className="grid gap-2">
                    <legend className="text-sm font-medium">
                        فرمت خروجی
                    </legend>

                    <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                        {FORMATS.map(({ key, label, icon: Icon }) => (
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

                <fieldset className="grid gap-2">
                    <legend className="text-sm font-medium">قالب سند</legend>

                    {format === "pdf" ? (
                        <p className="text-xs text-muted-foreground">
                            PDF همیشه از سند تولیدی ساخته می‌شود و قالب فایل
                            نمی‌گیرد.
                        </p>
                    ) : templatesLoading ? (
                        <div className="flex justify-center py-3">
                            <Spinner className="size-4" />
                        </div>
                    ) : templates && templates.length > 0 ? (
                        <>
                            <p className="text-xs text-muted-foreground">
                                {defaultTemplate
                                    ? `قالب پیش‌فرضِ این فرمت «${defaultTemplate.name}» است؛ برای همین خروجی می‌توانید عوضش کنید.`
                                    : "برای این فرمت پیش‌فرضی تعیین نشده؛ سند تولیدی انتخاب شده است."}
                            </p>

                            <ul className="grid gap-1.5">
                                <li>
                                    <label
                                        className={`flex cursor-pointer items-center justify-between gap-2 rounded-lg border px-3 py-2 text-xs ${
                                            selected === null
                                                ? "border-primary bg-primary/10"
                                                : "border-border"
                                        }`}
                                    >
                                        <input
                                            type="radio"
                                            name="export-template"
                                            className="sr-only"
                                            checked={selected === null}
                                            onChange={() => setTemplate(null)}
                                        />

                                        <span>
                                            بدون قالب — سند تولیدی
                                        </span>
                                    </label>
                                </li>

                                {templates.map((option) => (
                                    <li key={option.slug}>
                                        <label
                                            className={`flex cursor-pointer items-center justify-between gap-2 rounded-lg border px-3 py-2 text-xs ${
                                                selected === option.slug
                                                    ? "border-primary bg-primary/10"
                                                    : "border-border"
                                            }`}
                                        >
                                            <input
                                                type="radio"
                                                name="export-template"
                                                className="sr-only"
                                                checked={
                                                    selected === option.slug
                                                }
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
                        </>
                    ) : (
                        <p className="text-xs text-muted-foreground">
                            برای این فرمت قالبی ثبت نشده؛ سند تولیدی استفاده
                            می‌شود.
                        </p>
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
                                    checked={
                                        picked === null ||
                                        picked.includes(section.key)
                                    }
                                    onCheckedChange={(checked) =>
                                        setPicked((current) => {
                                            const base = current ?? allKeys;

                                            const next =
                                                checked === true
                                                    ? [...base, section.key]
                                                    : base.filter(
                                                          (key) =>
                                                              key !==
                                                              section.key,
                                                      );

                                            // The server prints in the order it is
                                            // given, so re-checking a box must not
                                            // move that section to the end.
                                            return allKeys.filter((key) =>
                                                next.includes(key),
                                            );
                                        })
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

                    {chosen.length === 0 && (
                        <p className="text-xs text-amber-700">
                            هیچ بخشی انتخاب نشده — فایل فقط عنوان و سربرگ را
                            خواهد داشت.
                        </p>
                    )}

                    {picked !== null && (
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            className="justify-self-start"
                            onClick={() => setPicked(null)}
                        >
                            برگرداندن به همه
                        </Button>
                    )}
                </fieldset>
            </div>
        </ResponsiveDialog>
    );
}
