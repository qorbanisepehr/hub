import { useState, useMemo } from "react";
import { useQuery } from "@tanstack/react-query";
import { fetchPermissions } from "@/features/rbac/api";
import { SearchSelectModal } from "@/components/shared/search-select-modal";
import { useDebouncedValue } from "@/hooks/use-debounced-value";
import type { Permission } from "@/features/rbac/types";

type PermissionSearchSelectProps = {
    value?: number | string | null;
    onChange: (item: Permission | null) => void;
    placeholder?: string;
    disabled?: boolean;
    excludeIds?: number[];
    className?: string;
};

export function PermissionSearchSelect({
    value,
    onChange,
    placeholder = "انتخاب مجوز",
    disabled = false,
    excludeIds = [],
    className,
}: PermissionSearchSelectProps) {
    const [search, setSearch] = useState("");
    const debouncedSearch = useDebouncedValue(search, 300);

    const { data: groups, isLoading } = useQuery({
        queryKey: ["permissions"],
        queryFn: async () => {
            const { data } = await fetchPermissions();
            return data.data;
        },
    });

    const allPermissions = useMemo(() => {
        return (groups ?? []).flatMap((g) => g.permissions);
    }, [groups]);

    const filteredPermissions = useMemo(() => {
        const filtered = allPermissions.filter(
            (p) => !excludeIds.includes(p.id),
        );
        if (!debouncedSearch.trim()) return filtered;
        const lower = debouncedSearch.toLowerCase();
        return filtered.filter(
            (p) =>
                p.display_name.toLowerCase().includes(lower) ||
                p.name.toLowerCase().includes(lower),
        );
    }, [allPermissions, excludeIds, debouncedSearch]);

    const groupMap = useMemo(() => {
        const map = new Map<number, string>();
        for (const g of groups ?? []) {
            for (const p of g.permissions) {
                map.set(p.id, g.name);
            }
        }
        return map;
    }, [groups]);

    return (
        <SearchSelectModal
            items={filteredPermissions}
            isLoading={isLoading}
            isSearchPending={search !== debouncedSearch}
            searchQuery={search}
            onSearchChange={setSearch}
            value={value}
            onChange={onChange}
            placeholder={placeholder}
            modalTitle="انتخاب مجوز"
            modalDescription="مجوز مورد نظر را جستجو و انتخاب کنید"
            searchPlaceholder="جستجو بر اساس نام مجوز..."
            emptyText="مجوزی یافت نشد"
            disabled={disabled}
            getItemKey={(p) => p.id}
            getItemLabel={(p) => p.display_name}
            getItemSubLabel={(p) => groupMap.get(p.id) ?? ""}
            className={className}
        />
    );
}
