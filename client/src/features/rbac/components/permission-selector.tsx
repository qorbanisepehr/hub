import { memo, useMemo, useState } from "react";
import { IconPlus, IconTrash } from "@tabler/icons-react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import { Label } from "@/components/ui/label";
import { Badge } from "@/components/ui/badge";
import { Skeleton } from "@/components/ui/skeleton";
import { usePermissions } from "@/features/rbac/hooks/use-permissions";
import { PermissionAddModal } from "@/features/rbac/components/permission-add-modal";

interface PermissionSelectorProps {
    selectedGroupIds: number[];
    selectedPermissionIds: number[];
    inheritedPermissionIds?: number[];
    onGroupToggle: (groupId: number, permissionIds: number[]) => void;
    onGroupRemove: (groupId: number, permissionIds: number[]) => void;
    onPermissionToggle: (permissionId: number, groupId: number) => void;
}

const SkeletonList = memo(function SkeletonList() {
    return (
        <div className="space-y-4">
            {Array.from({ length: 2 }).map((_, i) => (
                <div key={i} className="space-y-2">
                    <Skeleton className="h-4 w-24 rounded-lg" />
                    <div className="flex flex-wrap gap-2">
                        <Skeleton className="h-6 w-20 rounded-lg" />
                        <Skeleton className="h-6 w-16 rounded-lg" />
                    </div>
                </div>
            ))}
        </div>
    );
});

export function PermissionSelector({
    selectedGroupIds,
    selectedPermissionIds,
    inheritedPermissionIds = [],
    onGroupToggle,
    onGroupRemove,
    onPermissionToggle,
}: PermissionSelectorProps) {
    const [modalOpen, setModalOpen] = useState(false);
    const { data: groups, isLoading } = usePermissions();

    const selectedPermSet = useMemo(
        () => new Set(selectedPermissionIds),
        [selectedPermissionIds],
    );

    const inheritedSet = useMemo(
        () => new Set(inheritedPermissionIds),
        [inheritedPermissionIds],
    );

    const selectedGroupSet = useMemo(
        () => new Set(selectedGroupIds),
        [selectedGroupIds],
    );

    const permNameMap = useMemo(() => {
        const map = new Map<number, string>();
        for (const group of groups ?? []) {
            for (const perm of group.permissions ?? []) {
                map.set(perm.id, perm.display_name);
            }
        }
        return map;
    }, [groups]);

    const selectedGroups = useMemo(
        () => groups?.filter((g) => selectedGroupSet.has(g.id)) ?? [],
        [groups, selectedGroupSet],
    );

    const selectedGroupPermIds = useMemo(
        () => new Set(selectedGroups.flatMap((g) => g.permissions?.map((p) => p.id) ?? [])),
        [selectedGroups],
    );

    const orphanPerms = useMemo(
        () => selectedPermissionIds.filter((id) => !selectedGroupPermIds.has(id)),
        [selectedPermissionIds, selectedGroupPermIds],
    );

    const hasSelections =
        selectedGroupIds.length > 0 || selectedPermissionIds.length > 0;

    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-3">
                <CardTitle className="text-base">مجوزها</CardTitle>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => setModalOpen(true)}
                    className="gap-1.5"
                >
                    <IconPlus className="size-4" />
                    افزودن مجوز
                </Button>
            </CardHeader>
            <CardContent>
                {isLoading ? (
                    <SkeletonList />
                ) : !hasSelections && inheritedSet.size === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        هیچ مجوزی انتخاب نشده است
                    </p>
                ) : (
                    <div className="space-y-4">
                        {selectedGroups.map((group) => {
                            const groupPermIds =
                                group.permissions?.map((p) => p.id) ?? [];
                            const inheritedInGroup = groupPermIds.filter(
                                (id) => inheritedSet.has(id),
                            ).length;
                            const allSelected = groupPermIds.length > 0 &&
                                groupPermIds.every((id) => selectedPermSet.has(id));
                            const someSelected = groupPermIds.some((id) =>
                                selectedPermSet.has(id),
                            ) && !allSelected;

                            return (
                                <div key={group.id} className="space-y-2">
                                    <div className="flex items-center gap-2">
                                        <Checkbox
                                            id={`group-${group.id}`}
                                            checked={allSelected}
                                            indeterminate={someSelected}
                                            onCheckedChange={() =>
                                                onGroupToggle(group.id, groupPermIds)
                                            }
                                        />
                                        <Label
                                            htmlFor={`group-${group.id}`}
                                            className="font-medium cursor-pointer"
                                        >
                                            {group.name}
                                        </Label>
                                        {inheritedInGroup > 0 &&
                                            inheritedInGroup === groupPermIds.length && (
                                                <Badge
                                                    variant="secondary"
                                                    className="text-xs"
                                                >
                                                    ارث‌بری کامل
                                                </Badge>
                                            )}
                                        {inheritedInGroup > 0 &&
                                            inheritedInGroup < groupPermIds.length && (
                                                <Badge
                                                    variant="secondary"
                                                    className="text-xs"
                                                >
                                                    {inheritedInGroup} ارث‌بری شده
                                                </Badge>
                                            )}
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => onGroupRemove(group.id, groupPermIds)}
                                            className="ms-auto size-7 p-0 text-muted-foreground hover:text-destructive"
                                        >
                                            <IconTrash className="size-4" />
                                        </Button>
                                    </div>
                                    <div className="flex flex-wrap gap-x-6 gap-y-2 ms-6">
                                        {group.permissions?.map((perm) => {
                                            const isChecked = selectedPermSet.has(perm.id);
                                            const isInherited = inheritedSet.has(perm.id);

                                            return (
                                                <div
                                                    key={perm.id}
                                                    className="flex items-center gap-1.5"
                                                >
                                                    <Checkbox
                                                        id={`perm-${perm.id}`}
                                                        checked={isChecked}
                                                        disabled={isInherited}
                                                        onCheckedChange={() =>
                                                            onPermissionToggle(perm.id, group.id)
                                                        }
                                                    />
                                                    <Label
                                                        htmlFor={`perm-${perm.id}`}
                                                        className={`text-sm cursor-pointer ${
                                                            isInherited
                                                                ? "text-muted-foreground/60"
                                                                : ""
                                                        }`}
                                                    >
                                                        {perm.display_name}
                                                        {isInherited && (
                                                            <span className="text-xs text-muted-foreground/60 ms-1">
                                                                (ارثی)
                                                            </span>
                                                        )}
                                                    </Label>
                                                </div>
                                            );
                                        })}
                                    </div>
                                </div>
                            );
                        })}

                        {orphanPerms.length > 0 && (
                            <div className="space-y-2">
                                <Label className="font-medium">
                                    مجوزهای جداگانه
                                </Label>
                                <div className="flex flex-wrap gap-x-6 gap-y-2 ms-6">
                                    {orphanPerms.map((permId) => {
                                        const isInherited = inheritedSet.has(permId);
                                        const permName = permNameMap.get(permId);
                                        if (!permName) return null;

                                        return (
                                            <div
                                                key={permId}
                                                className="flex items-center gap-1.5"
                                            >
                                                <Checkbox
                                                    id={`perm-${permId}`}
                                                    checked={true}
                                                    disabled={isInherited}
                                                    onCheckedChange={() => {
                                                        if (!isInherited) {
                                                            onPermissionToggle(permId, 0);
                                                        }
                                                    }}
                                                />
                                                <Label
                                                    htmlFor={`perm-${permId}`}
                                                    className={`text-sm cursor-pointer ${
                                                        isInherited
                                                            ? "text-muted-foreground/60"
                                                            : ""
                                                    }`}
                                                >
                                                    {permName}
                                                    {isInherited && (
                                                        <span className="text-xs text-muted-foreground/60 ms-1">
                                                            (ارثی)
                                                        </span>
                                                    )}
                                                </Label>
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                        )}
                    </div>
                )}
            </CardContent>

            <PermissionAddModal
                open={modalOpen}
                onOpenChange={setModalOpen}
                selectedPermissionIds={selectedPermissionIds}
                onGroupToggle={onGroupToggle}
                onGroupRemove={onGroupRemove}
                onPermissionToggle={onPermissionToggle}
            />
        </Card>
    );
}
