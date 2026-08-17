import { useEffect, useMemo, useState } from "react";
import { IconListDetails, IconPencil, IconPlus } from "@tabler/icons-react";

import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import { ConfirmDeleteButton } from "@/components/ui/confirm-delete-button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { ResponsiveDialog } from "@/components/ui/responsive-dialog";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { Skeleton } from "@/components/ui/skeleton";
import { Switch } from "@/components/ui/switch";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/components/ui/table";
import { EmptyState } from "@/components/layout";
import { ErrorSection } from "@/components/layout";
import { usePermission } from "@/features/auth/components/permission-guard";
import { groupDisplayName } from "@/features/form-options/groups";
import {
    useAdminFormOptions,
    useFormOptionsAdmin,
} from "@/features/form-options/hooks/use-form-options";
import type { FormOption } from "@/features/form-options/types";
import { PERMISSIONS } from "@/lib/permissions";

type OptionFormState = {
    value: string;
    label: string;
    parent_value: string;
    group_label: string;
    meta: string;
    sort_order: string;
    is_active: boolean;
};

function emptyForm(nextSortOrder: number): OptionFormState {
    return {
        value: "",
        label: "",
        parent_value: "",
        group_label: "",
        meta: "",
        sort_order: String(nextSortOrder),
        is_active: true,
    };
}

function formFromOption(option: FormOption): OptionFormState {
    return {
        value: option.value,
        label: option.label,
        parent_value: option.parent_value ?? "",
        group_label: option.group_label ?? "",
        meta: option.meta ? JSON.stringify(option.meta, null, 2) : "",
        sort_order: String(option.sort_order),
        is_active: option.is_active,
    };
}

function parseMeta(raw: string): Record<string, unknown> | null {
    const trimmed = raw.trim();
    if (!trimmed) return null;
    try {
        const parsed = JSON.parse(trimmed);
        return parsed && typeof parsed === "object" && !Array.isArray(parsed)
            ? parsed
            : null;
    } catch {
        return null;
    }
}

export function FormOptionsSection() {
    const { data, isLoading, isError, refetch } = useAdminFormOptions();
    const admin = useFormOptionsAdmin();
    const canManage = usePermission([PERMISSIONS.FORM_OPTIONS_MANAGE]);

    const [selectedGroup, setSelectedGroup] = useState<string>("");
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editing, setEditing] = useState<FormOption | null>(null);
    const [form, setForm] = useState<OptionFormState>(emptyForm(0));

    const groups = useMemo(() => {
        if (!data) return [];
        const seen = new Map<string, { label: string }>();
        for (const option of data) {
            if (!seen.has(option.group)) {
                seen.set(
                    option.group,
                    { label: groupDisplayName(option.group, option.group_label) },
                );
            }
        }
        return Array.from(seen.entries()).map(([group, meta]) => ({
            group,
            groupLabel: meta.label,
        }));
    }, [data]);

    useEffect(() => {
        if (!selectedGroup && groups.length > 0) {
            setSelectedGroup(groups[0].group);
        }
    }, [groups, selectedGroup]);

    const rows = useMemo(
        () => (data ? data.filter((o) => o.group === selectedGroup) : []),
        [data, selectedGroup],
    );

    const activeGroup = groups.find((g) => g.group === selectedGroup);

    const openCreate = () => {
        if (!selectedGroup) return;
        const nextSortOrder = rows.reduce(
            (max, o) => Math.max(max, o.sort_order),
            0,
        ) + 1;
        setEditing(null);
        setForm(emptyForm(nextSortOrder));
        setDialogOpen(true);
    };

    const openEdit = (option: FormOption) => {
        setEditing(option);
        setForm(formFromOption(option));
        setDialogOpen(true);
    };

    const handleSave = () => {
        const payload = {
            label: form.label.trim(),
            parent_value: form.parent_value.trim() || null,
            group_label: form.group_label.trim() || null,
            meta: parseMeta(form.meta),
            sort_order: Number(form.sort_order) || 0,
            is_active: form.is_active,
        };

        if (editing) {
            admin.update.mutate({ id: editing.id, data: payload });
        } else {
            admin.create.mutate({
                group: selectedGroup,
                value: form.value.trim(),
                ...payload,
            });
        }
        setDialogOpen(false);
    };

    const saveDisabled = (editing ? false : !form.value.trim()) || !form.label.trim();

    const groupLabel = selectedGroup
        ? groupDisplayName(selectedGroup, activeGroup?.groupLabel)
        : "";

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <IconListDetails className="size-5" />
                    گزینه‌های فرم
                </CardTitle>
                <CardDescription>
                    مدیریت گزینه‌های بازشو (جنسیت، وضعیت تأهل، نوع همکاری و…) در فرم‌ها
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <Select
                        value={selectedGroup || null}
                        onValueChange={(value: string | null) => {
                            if (value) setSelectedGroup(value);
                        }}
                        itemToStringLabel={(val) =>
                            groups.find((g) => g.group === val)?.groupLabel ??
                            (val as string)
                        }
                    >
                        <SelectTrigger className="w-full sm:w-64">
                            <SelectValue placeholder="انتخاب گروه" />
                        </SelectTrigger>
                        <SelectContent>
                            {groups.map((group) => (
                                <SelectItem key={group.group} value={group.group}>
                                    {group.groupLabel}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    {canManage && (
                        <Button
                            type="button"
                            onClick={openCreate}
                            disabled={!selectedGroup || admin.create.isPending}
                        >
                            <IconPlus className="size-4" />
                            افزودن گزینه
                        </Button>
                    )}
                </div>

                {isLoading ? (
                    <div className="mt-6 space-y-2">
                        {Array.from({ length: 5 }).map((_, i) => (
                            <Skeleton key={i} className="h-10 w-full" />
                        ))}
                    </div>
                ) : isError ? (
                    <div className="mt-6">
                        <ErrorSection onRetry={() => refetch()} />
                    </div>
                ) : !data?.length ? (
                    <div className="mt-6">
                        <EmptyState
                            icon={IconListDetails}
                            message="هنوز گزینه‌ای ثبت نشده است"
                        />
                    </div>
                ) : (
                    <div className="mt-6 rounded-lg border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>عنوان</TableHead>
                                    <TableHead>مقدار</TableHead>
                                    <TableHead>وابسته به</TableHead>
                                    <TableHead>متادیتا</TableHead>
                                    <TableHead className="w-20 text-center">ترتیب</TableHead>
                                    <TableHead className="w-20 text-center">فعال</TableHead>
                                    {canManage && <TableHead className="w-24" />}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {rows.map((option) => (
                                    <TableRow key={option.id}>
                                        <TableCell className="font-medium">
                                            {option.label}
                                        </TableCell>
                                        <TableCell dir="ltr" className="text-muted-foreground">
                                            {option.value}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {option.parent_value || <span>—</span>}
                                        </TableCell>
                                        <TableCell
                                            dir="ltr"
                                            className="max-w-[14rem] truncate text-muted-foreground"
                                            title={
                                                option.meta
                                                    ? JSON.stringify(option.meta)
                                                    : undefined
                                            }
                                        >
                                            {option.meta ? JSON.stringify(option.meta) : <span>—</span>}
                                        </TableCell>
                                        <TableCell className="text-center">
                                            {option.sort_order}
                                        </TableCell>
                                        <TableCell className="text-center">
                                            {canManage ? (
                                                <Switch
                                                    size="sm"
                                                    checked={option.is_active}
                                                    onCheckedChange={() =>
                                                        admin.toggle.mutate(option.id)
                                                    }
                                                    disabled={admin.toggle.isPending}
                                                />
                                            ) : (
                                                <Badge
                                                    variant={
                                                        option.is_active ? "default" : "secondary"
                                                    }
                                                >
                                                    {option.is_active ? "فعال" : "غیرفعال"}
                                                </Badge>
                                            )}
                                        </TableCell>
                                        {canManage && (
                                            <TableCell>
                                                <div className="flex items-center gap-1">
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon-sm"
                                                        onClick={() => openEdit(option)}
                                                    >
                                                        <IconPencil className="size-4" />
                                                    </Button>
                                                    <ConfirmDeleteButton
                                                        iconOnly
                                                        onConfirm={() =>
                                                            admin.remove.mutate(option.id)
                                                        }
                                                        isPending={admin.remove.isPending}
                                                    />
                                                </div>
                                            </TableCell>
                                        )}
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}
            </CardContent>

            <ResponsiveDialog
                open={dialogOpen}
                onOpenChange={setDialogOpen}
                title={editing ? "ویرایش گزینه" : "افزودن گزینه"}
                description={
                    editing
                        ? `گزینه «${groupLabel}»`
                        : `گزینه جدید در گروه «${groupLabel}»`
                }
                footer={
                    <>
                        <Button
                            variant="outline"
                            className="flex-1"
                            onClick={() => setDialogOpen(false)}
                        >
                            انصراف
                        </Button>
                        <Button
                            className="flex-1"
                            onClick={handleSave}
                            disabled={
                                saveDisabled ||
                                admin.create.isPending ||
                                admin.update.isPending
                            }
                        >
                            {editing ? "ذخیره تغییرات" : "افزودن"}
                        </Button>
                    </>
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
                                    setForm((f) => ({ ...f, value: e.target.value }))
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
                                setForm((f) => ({ ...f, label: e.target.value }))
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
                                    setForm((f) => ({
                                        ...f,
                                        group_label: e.target.value,
                                    }))
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
                                    setForm((f) => ({
                                        ...f,
                                        sort_order: e.target.value,
                                    }))
                                }
                            />
                        </div>
                    </div>
                    <div className="grid gap-2">
                        <Label>وابسته به (مقدار والد)</Label>
                        <Input
                            dir="ltr"
                            value={form.parent_value}
                            onChange={(e) =>
                                setForm((f) => ({
                                    ...f,
                                    parent_value: e.target.value,
                                }))
                            }
                            placeholder="در صورت نیاز"
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label>متادیتا (JSON)</Label>
                        <textarea
                            dir="ltr"
                            rows={4}
                            value={form.meta}
                            onChange={(e) =>
                                setForm((f) => ({ ...f, meta: e.target.value }))
                            }
                            placeholder='مثلاً {"slug":"markazi"}'
                            className="flex min-h-[6rem] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                        />
                    </div>
                    <div className="flex items-center gap-2">
                        <Switch
                            checked={form.is_active}
                            onCheckedChange={(checked) =>
                                setForm((f) => ({ ...f, is_active: checked }))
                            }
                        />
                        <Label>فعال</Label>
                    </div>
                </div>
            </ResponsiveDialog>
        </Card>
    );
}
