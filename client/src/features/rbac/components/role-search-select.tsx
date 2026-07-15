import { useState } from "react";
import { useInfiniteQuery } from "@tanstack/react-query";
import { useNavigate } from "@tanstack/react-router";
import { fetchRoles } from "@/features/rbac/api";
import { SearchSelectModal } from "@/components/shared/search-select-modal";
import { useDebouncedValue } from "@/hooks/use-debounced-value";
import type { Role } from "@/features/rbac/types";

type RoleSearchSelectProps = {
    value?: number | string | null;
    onChange: (item: Role | null) => void;
    placeholder?: string;
    disabled?: boolean;
    excludeIds?: number[];
    className?: string;
};

export function RoleSearchSelect({
    value,
    onChange,
    placeholder = "انتخاب نقش",
    disabled = false,
    excludeIds = [],
    className,
}: RoleSearchSelectProps) {
    const navigate = useNavigate();
    const [search, setSearch] = useState("");
    const debouncedSearch = useDebouncedValue(search, 300);

    const { data, isLoading, fetchNextPage, hasNextPage, isFetchingNextPage } =
        useInfiniteQuery({
            queryKey: ["roles", "select", debouncedSearch],
            queryFn: async ({ pageParam = 1 }) => {
                const { data } = await fetchRoles({
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
    const filteredItems = allItems.filter((r) => !excludeIds.includes(r.id));

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
            onCreateNew={() =>
                navigate({ to: "/roles/create" })
            }
            placeholder={placeholder}
            modalTitle="انتخاب نقش"
            modalDescription="نقش مورد نظر را جستجو و انتخاب کنید"
            searchPlaceholder="جستجو بر اساس نام نقش..."
            emptyText="نقشی یافت نشد"
            disabled={disabled}
            getItemKey={(r) => r.id}
            getItemLabel={(r) => r.display_name}
            getItemSubLabel={(r) => r.description ?? ""}
            className={className}
        />
    );
}
