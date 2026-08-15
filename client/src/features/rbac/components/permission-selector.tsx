import { memo, useMemo, useState } from "react";
import { IconPlus, IconSearch } from "@tabler/icons-react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import { Label } from "@/components/ui/label";
import { Badge } from "@/components/ui/badge";
import { Input } from "@/components/ui/input";
import { Skeleton } from "@/components/ui/skeleton";
import { usePermissions } from "@/features/rbac/hooks/use-permissions";
import { PermissionAddModal } from "@/features/rbac/components/permission-add-modal";

interface PermissionSelectorProps {
    selectedPermissionIds: number[];
    inheritedPermissionIds?: number[];
    onGroupToggle: (groupId: number, permissionIds: number[]) => void;
    onPermissionToggle: (permissionId: number) => void;
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
    selectedPermissionIds,
    inheritedPermissionIds = [],
    onGroupToggle,
    onPermissionToggle,
}: PermissionSelectorProps) {
    const [modalOpen, setModalOpen] = useState(false);
    const [search, setSearch] = useState("");
    const { data: groups, isLoading } = usePermissions();

    const selectedPermSet = useMemo(
        () => new Set(selectedPermissionIds),
        [selectedPermissionIds],
    );

    const inheritedSet = useMemo(
        () => new Set(inheritedPermissionIds),
        [inheritedPermissionIds],
    );

    const activeGroups = useMemo(
        () =>
            (groups ?? []).filter((group) =>
                (group.permissions ?? []).some(
                    (perm) =>
                        selectedPermSet.has(perm.id) || inheritedSet.has(perm.id),
                ),
            ),
        [groups, selectedPermSet, inheritedSet],
    );

    const normalizedSearch = useMemo(() => search.trim().toLowerCase(), [search]);

    const visibleGroups = useMemo(() => {
        const normalized = normalizedSearch;

        if (!normalized) {
            return activeGroups;
        }

        return activeGroups
            .map((group) => {
                const permissions =
                    group.permissions?.filter(
                        (perm) =>
                            perm.display_name.toLowerCase().includes(normalized) ||
                            perm.name.toLowerCase().includes(normalized),
                    ) ?? [];

                if (
                    group.name.toLowerCase().includes(normalized) ||
                    permissions.some(
                        (perm) =>
                            selectedPermSet.has(perm.id) ||
                            inheritedSet.has(perm.id),
                    )
                ) {
                    return group;
                }

                return null;
            })
            .filter((group): group is NonNullable<typeof group> => group !== null);
    }, [activeGroups, normalizedSearch, selectedPermSet, inheritedSet]);

    const selectedCount = useMemo(
        () =>
            (groups ?? []).reduce(
                (count, group) =>
                    count +
                    (group.permissions?.filter((perm) =>
                        selectedPermSet.has(perm.id),
                    ).length ?? 0),
                0,
            ),
        [groups, selectedPermSet],
    );

    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-3">
                <div className="flex items-center gap-2">
                    <CardTitle className="text-base">مجوزها</CardTitle>
                    {selectedCount > 0 && (
                        <Badge variant="secondary" className="text-xs">
                            {selectedCount} انتخاب شده
                        </Badge>
                    )}
                </div>
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
                ) : activeGroups.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        هیچ مجوزی انتخاب نشده است
                    </p>
                ) : (
                    <div className="space-y-4">
                        {activeGroups.length > 1 && (
                            <div className="relative">
                                <IconSearch className="absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    type="search"
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    placeholder="جستجو در مجوزهای انتخاب شده..."
                                    className="ps-9"
                                />
                            </div>
                        )}
                        {visibleGroups.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                موردی یافت نشد
                            </p>
                        ) : (
                            visibleGroups.map((group) => {
                                const groupPermIds =
                                    group.permissions?.map((p) => p.id) ?? [];
                                const selectableGroupPermIds = groupPermIds.filter(
                                    (id) => !inheritedSet.has(id),
                                );
                                const inheritedInGroup = groupPermIds.filter((id) =>
                                    inheritedSet.has(id),
                                ).length;
                                const allSelected =
                                    selectableGroupPermIds.length > 0 &&
                                    selectableGroupPermIds.every((id) =>
                                        selectedPermSet.has(id),
                                    );
                                const someSelected =
                                    groupPermIds.some((id) =>
                                        selectedPermSet.has(id),
                                    ) && !allSelected;

                                return (
                                    <div key={group.id} className="space-y-2">
                                        <div className="flex items-center gap-2">
                                            <Checkbox
                                                id={`group-${group.id}`}
                                                checked={allSelected}
                                                indeterminate={someSelected}
                                                disabled={
                                                    selectableGroupPermIds.length ===
                                                    0
                                                }
                                                onCheckedChange={() =>
                                                    onGroupToggle(
                                                        group.id,
                                                        groupPermIds,
                                                    )
                                                }
                                            />
                                            <Label
                                                htmlFor={`group-${group.id}`}
                                                className="font-medium cursor-pointer"
                                            >
                                                {group.name}
                                            </Label>
                                            {inheritedInGroup > 0 &&
                                                inheritedInGroup ===
                                                    groupPermIds.length && (
                                                    <Badge
                                                        variant="secondary"
                                                        className="text-xs"
                                                    >
                                                        ارث‌بری کامل
                                                    </Badge>
                                                )}
                                            {inheritedInGroup > 0 &&
                                                inheritedInGroup <
                                                    groupPermIds.length && (
                                                    <Badge
                                                        variant="secondary"
                                                        className="text-xs"
                                                    >
                                                        {inheritedInGroup} ارث‌بری
                                                        شده
                                                    </Badge>
                                                )}
                                        </div>
                                        <div className="flex flex-wrap gap-x-6 gap-y-2 ms-6">
                                            {group.permissions?.map((perm) => {
                                                const isChecked =
                                                    selectedPermSet.has(perm.id);
                                                const isInherited =
                                                    inheritedSet.has(perm.id);

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
                                                                onPermissionToggle(
                                                                    perm.id,
                                                                )
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
                            })
                        )}
                    </div>
                )}
            </CardContent>

            <PermissionAddModal
                open={modalOpen}
                onOpenChange={setModalOpen}
                selectedPermissionIds={selectedPermissionIds}
                onGroupToggle={onGroupToggle}
                onPermissionToggle={onPermissionToggle}
            />
        </Card>
    );
}
