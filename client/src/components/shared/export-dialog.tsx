import { useState } from "react";
import { toast } from "sonner";
import {
    IconCheckbox,
    IconDownload,
    IconFileText,
    IconLoader2,
    IconSquare,
} from "@tabler/icons-react";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Label } from "@/components/ui/label";
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group";
import { ResponsiveDialog } from "@/components/ui/responsive-dialog";
import { getApiError } from "@/lib/error-utils";

export type ExportFormat = "xlsx" | "csv";

export type ExportPresentation = {
    /** Header language: machine keys or Persian labels. */
    headers: "key" | "label";
    /** Date cell calendar: Gregorian, Jalali, or both. */
    calendar: "gregorian" | "persian" | "both";
    /** Digit glyphs for all formatted numbers (dates and numbers alike). */
    digits: "latin" | "persian";
    /** Repeater rows go to their own sheets (xlsx only). */
    detailSheets: boolean;
};

export type ExportFieldOption = {
    key: string;
    label: string;
};

type ExportDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description?: string;
    /** Field catalog from the backend fields endpoint. */
    fields: ExportFieldOption[];
    fieldsLoading?: boolean;
    /** Formats offered, in display order. Defaults to xlsx + csv. */
    formats?: { value: ExportFormat; label: string }[];
    /** Human-readable summary of the filters the export will honor. */
    activeFilters?: string[];
    /**
     * Show the calendar/digits presentation controls (columns with dates
     * or numbers only — a pure-text export needs no presentation).
     */
    showPresentation?: boolean;
    /**
     * Show the detail-sheets toggle: repeater rows (dependents, education
     * records, …) on their own sheet, addressed by personnel code / national
     * ID, with a count column + hyperlink on the base sheet. xlsx only —
     * other formats silently ignore it.
     */
    showDetailSheets?: boolean;
    /** Runs the export request; throw to surface the error toast. */
    onExport: (options: {
        fields: string[];
        format: ExportFormat;
        presentation: ExportPresentation;
    }) => Promise<void>;
    /** Optional fill-and-import template download. */
    onTemplate?: (format: ExportFormat) => Promise<void>;
    successMessage?: string;
};

const DEFAULT_FORMATS: { value: ExportFormat; label: string }[] = [
    { value: "xlsx", label: "اکسل (xlsx)" },
    { value: "csv", label: "CSV (سازگار با Excel)" },
];

const NO_FILTERS: string[] = [];

/**
 * Shared export dialog: format choice, field picker (empty = all columns),
 * the active-filter summary, and an optional template download. The caller
 * owns the request itself — the dialog owns the choices and the feedback.
 */
export function ExportDialog({
    open,
    onOpenChange,
    title,
    description,
    fields,
    fieldsLoading = false,
    formats = DEFAULT_FORMATS,
    activeFilters = NO_FILTERS,
    showPresentation = false,
    showDetailSheets = false,
    onExport,
    onTemplate,
    successMessage = "خروجی با موفقیت ایجاد شد.",
}: ExportDialogProps) {
    const [format, setFormat] = useState<ExportFormat>(
        formats[0]?.value ?? "xlsx",
    );
    const [presentation, setPresentation] = useState<ExportPresentation>({
        headers: "label",
        calendar: "persian",
        digits: "latin",
        detailSheets: false,
    });
    const [selectedFields, setSelectedFields] = useState<string[]>([]);
    const [isExporting, setIsExporting] = useState(false);
    const [isTemplateDownloading, setIsTemplateDownloading] = useState(false);

    const allSelected =
        fields.length > 0 && selectedFields.length === fields.length;

    const toggleField = (key: string) => {
        setSelectedFields((prev) =>
            prev.includes(key)
                ? prev.filter((k) => k !== key)
                : [...prev, key],
        );
    };

    const toggleAll = () => {
        setSelectedFields(allSelected ? [] : fields.map((f) => f.key));
    };

    const handleExport = async () => {
        setIsExporting(true);
        try {
            await onExport({ fields: selectedFields, format, presentation });
            toast.success(successMessage);
            onOpenChange(false);
        } catch (err) {
            toast.error(getApiError(err) ?? "خطا در ایجاد خروجی.");
        } finally {
            setIsExporting(false);
        }
    };

    const handleTemplate = async () => {
        if (!onTemplate) return;
        setIsTemplateDownloading(true);
        try {
            await onTemplate(format);
            toast.success("قالب خالی با موفقیت دانلود شد.");
        } catch (err) {
            toast.error(getApiError(err) ?? "خطا در دانلود قالب.");
        } finally {
            setIsTemplateDownloading(false);
        }
    };

    return (
        <ResponsiveDialog
            open={open}
            onOpenChange={onOpenChange}
            title={title}
            description={description}
            footer={
                <>
                    <Button variant="ghost" onClick={() => onOpenChange(false)}>
                        انصراف
                    </Button>
                    {onTemplate && (
                        <Button
                            variant="outline"
                            onClick={handleTemplate}
                            disabled={isTemplateDownloading || isExporting}
                        >
                            {isTemplateDownloading ? (
                                <IconLoader2 className="size-4 animate-spin" />
                            ) : (
                                <IconFileText className="size-4" />
                            )}
                            دانلود قالب
                        </Button>
                    )}
                    <Button onClick={handleExport} disabled={isExporting}>
                        {isExporting ? (
                            <IconLoader2 className="size-4 animate-spin" />
                        ) : (
                            <IconDownload className="size-4" />
                        )}
                        دریافت خروجی
                    </Button>
                </>
            }
        >
            <div className="space-y-6">
                {activeFilters.length > 0 && (
                    <div className="space-y-2">
                        <Label>فیلترهای اعمال‌شده</Label>
                        <div className="flex flex-wrap gap-1.5">
                            {activeFilters.map((filter) => (
                                <span
                                    key={filter}
                                    className="bg-muted rounded-md px-2 py-0.5 text-xs text-muted-foreground"
                                >
                                    {filter}
                                </span>
                            ))}
                        </div>
                        <p className="text-xs text-muted-foreground">
                            خروجی فقط شامل ردیف‌های مطابق این فیلترها است.
                        </p>
                    </div>
                )}

                {showPresentation && (
                    <div className="space-y-4">
                        <div className="space-y-2">
                            <Label>تقویم تاریخ‌ها</Label>
                            <RadioGroup
                                value={presentation.calendar}
                                onValueChange={(value) =>
                                    setPresentation((prev) => ({
                                        ...prev,
                                        calendar: value as ExportPresentation["calendar"],
                                    }))
                                }
                                className="flex flex-wrap gap-4"
                            >
                                {(
                                    [
                                        { value: "persian", label: "شمسی" },
                                        { value: "gregorian", label: "میلادی" },
                                        { value: "both", label: "هر دو" },
                                    ] as const
                                ).map((option) => (
                                    <div
                                        key={option.value}
                                        className="flex items-center gap-2"
                                    >
                                        <RadioGroupItem
                                            value={option.value}
                                            id={`export-calendar-${option.value}`}
                                        />
                                        <Label
                                            htmlFor={`export-calendar-${option.value}`}
                                            className="font-normal cursor-pointer"
                                        >
                                            {option.label}
                                        </Label>
                                    </div>
                                ))}
                            </RadioGroup>
                        </div>

                        {showDetailSheets && (
                            <div className="space-y-2">
                                <div className="flex items-center gap-2">
                                    <Checkbox
                                        id="export-detail-sheets"
                                        checked={presentation.detailSheets}
                                        onCheckedChange={(checked) =>
                                            setPresentation((prev) => ({
                                                ...prev,
                                                detailSheets:
                                                    checked === true,
                                            }))
                                        }
                                    />
                                    <Label
                                        htmlFor="export-detail-sheets"
                                        className="font-normal cursor-pointer"
                                    >
                                        ردیف‌های تکرارشونده در شیت جداگانه
                                    </Label>
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    بستگان، سوابق تحصیلی، حساب‌های بانکی و
                                    سوابق بیمه هر کارمند در شیتی جداگانه نوشته
                                    می‌شوند؛ ستون تعداد در لیست اصلی به آن شیت
                                    لینک می‌شود. فقط در خروجی اکسل اعمال می‌شود.
                                </p>
                            </div>
                        )}

                        <div className="space-y-2">
                            <Label>اعداد</Label>
                            <RadioGroup
                                value={presentation.digits}
                                onValueChange={(value) =>
                                    setPresentation((prev) => ({
                                        ...prev,
                                        digits: value as ExportPresentation["digits"],
                                    }))
                                }
                                className="flex flex-wrap gap-4"
                            >
                                {(
                                    [
                                        { value: "latin", label: "انگلیسی" },
                                        { value: "persian", label: "فارسی" },
                                    ] as const
                                ).map((option) => (
                                    <div
                                        key={option.value}
                                        className="flex items-center gap-2"
                                    >
                                        <RadioGroupItem
                                            value={option.value}
                                            id={`export-digits-${option.value}`}
                                        />
                                        <Label
                                            htmlFor={`export-digits-${option.value}`}
                                            className="font-normal cursor-pointer"
                                        >
                                            {option.label}
                                        </Label>
                                    </div>
                                ))}
                            </RadioGroup>
                            <p className="text-xs text-muted-foreground">
                                اعداد فارسی در اکسل به‌عنوان متن شناخته می‌شوند
                                و مرتب‌سازی/فیلتر عددی از کار می‌افتد.
                            </p>
                        </div>
                    </div>
                )}

                <div className="space-y-2">
                    <Label>قالب فایل</Label>
                    <RadioGroup
                        value={format}
                        onValueChange={(value) =>
                            setFormat(value as ExportFormat)
                        }
                        className="flex flex-wrap gap-4"
                    >
                        {formats.map((option) => (
                            <div
                                key={option.value}
                                className="flex items-center gap-2"
                            >
                                <RadioGroupItem
                                    value={option.value}
                                    id={`export-format-${option.value}`}
                                />
                                <Label
                                    htmlFor={`export-format-${option.value}`}
                                    className="font-normal cursor-pointer"
                                >
                                    {option.label}
                                </Label>
                            </div>
                        ))}
                    </RadioGroup>
                </div>

                <div className="space-y-2">
                    <div className="flex items-center justify-between">
                        <Label>ستون‌های خروجی</Label>
                        <Button
                            variant="ghost"
                            size="sm"
                            className="h-7 px-2 text-xs"
                            onClick={toggleAll}
                            disabled={fieldsLoading || fields.length === 0}
                        >
                            {allSelected ? (
                                <IconCheckbox className="size-4" />
                            ) : (
                                <IconSquare className="size-4" />
                            )}
                            {allSelected ? "هیچیک" : "همه"}
                        </Button>
                    </div>
                    {selectedFields.length === 0 && (
                        <p className="text-xs text-muted-foreground">
                            بدون انتخاب، همه ستون‌ها در خروجی قرار می‌گیرند.
                        </p>
                    )}
                    {fieldsLoading ? (
                        <div className="text-muted-foreground flex items-center gap-2 py-4 text-sm">
                            <IconLoader2 className="size-4 animate-spin" />
                            در حال بارگذاری فیلدها…
                        </div>
                    ) : (
                        <div className="grid max-h-64 grid-cols-1 gap-2 overflow-y-auto sm:grid-cols-2">
                            {fields.map((field) => (
                                <div
                                    key={field.key}
                                    className="flex items-center gap-2"
                                >
                                    <Checkbox
                                        id={`export-field-${field.key}`}
                                        checked={selectedFields.includes(
                                            field.key,
                                        )}
                                        onCheckedChange={() =>
                                            toggleField(field.key)
                                        }
                                    />
                                    <Label
                                        htmlFor={`export-field-${field.key}`}
                                        className="font-normal cursor-pointer"
                                    >
                                        {field.label}
                                    </Label>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </ResponsiveDialog>
    );
}
