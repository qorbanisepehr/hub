import { useEffect, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { toast } from "sonner";
import { IconDownload, IconLoader2 } from "@tabler/icons-react";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Label } from "@/components/ui/label";
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { ResponsiveDialog } from "@/components/ui/responsive-dialog";
import {
    exportRoleChart,
    fetchChartExportFields,
    type ChartExportField,
} from "@/features/rbac/api";
import { useRoleChart } from "@/features/rbac/hooks/use-roles";

type ExportChartDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

const DEFAULT_FIELDS = ["description", "is_active", "user_count"];

export function ExportChartDialog({
    open,
    onOpenChange,
}: ExportChartDialogProps) {
    const { data: roles } = useRoleChart();
    const [scope, setScope] = useState<"all" | "subtree">("all");
    const [rootId, setRootId] = useState<number | null>(null);
    const [selectedFields, setSelectedFields] = useState<string[]>([]);
    const [isExporting, setIsExporting] = useState(false);

    const { data: fields } = useQuery({
        queryKey: ["chart-export-fields"],
        queryFn: async () => {
            const { data } = await fetchChartExportFields();
            return data.data;
        },
        enabled: open,
    });
    const fieldList: ChartExportField[] = fields ?? [];

    useEffect(() => {
        if (open) {
            setScope("all");
            setRootId(null);
            setSelectedFields(DEFAULT_FIELDS);
        }
    }, [open]);

    const rootOptions = (roles ?? [])
        .slice()
        .sort((a, b) => a.display_name.localeCompare(b.display_name, "fa"));

    const toggleField = (key: string) => {
        setSelectedFields((prev) =>
            prev.includes(key) ? prev.filter((k) => k !== key) : [...prev, key],
        );
    };

    const handleExport = async () => {
        if (scope === "subtree" && rootId === null) {
            toast.error("لطفاً نقش ریشه را انتخاب کنید.");
            return;
        }
        setIsExporting(true);
        try {
            const response = await exportRoleChart({
                scope,
                root_id: scope === "subtree" ? rootId : undefined,
                fields: selectedFields,
                format: "csv",
            });

            // مهم: response.data یک Blob است (به‌خاطر responseType: "blob")
            // مستقیماً همان Blob را استفاده کنید، Blob جدید نسازید
            let csvBlob: Blob;
            if (response.data instanceof Blob) {
                csvBlob = response.data;
            } else {
                // اگر به‌دلیلی data رشته بود، آن را به Blob تبدیل کنید
                csvBlob = new Blob([response.data], {
                    type: "text/csv;charset=utf-8",
                });
            }

            const url = window.URL.createObjectURL(csvBlob);
            const link = document.createElement("a");
            link.href = url;
            link.download = `org-chart-roles-${new Date().toISOString().slice(0, 10)}.csv`;
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.URL.revokeObjectURL(url);

            toast.success("خروجی با موفقیت ایجاد شد.");
            onOpenChange(false);
        } catch {
            toast.error("خطا در ایجاد خروجی.");
        } finally {
            setIsExporting(false);
        }
    };

    return (
        <ResponsiveDialog
            open={open}
            onOpenChange={onOpenChange}
            title="خروجی چارت سازمانی"
            description="خروجی CSV سازگار با Visio برای واردسازی چارت سازمانی"
            footer={
                <>
                    <Button variant="ghost" onClick={() => onOpenChange(false)}>
                        انصراف
                    </Button>
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
                <div className="space-y-2">
                    <Label>محدوده خروجی</Label>
                    <RadioGroup
                        value={scope}
                        onValueChange={(value) => {
                            setScope(value as "all" | "subtree");
                            if (value === "all") setRootId(null);
                        }}
                    >
                        <div className="flex items-center gap-2">
                            <RadioGroupItem value="all" id="scope-all" />
                            <Label
                                htmlFor="scope-all"
                                className="font-normal cursor-pointer"
                            >
                                کل چارت
                            </Label>
                        </div>
                        <div className="flex items-center gap-2">
                            <RadioGroupItem
                                value="subtree"
                                id="scope-subtree"
                            />
                            <Label
                                htmlFor="scope-subtree"
                                className="font-normal cursor-pointer"
                            >
                                زیرمجموعه یک نقش
                            </Label>
                        </div>
                    </RadioGroup>
                </div>

                {scope === "subtree" && (
                    <div className="space-y-2">
                        <Label htmlFor="root-role">نقش ریشه</Label>
                        <Select
                            value={rootId === null ? null : String(rootId)}
                            onValueChange={(value) =>
                                setRootId(value === null ? null : Number(value))
                            }
                            itemToStringLabel={(value) =>
                                value
                                    ? (rootOptions.find(
                                          (r) => String(r.id) === value,
                                      )?.display_name ?? "")
                                    : ""
                            }
                        >
                            <SelectTrigger id="root-role">
                                <SelectValue placeholder="انتخاب نقش ریشه" />
                            </SelectTrigger>
                            <SelectContent>
                                {rootOptions.map((role) => (
                                    <SelectItem
                                        key={role.id}
                                        value={String(role.id)}
                                    >
                                        {role.display_name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <p className="text-xs text-muted-foreground">
                            خروجی شامل نقش انتخاب‌شده و همه زیرمجموعه‌های آن
                            خواهد بود.
                        </p>
                    </div>
                )}

                <div className="space-y-2">
                    <Label>فیلدهای خروجی</Label>
                    <p className="text-xs text-muted-foreground">
                        ستون‌های Name و Manager برای ساخت ساختار چارت همیشه
                        اضافه می‌شوند. فیلدهای زیر به‌عنوان ستون‌های اضافی اضافه
                        می‌شوند.
                    </p>
                    <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                        {fieldList.map((field) => (
                            <div
                                key={field.key}
                                className="flex items-center gap-2"
                            >
                                <Checkbox
                                    id={`field-${field.key}`}
                                    checked={selectedFields.includes(field.key)}
                                    onCheckedChange={() =>
                                        toggleField(field.key)
                                    }
                                />
                                <Label
                                    htmlFor={`field-${field.key}`}
                                    className="font-normal cursor-pointer"
                                >
                                    {field.label}
                                </Label>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </ResponsiveDialog>
    );
}
