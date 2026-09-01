import { Fragment } from "react";
import { useFormOptionsByGroup } from "@/features/form-options/hooks/use-form-options";
import type { FormOption } from "@/features/form-options/types";

import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { Input } from "@/components/ui/input";
import { Switch } from "@/components/ui/switch";
import { ResponsiveDialog } from "@/components/ui/responsive-dialog";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import type { OptionFormState, OptionFormActions } from "./form-option-form-state";

type OptionEditorDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    editing: FormOption | null;
    groupLabel: string;
    selectedGroup: string;
    form: OptionFormState;
    actions: OptionFormActions;
    saveDisabled: boolean;
    isPending: boolean;
    onSave: () => void;
};

export function OptionEditorDialog({
    open,
    onOpenChange,
    editing,
    groupLabel,
    selectedGroup,
    form,
    actions,
    saveDisabled,
    isPending,
    onSave,
}: OptionEditorDialogProps) {
    const { data: provinceOptions = [] } = useFormOptionsByGroup("province");

    return (
        <ResponsiveDialog
            open={open}
            onOpenChange={onOpenChange}
            title={editing ? "ویرایش گزینه" : "افزودن گزینه"}
            description={
                editing
                    ? `گزینه «${groupLabel}»`
                    : `گزینه جدید در گروه «${groupLabel}»`
            }
            footer={
                <Fragment>
                    <Button
                        variant="outline"
                        className="flex-1"
                        onClick={() => onOpenChange(false)}
                    >
                        انصراف
                    </Button>
                    <Button
                        className="flex-1"
                        onClick={onSave}
                        disabled={saveDisabled || isPending}
                    >
                        {editing ? "ذخیره تغییرات" : "افزودن"}
                    </Button>
                </Fragment>
            }
        >
            <div className="grid gap-4 py-2">
                {!editing && (
                    <div className="grid gap-2">
                        <Label>مقدار (ارسال به سرور)</Label>
                        <Input
                            dir="ltr"
                            value={form.value}
                            onChange={(e) =>
                                actions.patch({ value: e.target.value })
                            }
                            placeholder="مثلاً single"
                        />
                    </div>
                )}
                <div className="grid gap-2">
                    <Label>عنوان نمایشی</Label>
                    <Input
                        value={form.label}
                        onChange={(e) =>
                            actions.patch({ label: e.target.value })
                        }
                        placeholder="مثلاً مجرد"
                    />
                </div>
                <div className="grid grid-cols-2 gap-4">
                    <div className="grid gap-2">
                        <Label>عنوان گروه</Label>
                        <Input
                            value={form.group_label}
                            onChange={(e) =>
                                actions.patch({ group_label: e.target.value })
                            }
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label>ترتیب</Label>
                        <Input
                            dir="ltr"
                            type="number"
                            value={form.sort_order}
                            onChange={(e) =>
                                actions.patch({ sort_order: e.target.value })
                            }
                        />
                    </div>
                </div>
                <div className="grid gap-2">
                    {selectedGroup === "city" ? (
                        <>
                            <Label>استان والد</Label>
                            <Select
                                value={form.parent_value || null}
                                onValueChange={(value: string | null) => {
                                    if (value === null) return;
                                    actions.patch({ parent_value: value });
                                }}
                                itemToStringLabel={(val) =>
                                    provinceOptions.find(
                                        (o) => o.value === val,
                                    )?.label ?? (val as string)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="انتخاب استان" />
                                </SelectTrigger>
                                <SelectContent>
                                    {provinceOptions.map((option) => (
                                        <SelectItem
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </>
                    ) : (
                        <>
                            <Label>وابسته به (مقدار والد)</Label>
                            <Input
                                dir="ltr"
                                value={form.parent_value}
                                onChange={(e) =>
                                    actions.patch({
                                        parent_value: e.target.value,
                                    })
                                }
                                placeholder="در صورت نیاز"
                            />
                        </>
                    )}
                </div>
                <div className="grid gap-2">
                    <Label>متادیتا (JSON)</Label>
                    <textarea
                        dir="ltr"
                        rows={4}
                        value={form.meta}
                        onChange={(e) =>
                            actions.patch({ meta: e.target.value })
                        }
                        placeholder='مثلاً {"slug":"markazi"}'
                        className="flex min-h-[6rem] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                    />
                </div>
                <div className="flex items-center gap-2">
                    <Switch
                        checked={form.is_active}
                        onCheckedChange={(checked) =>
                            actions.patch({ is_active: checked })
                        }
                    />
                    <Label>فعال</Label>
                </div>
            </div>
        </ResponsiveDialog>
    );
}