import { memo, useMemo, useState } from "react";
import { IconChevronDown, IconPlus, IconSearch } from "@tabler/icons-react";
import { cn } from "@/lib/utils";

import { Button } from "@/components/ui/button";
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
        <div className="space-y-2 rounded-lg border p-3">
            {Array.from({ length: 4 }).map((_, i) => (
                <div key={i} className="flex items-center gap-2 py-1">
                    <Skeleton className="size-4 rounded" />
                    <Skeleton className="h-4 w-32" />
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
    const [openGroupIds, setOpenGroupIds] = useState<ReadonlySet<number>>(
        () => new Set(),
    );
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
    const isSearching = normalizedSearch.length > 0;

    /**
     * While searching, matching groups are forced open so results are
     * immediately visible; otherwise the collapse state wins.
     */
    const isGroupOpen = (groupId: number) =>
        isSearching || openGroupIds.has(groupId);

    const toggleGroupOpen = (groupId: number) => {
        setOpenGroupIds((previous) => {
            const next = new Set(previous);
            if (next.has(groupId)) {
                next.delete(groupId);
            } else {
                next.add(groupId);
            }
            return next;
        });
    };

    const visibleGroups = useMemo(() => {
        if (!isSearching) {
            return activeGroups.map((group) => ({
                group,
                permissions: group.permissions ?? [],
            }));
        }

        return activeGroups
            .map((group) => {
                const permissions =
                    group.permissions?.filter(
                        (perm) =>
                            perm.display_name
                                .toLowerCase()
                                .includes(normalizedSearch) ||
                            perm.name.toLowerCase().includes(normalizedSearch),
                    ) ?? [];

                if (group.name.toLowerCase().includes(normalizedSearch)) {
                    return { group, permissions: group.permissions ?? [] };
                }

                if (permissions.length > 0) {
                    return { group, permissions };
                }

                return null;
            })
            .filter(
                (entry): entry is NonNullable<typeof entry> => entry !== null,
            );
    }, [activeGroups, isSearching, normalizedSearch]);

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
        <div className="space-y-3">
            <div className="flex items-center gap-2">
                <div className="relative flex-1">
                    <IconSearch className="absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        type="search"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="جستجو در مجوزها..."
                        className="ps-9"
                    />
                </div>
                {selectedCount > 0 && (
                    <Badge variant="secondary" className="shrink-0 text-xs">
                        {selectedCount} انتخاب شده
                    </Badge>
                )}
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => setModalOpen(true)}
                    className="shrink-0 gap-1.5"
                >
                    <IconPlus className="size-4" />
                    افزودن مجوز
                </Button>
            </div>

            {isLoading ? (
                <SkeletonList />
            ) : activeGroups.length === 0 ? (
                <p className="rounded-lg border border-dashed px-3 py-6 text-center text-sm text-muted-foreground">
                    هیچ مجوزی انتخاب نشده است. با دکمه «افزودن مجوز» دسترسی‌های
                    این نقش را تعیین کنید.
                </p>
            ) : visibleGroups.length === 0 ? (
                <p className="rounded-lg border border-dashed px-3 py-6 text-center text-sm text-muted-foreground">
                    موردی یافت نشد
                </p>
            ) : (
                <div className="max-h-80 divide-y divide-border overflow-y-auto overscroll-contain rounded-lg border">
                    {visibleGroups.map(({ group, permissions }) => {
                        const groupPermIds = permissions.map((p) => p.id);
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
                        const ownSelectedCount = groupPermIds.filter((id) =>
                            selectedPermSet.has(id),
                        ).length;
                        const isOpen = isGroupOpen(group.id);

                        return (
                            <div key={group.id}>
                                <div className="flex items-center gap-2 px-3 py-2 transition-colors hover:bg-accent/50">
                                    <button
                                        type="button"
                                        onClick={() =>
                                            toggleGroupOpen(group.id)
                                        }
                                        aria-expanded={isOpen}
                                        className="flex min-w-0 flex-1 cursor-pointer items-center gap-2 text-start outline-none focus-visible:ring-2 focus-visible:ring-ring/50"
                                    >
                                        <IconChevronDown
                                            className={cn(
                                                "size-4 shrink-0 text-muted-foreground transition-transform",
                                                !isOpen && "-rotate-90",
                                            )}
                                        />
                                        <span className="truncate text-sm font-medium">
                                            {group.name}
                                        </span>
                                        <Badge
                                            variant="secondary"
                                            className="ms-auto shrink-0 text-xs"
                                        >
                                            {ownSelectedCount}/
                                            {String(groupPermIds.length)}
                                        </Badge>
                                        {inheritedInGroup ===
                                            groupPermIds.length &&
                                            inheritedInGroup > 0 && (
                                                <Badge
                                                    variant="secondary"
                                                    className="shrink-0 text-xs"
                                                >
                                                    ارث‌بری کامل
                                                </Badge>
                                            )}
                                        {inheritedInGroup > 0 &&
                                            inheritedInGroup <
                                                groupPermIds.length && (
                                                <Badge
                                                    variant="secondary"
                                                    className="hidden shrink-0 text-xs sm:inline-flex"
                                                >
                                                    {inheritedInGroup} ارث‌بری
                                                    شده
                                                </Badge>
                                            )}
                                    </button>
                                    <Checkbox
                                        checked={allSelected}
                                        indeterminate={someSelected}
                                        disabled={
                                            selectableGroupPermIds.length === 0
                                        }
                                        aria-label={`انتخاب همه مجوزهای ${group.name}`}
                                        onCheckedChange={() =>
                                            onGroupToggle(
                                                group.id,
                                                groupPermIds,
                                            )
                                        }
                                    />
                                </div>
                                {isOpen && (
                                    <div className="grid grid-cols-1 gap-x-4 gap-y-1.5 px-3 pb-3 ps-9 sm:grid-cols-2">
                                        {permissions.map((perm) => {
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
                                                        className={`cursor-pointer truncate text-sm ${
                                                            isInherited
                                                                ? "text-muted-foreground/60"
                                                                : ""
                                                        }`}
                                                    >
                                                        {perm.display_name}
                                                        {isInherited && (
                                                            <span className="ms-1 text-xs text-muted-foreground/60">
                                                                (ارثی)
                                                            </span>
                                                        )}
                                                    </Label>
                                                </div>
                                            );
                                        })}
                                    </div>
                                )}
                            </div>
                        );
                    })}
                </div>
            )}

            <PermissionAddModal
                open={modalOpen}
                onOpenChange={setModalOpen}
                selectedPermissionIds={selectedPermissionIds}
                onGroupToggle={onGroupToggle}
                onPermissionToggle={onPermissionToggle}
            />
        </div>
    );
}
