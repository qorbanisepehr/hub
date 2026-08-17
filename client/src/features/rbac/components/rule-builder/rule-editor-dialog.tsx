import { useEffect, useMemo, useState } from "react";
import { useMutation } from "@tanstack/react-query";
import { IconPlus, IconLoader2 } from "@tabler/icons-react";

import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Switch } from "@/components/ui/switch";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { Field, FieldLabel, FieldError } from "@/components/ui/field";
import { UserSearchSelect } from "@/features/rbac/components/user-search-select";
import { previewRule } from "@/features/rbac/api";
import { getApiError } from "@/lib/error-utils";
import type { UserListItem } from "@/features/rbac/types";
import type {
    AccessRule,
    AccessRuleInput,
    AccessRulePolicy,
    Permission,
    RuleBuilderMeta,
} from "@/features/rbac/types";
import {
    type EditorDraft,
    type ConditionDraft,
    EFFECT_LABELS,
    emptyDraft,
    emptyCondition,
    policyToDraft,
    conditionToLeaf,
    attributeTypeFor,
} from "./types";
import { ConditionRow } from "./condition-row";

interface RuleEditorDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onSave: (rule: AccessRuleInput) => void;
    meta: RuleBuilderMeta | undefined;
    permissions: Permission[];
    initialRule: AccessRule | AccessRuleInput | undefined;
}

export function RuleEditorDialog({
    open,
    onOpenChange,
    onSave,
    meta,
    permissions,
    initialRule,
}: RuleEditorDialogProps) {
    const [draft, setDraft] = useState<EditorDraft>(emptyDraft());
    const [previewUser, setPreviewUser] = useState<UserListItem | null>(null);
    const [previewResourceType, setPreviewResourceType] = useState("");
    const [previewResourceId, setPreviewResourceId] = useState("");

    const permissionById = useMemo(
        () => new Map(permissions.map((permission) => [permission.id, permission])),
        [permissions],
    );

    const permission = draft.permission_id
        ? permissionById.get(draft.permission_id)
        : undefined;

    const resourceTypeKey = permission?.policy_resource ?? null;
    const resourceType = meta?.resource_types.find(
        (rt) => rt.key === resourceTypeKey,
    );

    const groups = useMemo(() => {
        const grouped = new Map<number, { name: string; permissions: typeof permissions }>();
        for (const item of permissions) {
            const groupId = item.group_id;
            const entry = grouped.get(groupId) ?? {
                name: item.group?.name ?? `گروه ${String(groupId)}`,
                permissions: [],
            };
            entry.permissions.push(item);
            grouped.set(groupId, entry);
        }
        return Array.from(grouped.values());
    }, [permissions]);

    const previewMutation = useMutation({
        mutationFn: previewRule,
    });

    useEffect(() => {
        if (open) {
            setDraft(
                initialRule
                    ? {
                          permission_id: initialRule.permission_id,
                          effect: initialRule.effect,
                          priority:
                              initialRule.priority === null
                                  ? "0"
                                  : String(initialRule.priority),
                          is_active: initialRule.is_active ?? true,
                          conditions: policyToDraft(initialRule.policy),
                      }
                    : emptyDraft(),
            );
            setPreviewUser(null);
            setPreviewResourceType("");
            setPreviewResourceId("");
            previewMutation.reset();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    const setPermission = (permissionId: number) => {
        setDraft((current) => ({
            ...current,
            permission_id: permissionId,
            conditions: [],
        }));
        previewMutation.reset();
    };

    const updateCondition = (index: number, patch: Partial<ConditionDraft>) => {
        setDraft((current) => ({
            ...current,
            conditions: current.conditions.map((condition, conditionIndex) =>
                conditionIndex === index ? { ...condition, ...patch } : condition,
            ),
        }));
    };

    const buildPolicy = (): AccessRulePolicy | null => {
        const conditions = draft.conditions
            .filter((condition) => condition.attribute && condition.operator)
            .map((condition) =>
                conditionToLeaf(
                    condition,
                    attributeTypeFor(
                        meta,
                        resourceTypeKey,
                        condition.attribute,
                    ),
                ),
            );

        return conditions.length > 0 ? { all: conditions } : null;
    };

    const handlePreview = () => {
        if (!permission) return;

        previewMutation.mutate({
            permission: permission.name,
            policy: buildPolicy(),
            user_id: previewUser?.id ?? 0,
            resource_type: previewResourceType || null,
            resource_id: previewResourceId
                ? Number(previewResourceId)
                : null,
        });
    };

    const handleSaveClick = () => {
        if (!draft.permission_id) return;

        onSave({
            permission_id: draft.permission_id,
            effect: draft.effect,
            priority: draft.priority === "" ? null : Number(draft.priority),
            is_active: draft.is_active,
            policy: buildPolicy(),
        });
    };

    const previewResult = previewMutation.data?.data?.data;

    const valueSourceOptions = meta?.value_sources ?? [];
    const operatorOptions = meta?.operators ?? [];

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                if (!next) previewMutation.reset();
                onOpenChange(next);
            }}
        >
            <DialogContent className="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {initialRule ? "ویرایش قانون دسترسی" : "قانون دسترسی جدید"}
                    </DialogTitle>
                    <DialogDescription>
                        مجوز، اثر و شرایط دسترسی را تعیین کنید. شرایط با «همه
                        موارد» به هم می‌پیوندند.
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-4 py-2">
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <Field>
                            <FieldLabel>مجوز</FieldLabel>
                            <Select
                                value={
                                    draft.permission_id === null
                                        ? ""
                                        : String(draft.permission_id)
                                }
                                onValueChange={(value) =>
                                    setPermission(Number(value))
                                }
                                itemToStringLabel={(val) =>
                                    permissionById.get(Number(val))
                                        ?.display_name ?? ""
                                }
                            >
                                <SelectTrigger
                                    id="rule-permission"
                                    className="w-full"
                                >
                                    <SelectValue placeholder="انتخاب مجوز..." />
                                </SelectTrigger>
                                <SelectContent>
                                    {groups.map((group) => (
                                        <SelectGroup key={group.name}>
                                            <SelectLabel>{group.name}</SelectLabel>
                                            {group.permissions.map((perm) => (
                                                <SelectItem
                                                    key={perm.id}
                                                    value={String(perm.id)}
                                                >
                                                    {perm.display_name}
                                                </SelectItem>
                                            ))}
                                        </SelectGroup>
                                    ))}
                                </SelectContent>
                            </Select>
                        </Field>

                        <div className="grid grid-cols-2 gap-4">
                            <Field>
                                <FieldLabel>اثر</FieldLabel>
                                <Select
                                    value={draft.effect}
                                    onValueChange={(value) =>
                                        setDraft((current) => ({
                                            ...current,
                                            effect: value as "allow" | "deny",
                                        }))
                                    }
                                    itemToStringLabel={(val) =>
                                        EFFECT_LABELS[val as "allow" | "deny"] ?? ""
                                    }
                                >
                                    <SelectTrigger
                                        id="rule-effect"
                                        className="w-full"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="allow">
                                            اجازه دسترسی
                                        </SelectItem>
                                        <SelectItem value="deny">
                                            منع دسترسی
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </Field>
                            <Field>
                                <FieldLabel>اولویت</FieldLabel>
                                <Input
                                    id="rule-priority"
                                    type="number"
                                    min={0}
                                    dir="ltr"
                                    value={draft.priority}
                                    onChange={(e) =>
                                        setDraft((current) => ({
                                            ...current,
                                            priority: e.target.value,
                                        }))
                                    }
                                />
                            </Field>
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        <Switch
                            id="rule-active"
                            size="sm"
                            checked={draft.is_active}
                            onCheckedChange={(checked) =>
                                setDraft((current) => ({
                                    ...current,
                                    is_active: checked,
                                }))
                            }
                        />
                        <Label htmlFor="rule-active" className="cursor-pointer">
                            قانون فعال باشد
                        </Label>
                    </div>

                    <div className="space-y-3">
                        <div className="flex items-center justify-between">
                            <Label className="text-sm font-medium">شرایط</Label>
                            {resourceTypeKey === null && (
                                <p className="text-xs text-muted-foreground">
                                    این مجوز قابلیت تعریف شرط ندارد.
                                </p>
                            )}
                        </div>

                        {resourceTypeKey !== null && (
                            <>
                                {draft.conditions.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        شرطی تعریف نشده است؛ این قانون برای همه
                                        منابع برقرار است.
                                    </p>
                                ) : (
                                    <div className="space-y-3">
                                        {draft.conditions.map((condition, index) => (
                                            <ConditionRow
                                                key={index}
                                                index={index}
                                                condition={condition}
                                                resourceTypeKey={resourceTypeKey}
                                                resourceAttributes={
                                                    resourceType?.attributes ?? []
                                                }
                                                meta={meta}
                                                operatorOptions={operatorOptions}
                                                valueSourceOptions={
                                                    valueSourceOptions
                                                }
                                                onChange={updateCondition}
                                                onRemove={() =>
                                                    setDraft((current) => ({
                                                        ...current,
                                                        conditions:
                                                            current.conditions.filter(
                                                                (_, i) =>
                                                                    i !== index,
                                                            ),
                                                    }))
                                                }
                                            />
                                        ))}
                                    </div>
                                )}

                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() =>
                                        setDraft((current) => ({
                                            ...current,
                                            conditions: [
                                                ...current.conditions,
                                                emptyCondition(),
                                            ],
                                        }))
                                    }
                                    className="gap-1.5"
                                >
                                    <IconPlus className="size-4" />
                                    افزودن شرط
                                </Button>
                            </>
                        )}
                    </div>

                    <div className="space-y-3 rounded-lg border bg-muted/30 p-3">
                        <Label className="text-sm font-medium">
                            پیش‌نمایش قانون
                        </Label>
                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div className="sm:col-span-3">
                                <UserSearchSelect
                                    value={previewUser?.id ?? null}
                                    onChange={setPreviewUser}
                                    placeholder="انتخاب کاربر برای بررسی"
                                />
                            </div>
                            <Field>
                                <FieldLabel>نوع منبع</FieldLabel>
                                <Select
                                    value={previewResourceType}
                                    onValueChange={(value) =>
                                        setPreviewResourceType(value ?? "")
                                    }
                                    itemToStringLabel={(val) =>
                                        meta?.resource_types.find(
                                            (rt) => rt.key === val,
                                        )?.label ?? ""
                                    }
                                >
                                    <SelectTrigger
                                        id="preview-resource-type"
                                        className="w-full"
                                    >
                                        <SelectValue placeholder="بدون منبع" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="">بدون منبع</SelectItem>
                                        {meta?.resource_types.map((rt) => (
                                            <SelectItem key={rt.key} value={rt.key}>
                                                {rt.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </Field>
                            <Field>
                                <FieldLabel>شناسه منبع</FieldLabel>
                                <Input
                                    id="preview-resource-id"
                                    type="number"
                                    min={1}
                                    dir="ltr"
                                    disabled={!previewResourceType}
                                    value={previewResourceId}
                                    onChange={(e) =>
                                        setPreviewResourceId(e.target.value)
                                    }
                                    placeholder="1"
                                />
                            </Field>
                            <div className="flex items-end">
                                <Button
                                    type="button"
                                    variant="secondary"
                                    size="sm"
                                    onClick={handlePreview}
                                    disabled={
                                        !permission ||
                                        !previewUser ||
                                        previewMutation.isPending ||
                                        (previewResourceType !== "" &&
                                            !previewResourceId)
                                    }
                                    className="w-full"
                                >
                                    {previewMutation.isPending ? (
                                        <IconLoader2 className="size-4 animate-spin ms-1" />
                                    ) : null}
                                    بررسی قانون
                                </Button>
                            </div>
                        </div>

                        {previewMutation.isError && (
                            <p className="text-sm text-destructive">
                                {getApiError(previewMutation.error)}
                            </p>
                        )}

                        {previewMutation.data && previewResult && (
                            <div className="space-y-2 text-sm">
                                <div className="flex items-center gap-2">
                                    <Badge
                                        variant={
                                            previewResult.rule_matches
                                                ? "default"
                                                : "secondary"
                                        }
                                    >
                                        {previewResult.rule_matches
                                            ? "شرط برقرار است"
                                            : "شرط برقرار نیست"}
                                    </Badge>
                                    <Badge
                                        variant={
                                            previewResult.effective.allowed
                                                ? "default"
                                                : "destructive"
                                        }
                                    >
                                        {previewResult.effective.allowed
                                            ? "دسترسی فعلی دارد"
                                            : "دسترسی فعلی ندارد"}
                                    </Badge>
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    {previewResult.effective.reason}
                                </p>
                            </div>
                        )}
                    </div>
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
                        onClick={handleSaveClick}
                        disabled={!draft.permission_id}
                    >
                        ذخیره قانون
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
