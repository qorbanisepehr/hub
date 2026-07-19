import { memo, useEffect, useMemo, useRef, useState } from "react";
import {
    IconCircleCheck,
    IconLoader2,
    IconMinus,
    IconSearch,
} from "@tabler/icons-react";
import { useInfiniteQuery } from "@tanstack/react-query";
import { Input } from "@/components/ui/input";
import { Skeleton } from "@/components/ui/skeleton";
import { Badge } from "@/components/ui/badge";
import { Tabs, TabsList, TabsTrigger, TabsContent } from "@/components/ui/tabs";
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from "@/components/ui/dialog";
import { usePermissions } from "@/features/rbac/hooks/use-permissions";
import { useDebouncedValue } from "@/hooks/use-debounced-value";
import { fetchPermissionsPaginated } from "@/features/rbac/api";
import { permissionKeys } from "@/lib/query-keys";

interface PermissionAddModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    selectedPermissionIds: number[];
    onGroupToggle: (groupId: number, permissionIds: number[]) => void;
    onGroupRemove: (groupId: number, permissionIds: number[]) => void;
    onPermissionToggle: (permissionId: number, groupId: number) => void;
}

const PAGE_SIZE = 20;

const GroupsSkeleton = memo(function GroupsSkeleton() {
    return (
        <div className="space-y-2 p-1">
            {Array.from({ length: 5 }).map((_, i) => (
                <div
                    key={i}
                    className="flex items-center gap-3 rounded-lg px-2 py-2"
                >
                    <Skeleton className="size-4 shrink-0 rounded" />
                    <div className="flex-1 space-y-1.5">
                        <Skeleton className="h-3.5 w-24" />
                        <Skeleton className="h-3 w-16" />
                    </div>
                </div>
            ))}
        </div>
    );
});

const PermissionsSkeleton = memo(function PermissionsSkeleton() {
    return (
        <div className="space-y-1 p-1">
            {Array.from({ length: 5 }).map((_, i) => (
                <div
                    key={i}
                    className="flex items-center gap-3 rounded-lg px-2 py-2"
                >
                    <Skeleton className="size-4 shrink-0 rounded" />
                    <div className="flex-1 space-y-1.5">
                        <Skeleton className="h-3.5 w-32" />
                        <Skeleton className="h-3 w-20" />
                    </div>
                </div>
            ))}
        </div>
    );
});

function GroupsTab({
    selectedPermissionIds,
    onGroupToggle,
    onGroupRemove,
}: {
    selectedPermissionIds: number[];
    onGroupToggle: (groupId: number, permissionIds: number[]) => void;
    onGroupRemove: (groupId: number, permissionIds: number[]) => void;
}) {
    const { data: groups, isLoading } = usePermissions();
    const [search, setSearch] = useState("");

    const filtered = useMemo(() => {
        if (!groups) return [];
        if (!search) return groups;
        const q = search.toLowerCase();
        return groups.filter(
            (g) =>
                g.name.toLowerCase().includes(q) ||
                g.permissions?.some((p) =>
                    p.display_name.toLowerCase().includes(q),
                ),
        );
    }, [groups, search]);

    const selectedPermSet = useMemo(
        () => new Set(selectedPermissionIds),
        [selectedPermissionIds],
    );

    if (isLoading) {
        return <GroupsSkeleton />;
    }

    return (
        <div className="space-y-2">
            <div className="relative">
                <IconSearch className="absolute start-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    placeholder="جستجوی گروه..."
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    className="h-9 ps-8"
                    autoFocus
                />
            </div>
            <div className="max-h-72 overflow-y-auto overscroll-contain space-y-1 p-1">
                {filtered.length === 0 ? (
                    <div className="flex flex-col items-center justify-center py-8 text-muted-foreground">
                        <IconSearch className="mb-2 size-8 opacity-40" />
                        <p className="text-sm">گروه‌ای یافت نشد</p>
                    </div>
                ) : (
                    filtered.map((group) => {
                        const groupPermIds =
                            group.permissions?.map((p) => p.id) ?? [];
                        const selectedCount = groupPermIds.filter((id) =>
                            selectedPermSet.has(id),
                        ).length;
                        const isAllSelected =
                            groupPermIds.length > 0 &&
                            selectedCount === groupPermIds.length;
                        const isSomeSelected =
                            selectedCount > 0 && !isAllSelected;
                        const permCount = groupPermIds.length;
                        return (
                            <button
                                key={group.id}
                                type="button"
                                onClick={() =>
                                    isAllSelected
                                        ? onGroupRemove(group.id, groupPermIds)
                                        : onGroupToggle(group.id, groupPermIds)
                                }
                                className={`flex w-full items-center gap-3 rounded-lg px-2 py-2 text-start text-sm transition-colors outline-none hover:bg-accent hover:text-accent-foreground ${
                                    isAllSelected || isSomeSelected
                                        ? "bg-accent text-accent-foreground"
                                        : ""
                                }`}
                            >
                                <div className="flex min-w-0 flex-1 flex-col">
                                    <span className="truncate font-medium">
                                        {group.name}
                                    </span>
                                    <span className="truncate text-xs text-muted-foreground">
                                        {isSomeSelected
                                            ? `${selectedCount}/${permCount} مجوز`
                                            : `${permCount} مجوز`}
                                    </span>
                                </div>
                                {isAllSelected && (
                                    <IconCircleCheck className="size-4 shrink-0 text-primary" />
                                )}
                                {isSomeSelected && (
                                    <IconMinus className="size-4 shrink-0 text-primary" />
                                )}
                            </button>
                        );
                    })
                )}
            </div>
        </div>
    );
}

function PermissionsTab({
    selectedPermissionIds,
    onPermissionToggle,
}: {
    selectedPermissionIds: number[];
    onPermissionToggle: (permissionId: number, groupId: number) => void;
}) {
    const [search, setSearch] = useState("");
    const debouncedSearch = useDebouncedValue(search, 300);
    const scrollRef = useRef<HTMLDivElement | null>(null);

    const { data, isLoading, isFetchingNextPage, hasNextPage, fetchNextPage } =
        useInfiniteQuery({
            queryKey: permissionKeys.search(debouncedSearch),
            queryFn: async ({ pageParam = 1 }) => {
                const res = await fetchPermissionsPaginated({
                    filter: debouncedSearch || undefined,
                    page: pageParam,
                    per_page: PAGE_SIZE,
                });
                return res.data;
            },
            getNextPageParam: (lastPage) => {
                if (lastPage.meta.current_page < lastPage.meta.last_page) {
                    return lastPage.meta.current_page + 1;
                }
                return undefined;
            },
            initialPageParam: 1,
        });

    const allItems = useMemo(
        () => data?.pages?.flatMap((p) => p.data) ?? [],
        [data],
    );

    const selectedSet = useMemo(
        () => new Set(selectedPermissionIds),
        [selectedPermissionIds],
    );

    useEffect(() => {
        const el = scrollRef.current;
        if (!el) return;

        let rafId = 0;
        const onScroll = () => {
            if (rafId) return;
            rafId = requestAnimationFrame(() => {
                rafId = 0;
                if (
                    hasNextPage &&
                    !isFetchingNextPage &&
                    el.scrollTop + el.clientHeight >= el.scrollHeight - 60
                ) {
                    fetchNextPage();
                }
            });
        };

        el.addEventListener("scroll", onScroll, { passive: true });
        return () => {
            el.removeEventListener("scroll", onScroll);
            if (rafId) cancelAnimationFrame(rafId);
        };
    }, [hasNextPage, isFetchingNextPage, fetchNextPage]);

    if (isLoading) {
        return <PermissionsSkeleton />;
    }

    return (
        <div className="space-y-2">
            <div className="relative">
                <IconSearch className="absolute start-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    placeholder="جستجوی مجوز..."
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    className="h-9 ps-8"
                    autoFocus
                />
            </div>
            <div
                ref={scrollRef}
                className="max-h-72 overflow-y-auto overscroll-contain space-y-1 p-1"
            >
                {allItems.length === 0 ? (
                    <div className="flex flex-col items-center justify-center py-8 text-muted-foreground">
                        <IconSearch className="mb-2 size-8 opacity-40" />
                        <p className="text-sm">
                            {debouncedSearch
                                ? "نتیجه‌ای یافت نشد"
                                : "مجوزی یافت نشد"}
                        </p>
                    </div>
                ) : (
                    allItems.map((perm) => {
                        const isChecked = selectedSet.has(perm.id);
                        return (
                            <button
                                key={perm.id}
                                type="button"
                                onClick={() =>
                                    onPermissionToggle(perm.id, perm.group_id)
                                }
                                className={`flex w-full items-center gap-3 rounded-lg px-2 py-2 text-start text-sm transition-colors outline-none hover:bg-accent hover:text-accent-foreground ${
                                    isChecked
                                        ? "bg-accent text-accent-foreground"
                                        : ""
                                }`}
                            >
                                <div className="flex min-w-0 flex-1 flex-col">
                                    <span className="truncate font-medium">
                                        {perm.display_name}
                                    </span>
                                    <span className="truncate text-xs text-muted-foreground">
                                        {perm.name}
                                    </span>
                                </div>
                                {isChecked && (
                                    <IconCircleCheck className="size-4 shrink-0 text-primary" />
                                )}
                            </button>
                        );
                    })
                )}

                {isFetchingNextPage && (
                    <div className="flex items-center justify-center gap-2 py-2.5">
                        <IconLoader2 className="size-3.5 animate-spin text-muted-foreground" />
                        <span className="text-xs text-muted-foreground">
                            بارگذاری...
                        </span>
                    </div>
                )}

                {!hasNextPage && allItems.length > 0 && (
                    <p className="py-2 text-center text-xs text-muted-foreground">
                        {allItems.length} مورد بارگذاری شد
                    </p>
                )}
            </div>
        </div>
    );
}

export function PermissionAddModal({
    open,
    onOpenChange,
    selectedPermissionIds,
    onGroupToggle,
    onGroupRemove,
    onPermissionToggle,
}: PermissionAddModalProps) {
    const [tab, setTab] = useState("groups");

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md gap-0 p-0 overflow-hidden">
                <DialogHeader className="px-4 pt-4 pb-2">
                    <DialogTitle className="flex items-center gap-2">
                        افزودن مجوز
                    </DialogTitle>
                    <DialogDescription>
                        گروه یا مجوز مورد نظر را انتخاب کنید{" "}
                        {selectedPermissionIds.length > 0 && (
                            <Badge variant="secondary" className="text-xs">
                                {selectedPermissionIds.length}
                            </Badge>
                        )}
                    </DialogDescription>
                </DialogHeader>

                <Tabs
                    value={tab}
                    onValueChange={(v) => setTab(v as string)}
                    className="px-4"
                >
                    <TabsList className="w-full">
                        <TabsTrigger value="groups" className="flex-1">
                            گروه‌ها
                        </TabsTrigger>
                        <TabsTrigger value="permissions" className="flex-1">
                            مجوزها
                        </TabsTrigger>
                    </TabsList>

                    <div className="py-2">
                        <TabsContent value="groups">
                            <GroupsTab
                                selectedPermissionIds={selectedPermissionIds}
                                onGroupToggle={onGroupToggle}
                                onGroupRemove={onGroupRemove}
                            />
                        </TabsContent>
                        <TabsContent value="permissions">
                            <PermissionsTab
                                selectedPermissionIds={selectedPermissionIds}
                                onPermissionToggle={onPermissionToggle}
                            />
                        </TabsContent>
                    </div>
                </Tabs>
            </DialogContent>
        </Dialog>
    );
}
