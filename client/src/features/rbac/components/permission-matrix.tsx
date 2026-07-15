import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import { Label } from "@/components/ui/label";
import { Skeleton } from "@/components/ui/skeleton";
import { Badge } from "@/components/ui/badge";
import { usePermissions } from "@/features/rbac/hooks/use-permissions";

interface PermissionMatrixProps {
    selectedGroupIds: number[];
    selectedPermissionIds: number[];
    inheritedPermissionIds?: number[];
    onGroupToggle: (groupId: number, permissionIds: number[]) => void;
    onPermissionToggle: (permissionId: number, groupId: number) => void;
}

export function PermissionMatrix({
    selectedGroupIds,
    selectedPermissionIds,
    inheritedPermissionIds = [],
    onGroupToggle,
    onPermissionToggle,
}: PermissionMatrixProps) {
    const { data, isLoading } = usePermissions();

    if (isLoading) {
        return (
            <div className="space-y-4">
                {Array.from({ length: 3 }).map((_, i) => (
                    <Skeleton key={i} className="h-24 w-full" />
                ))}
            </div>
        );
    }

    const groups = data ?? [];
    const inheritedSet = new Set(inheritedPermissionIds);

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">مجوزها</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
                {groups.map((group) => {
                    const isGroupSelected = selectedGroupIds.includes(group.id);
                    const groupPermIds = group.permissions?.map((p) => p.id) ?? [];
                    const inheritedInGroup = groupPermIds.filter((id) =>
                        inheritedSet.has(id),
                    ).length;

                    return (
                        <div key={group.id} className="space-y-2">
                            <div className="flex items-center gap-2">
                                <Checkbox
                                    id={`group-${group.id}`}
                                    checked={isGroupSelected}
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
                                        <Badge variant="secondary" className="text-xs">
                                            ارث‌بری کامل
                                        </Badge>
                                    )}
                                {inheritedInGroup > 0 &&
                                    inheritedInGroup < groupPermIds.length && (
                                        <Badge variant="secondary" className="text-xs">
                                            {inheritedInGroup} ارث‌بری شده
                                        </Badge>
                                    )}
                            </div>
                            <div className="flex flex-wrap gap-x-6 gap-y-2 mr-6">
                                {group.permissions?.map((perm) => {
                                    const isChecked =
                                        selectedPermissionIds.includes(perm.id);
                                    const isInherited = inheritedSet.has(perm.id);

                                    return (
                                        <div
                                            key={perm.id}
                                            className="flex items-center gap-1.5"
                                        >
                                            <Checkbox
                                                id={`perm-${perm.id}`}
                                                checked={isChecked}
                                                onCheckedChange={() =>
                                                    onPermissionToggle(
                                                        perm.id,
                                                        group.id,
                                                    )
                                                }
                                            />
                                            <Label
                                                htmlFor={`perm-${perm.id}`}
                                                className="text-sm text-muted-foreground cursor-pointer"
                                            >
                                                {perm.display_name}
                                                {isInherited && (
                                                    <span className="text-xs text-muted-foreground/60 mr-1">
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
            </CardContent>
        </Card>
    );
}
