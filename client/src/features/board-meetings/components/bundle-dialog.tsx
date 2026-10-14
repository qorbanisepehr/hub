import { useEffect, useState } from "react";
import { IconFileZip } from "@tabler/icons-react";
import { toast } from "sonner";

import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Label } from "@/components/ui/label";
import { ResponsiveDialog } from "@/components/ui/responsive-dialog";
import { Spinner } from "@/components/ui/spinner";
import {
    EXPORT_MIME_TYPES,
    exportDateStamp,
    saveBlobResponse,
} from "@/lib/download";

import { fetchBundleFormats, exportMeetingBundle } from "../api";
import type { BundleFormat } from "../types";

/**
 * What goes into a bundle: which renderings of the meeting, and whether its own
 * files travel with them.
 *
 * The formats are fetched rather than listed, because a list written here would
 * go stale silently — the dialog would stop offering a format the server is
 * perfectly happy to produce, and the only symptom would be a user asking why
 * there is no such button.
 *
 * Defaults are the common case rather than everything: a PDF to print and the
 * files that came with the meeting. Word and Markdown are a deliberate choice,
 * because each costs an extra copy of the same minutes and a reader who did not
 * ask for one would find four files instead of two.
 *
 * The documents checkbox is the other half of a two-part permission, which is
 * why it is a choice at all: leaving it off is how a user with
 * `board-meeting.export` but not `board-meeting.documents.download` gets a
 * bundle at all. Ticking it asks for bytes behind another permission, and the
 * server refuses the whole request rather than answering with a thinner archive.
 */
export function BundleDialog({
    open,
    onOpenChange,
    /** Restricts the bundle to these meetings; omitted means the current filter. */
    ids,
    /** How many meetings the bundle would cover, for the description line. */
    meetingCount,
    canDownloadDocuments,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    ids?: number[];
    meetingCount?: number;
    canDownloadDocuments: boolean;
}) {
    const [formats, setFormats] = useState<BundleFormat[]>([]);
    const [chosen, setChosen] = useState<string[]>(["pdf"]);
    const [withDocuments, setWithDocuments] = useState(true);
    const [isExporting, setIsExporting] = useState(false);

    useEffect(() => {
        if (!open) return;

        void fetchBundleFormats()
            .then((response) => {
                setFormats(response.data.data);

                // Only keep a default the server actually offers: defaulting to
                // "pdf" on a registry with no PDF would submit a format the
                // server refuses, and the dialog would look broken.
                setChosen((previous) =>
                    previous.filter((key) =>
                        response.data.data.some((one) => one.key === key),
                    ),
                );
            })
            .catch(() => toast.error("دریافت قالب‌ها ناموفق بود."));
    }, [open]);

    const toggle = (key: string, checked: boolean) =>
        setChosen((previous) =>
            checked
                ? [...previous, key]
                : previous.filter((one) => one !== key),
        );

    const canSubmit = chosen.length > 0 && !isExporting;

    const submit = async () => {
        setIsExporting(true);

        try {
            const response = await exportMeetingBundle({
                formats: chosen,
                documents: withDocuments,
                ...(ids ? { ids } : {}),
            });

            // The shared helper, so the server's own Content-Disposition name
            // wins and every export button in the app behaves the same way.
            saveBlobResponse(
                response,
                `board-meetings-${exportDateStamp()}.zip`,
                EXPORT_MIME_TYPES.zip,
            );

            onOpenChange(false);
        } catch {
            toast.error("ساخت بسته انجام نشد.", {
                description: "لطفاً انتخاب را محدودتر کنید یا دوباره تلاش کنید.",
            });
        } finally {
            setIsExporting(false);
        }
    };

    return (
        <ResponsiveDialog
            open={open}
            onOpenChange={onOpenChange}
            title="دانلود بستهٔ جلسات"
            description={
                meetingCount === undefined
                    ? "همهٔ جلساتی که در نمای فعلی می‌بینید در یک بسته قرار می‌گیرند."
                    : `${meetingCount.toLocaleString("fa-IR")} جلسه در یک بسته قرار می‌گیرد.`
            }
            footer={
                <>
                    <Button
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        انصراف
                    </Button>
                    <Button disabled={!canSubmit} onClick={submit}>
                        {isExporting ? (
                            <Spinner className="size-4" />
                        ) : (
                            <IconFileZip />
                        )}
                        ساخت بسته
                    </Button>
                </>
            }
        >
            <div className="flex flex-col gap-4">
                <fieldset className="flex flex-col gap-3">
                    <legend className="text-sm font-medium">
                        قالب‌های فایل
                    </legend>

                    {formats.map((format) => (
                        <div
                            key={format.key}
                            className="flex items-center gap-2"
                        >
                            <Checkbox
                                id={`bundle-format-${format.key}`}
                                checked={chosen.includes(format.key)}
                                onCheckedChange={(checked) =>
                                    toggle(format.key, checked === true)
                                }
                            />
                            <Label
                                htmlFor={`bundle-format-${format.key}`}
                                className="font-normal"
                            >
                                {format.label}
                            </Label>
                        </div>
                    ))}
                </fieldset>

                <div className="flex items-center gap-2">
                    <Checkbox
                        id="bundle-documents"
                        checked={withDocuments}
                        disabled={!canDownloadDocuments}
                        onCheckedChange={(checked) =>
                            setWithDocuments(checked === true)
                        }
                    />
                    <Label
                        htmlFor="bundle-documents"
                        className="font-normal"
                    >
                        افزودن اسناد و پیوست‌ها
                    </Label>
                </div>

                {!canDownloadDocuments && (
                    <p className="text-xs text-muted-foreground">
                        برای افزودن اسناد، اجازهٔ دانلود اسناد لازم است.
                    </p>
                )}
            </div>
        </ResponsiveDialog>
    );
}