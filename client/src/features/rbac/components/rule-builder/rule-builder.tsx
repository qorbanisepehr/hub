import { useMemo, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { IconPlus, IconTrash } from "@tabler/icons-react";
import { cn } from "@/lib/utils";

import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Switch } from "@/components/ui/switch";
import { Skeleton } from "@/components/ui/skeleton";
import { usePermissions } from "@/features/rbac/hooks/use-permissions";
import { fetchRuleBuilderMeta } from "@/features/rbac/api";
import { ruleBuilderKeys } from "@/lib/query-keys";
import type {
    AccessRuleInput,
    Permission,
    PermissionGroup,
    RuleBuilderMeta,
} from "@/features/rbac/types";
import { EFFECT_LABELS } from "./types";
import { RuleEditorDialog } from "./rule-editor-dialog";

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
