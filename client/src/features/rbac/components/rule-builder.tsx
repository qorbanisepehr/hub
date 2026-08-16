import { useEffect, useMemo, useState } from "react";
import { useMutation, useQuery } from "@tanstack/react-query";
import { IconPlus, IconTrash, IconLoader2 } from "@tabler/icons-react";
import { cn } from "@/lib/utils";

import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Switch } from "@/components/ui/switch";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Skeleton } from "@/components/ui/skeleton";
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
import { usePermissions } from "@/features/rbac/hooks/use-permissions";
import { fetchRuleBuilderMeta, previewRule } from "@/features/rbac/api";
import { ruleBuilderKeys } from "@/lib/query-keys";
import { getApiError } from "@/lib/error-utils";
import type { UserListItem } from "@/features/rbac/types";
import type {
    AccessRule,
    AccessRuleInput,
    AccessRulePolicy,
    AccessRulePolicyCondition,
    Permission,
    PermissionGroup,
    RuleBuilderMeta,
} from "@/features/rbac/types";

const NULLARY_OPERATORS = new Set([
    "is_null",
    "is_not_null",
    "exists",
    "not_exists",
]);

const SET_OPERATORS = new Set(["in", "not_in"]);

const EFFECT_LABELS: Record<"allow" | "deny", string> = {
    allow: "اجازه دسترسی",
    deny: "منع دسترسی",
};

const VALUE_SOURCE_KEYS = ["literal", "actor", "resource", "context"] as const;

type ConditionDraft = {
    attribute: string;
    operator: string;
    value_source: (typeof VALUE_SOURCE_KEYS)[number];
    value: string;
};

type EditorDraft = {
    permission_id: number | null;
    effect: "allow" | "deny";
    priority: string;
    is_active: boolean;
    conditions: ConditionDraft[];
};

const emptyCondition = (): ConditionDraft => ({
    attribute: "",
    operator: "",
    value_source: "literal",
    value: "",
});

const emptyDraft = (): EditorDraft => ({
    permission_id: null,
    effect: "allow",
    priority: "0",
    is_active: true,
    conditions: [],
});

function policyToDraft(policy: AccessRulePolicy | null | undefined): ConditionDraft[] {
    if (!policy?.all) return [];

    return policy.all.map((condition) => ({
        attribute: condition.attribute,
        operator: condition.operator,
        value_source: (VALUE_SOURCE_KEYS as readonly string[]).includes(
            condition.value_source,
        )
            ? (condition.value_source as ConditionDraft["value_source"])
            : "literal",
        value:
            condition.value_source === "actor" &&
            typeof condition.value === "string"
                ? toUserAttributeKey(condition.value)
                : serializeConditionValue(condition.value),
    }));
}

function serializeConditionValue(value: unknown): string {
    if (Array.isArray(value)) return value.join("، ");
    if (value === null || value === undefined) return "";
    return String(value);
}

function toUserAttributeKey(value: string): string {
    return value.startsWith("user.") ? value : `user.${value}`;
}

function fromUserAttributeKey(value: string): string {
    return value.replace(/^user\./, "");
}

function attributeTypeFor(
    meta: RuleBuilderMeta | undefined,
    resourceKey: string | null,
    attributeKey: string,
): string | undefined {
    if (!meta) return undefined;
    const resourceType = meta.resource_types.find((rt) => rt.key === resourceKey);
    return resourceType?.attributes.find((attr) => attr.key === attributeKey)?.type;
}

function conditionNeedsValue(operator: string): boolean {
    return !NULLARY_OPERATORS.has(operator);
}

function conditionToLeaf(
    condition: ConditionDraft,
    attributeType: string | undefined,
): AccessRulePolicyCondition {
    const leaf: AccessRulePolicyCondition = {
        attribute: condition.attribute,
        operator: condition.operator,
        value_source: condition.value_source,
    };

    if (!conditionNeedsValue(condition.operator)) {
        leaf.value = null;

        return leaf;
    }

    if (condition.value_source !== "literal") {
        leaf.value =
            condition.value_source === "actor"
                ? fromUserAttributeKey(condition.value)
                : condition.value;

        return leaf;
    }

    if (SET_OPERATORS.has(condition.operator)) {
        leaf.value = condition.value
            .split(/[،,]/)
            .map((part) => part.trim())
            .filter(Boolean);

        return leaf;
    }

    if (attributeType === "integer" && condition.value !== "") {
        leaf.value = Number(condition.value);

        return leaf;
    }

    leaf.value = condition.value;

    return leaf;
}

function flattenPermissions(groups: PermissionGroup[] | undefined): Permission[] {
    return groups?.flatMap((group) => group.permissions ?? []) ?? [];
}

interface RuleBuilderProps {
    value: AccessRuleInput[];
    onChange: (rules: AccessRuleInput[]) => void;
}

export function RuleBuilder({ value, onChange }: RuleBuilderProps) {
    const { data: meta } = useQuery({
        queryKey: ruleBuilderKeys.meta(),
        queryFn: async () => {
            const { data } = await fetchRuleBuilderMeta();
            return data.data;
        },
        staleTime: 5 * 60 * 1000,
    });

    const { data: groups, isLoading } = usePermissions();
    const permissions = useMemo(() => flattenPermissions(groups), [groups]);

    const permissionById = useMemo(
        () => new Map(permissions.map((permission) => [permission.id, permission])),
        [permissions],
    );

    const [editorOpen, setEditorOpen] = useState(false);
    const [editingIndex, setEditingIndex] = useState<number | null>(null);

    const openEditor = (index: number | null) => {
        setEditingIndex(index);
        setEditorOpen(true);
    };

    const handleSave = (rule: AccessRuleInput) => {
        if (editingIndex === null) {
            onChange([...value, rule]);
        } else {
            onChange(
                value.map((existing, index) =>
                    index === editingIndex ? rule : existing,
                ),
            );
        }
        setEditorOpen(false);
    };

    const handleDelete = (index: number) => {
        onChange(value.filter((_, existingIndex) => existingIndex !== index));
    };

    const handleToggleActive = (index: number, checked: boolean) => {
        onChange(
            value.map((rule, existingIndex) =>
                existingIndex === index ? { ...rule, is_active: checked } : rule,
            ),
        );
    };

    const draft =
        editingIndex !== null ? value[editingIndex] : undefined;

    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-3">
                <div className="flex items-center gap-2">
                    <CardTitle className="text-base">
                        قوانین دسترسی شرطی
                    </CardTitle>
                    {value.length > 0 && (
                        <Badge variant="secondary" className="text-xs">
                            {value.length} قانون
                        </Badge>
                    )}
                </div>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => openEditor(null)}
                    className="gap-1.5"
                >
                    <IconPlus className="size-4" />
                    افزودن قانون
                </Button>
            </CardHeader>
            <CardContent>
                {isLoading ? (
                    <div className="space-y-2">
                        <Skeleton className="h-14 w-full rounded-lg" />
                        <Skeleton className="h-14 w-full rounded-lg" />
                    </div>
                ) : value.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        قانون شرطی تعریف نشده است. با افزودن قانون، می‌توانید
                        دسترسی‌های شرطی بر اساس ویژگی‌های کاربر یا منبع تعیین
                        کنید.
                    </p>
                ) : (
                    <div className="space-y-2">
                        {value.map((rule, index) => {
                            const permission = permissionById.get(
                                rule.permission_id,
                            );
                            const conditionCount =
                                rule.policy?.all?.length ?? 0;

                            return (
                                <div
                                    key={`${rule.permission_id}-${index}`}
                                    className={cn(
                                        "flex flex-wrap items-center gap-3 rounded-lg border p-3",
                                        !rule.is_active && "opacity-60",
                                    )}
                                >
                                    <Badge
                                        variant={
                                            rule.effect === "allow"
                                                ? "default"
                                                : "destructive"
                                        }
                                        className="shrink-0"
                                    >
                                        {EFFECT_LABELS[rule.effect]}
                                    </Badge>
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-medium">
                                            {permission?.display_name ??
                                                `مجوز #${String(rule.permission_id)}`}
                                        </p>
                                        <p className="truncate text-xs text-muted-foreground">
                                            {conditionCount > 0
                                                ? `${conditionCount} شرط فعال`
                                                : "دسترسی نامشروط"}
                                            {rule.priority !== null &&
                                            rule.priority !== 0
                                                ? ` • اولویت ${String(rule.priority)}`
                                                : ""}
                                        </p>
                                    </div>
                                    <Switch
                                        size="sm"
                                        checked={rule.is_active ?? true}
                                        onCheckedChange={(checked) =>
                                            handleToggleActive(index, checked)
                                        }
                                    />
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() => openEditor(index)}
                                    >
                                        ویرایش
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon-sm"
                                        onClick={() => handleDelete(index)}
                                    >
                                        <IconTrash className="size-4" />
                                    </Button>
                                </div>
                            );
                        })}
                    </div>
                )}
            </CardContent>

            <RuleEditorDialog
                open={editorOpen}
                onOpenChange={setEditorOpen}
                onSave={handleSave}
                meta={meta}
                permissions={permissions}
                initialRule={draft}
            />
        </Card>
    );
}

interface RuleEditorDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onSave: (rule: AccessRuleInput) => void;
    meta: RuleBuilderMeta | undefined;
    permissions: Permission[];
    initialRule: AccessRule | AccessRuleInput | undefined;
}

function RuleEditorDialog({
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

interface ConditionRowProps {
    index: number;
    condition: ConditionDraft;
    resourceTypeKey: string;
    resourceAttributes: RuleBuilderMeta["resource_types"][number]["attributes"];
    meta: RuleBuilderMeta | undefined;
    operatorOptions: RuleBuilderMeta["operators"];
    valueSourceOptions: RuleBuilderMeta["value_sources"];
    onChange: (index: number, patch: Partial<ConditionDraft>) => void;
    onRemove: () => void;
}

function ConditionRow({
    index,
    condition,
    resourceTypeKey,
    resourceAttributes,
    meta,
    operatorOptions,
    valueSourceOptions,
    onChange,
    onRemove,
}: ConditionRowProps) {
    const attributeType = attributeTypeFor(meta, resourceTypeKey, condition.attribute);
    const availableOperators =
        resourceAttributes.find((attr) => attr.key === condition.attribute)
            ?.operators ?? [];
    const operatorFiltered =
        availableOperators.length > 0
            ? operatorOptions.filter((operator) =>
                  availableOperators.includes(operator.key),
              )
            : operatorOptions;

    const userAttributes =
        meta?.resource_types.find((rt) => rt.key === "user")?.attributes ?? [];

    const needsValue = conditionNeedsValue(condition.operator);
    const isSetOperator = SET_OPERATORS.has(condition.operator);
    const isActorSource = condition.value_source === "actor";
    const isResourceSource = condition.value_source === "resource";

    return (
        <div className="rounded-lg border p-3 space-y-3">
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <Field>
                    <FieldLabel>ویژگی منبع</FieldLabel>
                    <Select
                        value={condition.attribute}
                        onValueChange={(value) =>
                            onChange(index, {
                                attribute: value ?? "",
                                operator: "",
                            })
                        }
                        itemToStringLabel={(val) =>
                            resourceAttributes.find((attr) => attr.key === val)
                                ?.label ?? ""
                        }
                    >
                        <SelectTrigger className="w-full">
                            <SelectValue placeholder="انتخاب ویژگی..." />
                        </SelectTrigger>
                        <SelectContent>
                            {resourceAttributes.map((attr) => (
                                <SelectItem key={attr.key} value={attr.key}>
                                    {attr.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </Field>

                <Field>
                    <FieldLabel>عملگر</FieldLabel>
                    <Select
                        value={condition.operator}
                        onValueChange={(value) =>
                            onChange(index, { operator: value ?? "" })
                        }
                        itemToStringLabel={(val) =>
                            operatorFiltered.find((op) => op.key === val)?.label ?? ""
                        }
                    >
                        <SelectTrigger className="w-full">
                            <SelectValue placeholder="انتخاب عملگر..." />
                        </SelectTrigger>
                        <SelectContent>
                            {operatorFiltered.map((operator) => (
                                <SelectItem key={operator.key} value={operator.key}>
                                    {operator.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </Field>

                <Field>
                    <FieldLabel>نوع مقدار</FieldLabel>
                    <Select
                        value={condition.value_source}
                        onValueChange={(value) =>
                            onChange(index, {
                                value_source: (value ?? "") as ConditionDraft["value_source"],
                                value: "",
                            })
                        }
                        itemToStringLabel={(val) =>
                            valueSourceOptions.find((vs) => vs.key === val)?.label ?? ""
                        }
                    >
                        <SelectTrigger className="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {valueSourceOptions.map((source) => (
                                <SelectItem key={source.key} value={source.key}>
                                    {source.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </Field>

                <Field>
                    <FieldLabel>مقدار</FieldLabel>
                    {!needsValue ? (
                        <Input value="" disabled placeholder="—" />
                    ) : isActorSource ? (
                        <Select
                            value={condition.value}
                            onValueChange={(value) =>
                                onChange(index, { value: value ?? "" })
                            }
                            itemToStringLabel={(val) =>
                                userAttributes.find((attr) => attr.key === val)
                                    ?.label ?? ""
                            }
                        >
                            <SelectTrigger className="w-full">
                                <SelectValue placeholder="انتخاب ویژگی کاربر..." />
                            </SelectTrigger>
                            <SelectContent>
                                {userAttributes.map((attr) => (
                                    <SelectItem key={attr.key} value={attr.key}>
                                        {attr.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    ) : isResourceSource ? (
                        <Select
                            value={condition.value}
                            onValueChange={(value) =>
                                onChange(index, { value: value ?? "" })
                            }
                            itemToStringLabel={(val) =>
                                resourceAttributes.find((attr) => attr.key === val)
                                    ?.label ?? ""
                            }
                        >
                            <SelectTrigger className="w-full">
                                <SelectValue placeholder="انتخاب ویژگی منبع..." />
                            </SelectTrigger>
                            <SelectContent>
                                {resourceAttributes.map((attr) => (
                                    <SelectItem key={attr.key} value={attr.key}>
                                        {attr.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    ) : (
                        <Input
                            type={
                                !isSetOperator &&
                                attributeType === "integer"
                                    ? "number"
                                    : "text"
                            }
                            dir={attributeType === "integer" ? "ltr" : undefined}
                            value={condition.value}
                            onChange={(e) =>
                                onChange(index, { value: e.target.value })
                            }
                            placeholder={
                                isSetOperator
                                    ? "مقادیر با ویرگول"
                                    : "مقدار"
                            }
                        />
                    )}
                </Field>
            </div>
            <div className="flex justify-end">
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={onRemove}
                    className="text-destructive gap-1.5"
                >
                    <IconTrash className="size-4" />
                    حذف شرط
                </Button>
            </div>
        </div>
    );
}
