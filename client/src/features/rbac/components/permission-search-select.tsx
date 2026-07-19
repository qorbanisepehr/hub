import { useState } from "react";
import { useInfiniteQuery } from "@tanstack/react-query";
import { fetchPermissionsPaginated } from "@/features/rbac/api";
import { SearchSelectModal } from "@/components/shared/search-select-modal";
import { useDebouncedValue } from "@/hooks/use-debounced-value";
import { permissionKeys } from "@/lib/query-keys";
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

    const { data, isLoading, fetchNextPage, hasNextPage, isFetchingNextPage } =
        useInfiniteQuery({
            queryKey: permissionKeys.select(debouncedSearch),
            queryFn: async ({ pageParam = 1 }) => {
                const { data } = await fetchPermissionsPaginated({
                    filter: debouncedSearch || undefined,
                    per_page: 20,
                    page: pageParam,
                });
                return data;
            },
            getNextPageParam: (lastPage) =>
                lastPage.meta.current_page < lastPage.meta.last_page
                    ? lastPage.meta.current_page + 1
                    : undefined,
            initialPageParam: 1,
            staleTime: 5 * 60 * 1000,
        });

    const allItems = data?.pages.flatMap((p) => p.data) ?? [];
    const filteredItems = allItems.filter((p) => !excludeIds.includes(p.id));

    return (
        <SearchSelectModal
            items={filteredItems}
            isLoading={isLoading}
            isFetchingNextPage={isFetchingNextPage}
            isSearchPending={search !== debouncedSearch}
            hasNextPage={hasNextPage ?? false}
            fetchNextPage={fetchNextPage}
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
            getItemSubLabel={(p) => p.name}
            className={className}
        />
    );
}
