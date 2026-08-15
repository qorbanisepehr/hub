import { useState } from "react";
import { useInfiniteQuery } from "@tanstack/react-query";
import { useNavigate } from "@tanstack/react-router";
import { fetchUsers } from "@/features/rbac/api";
import { SearchSelectModal } from "@/components/shared/search-select-modal";
import { useDebouncedValue } from "@/hooks/use-debounced-value";
import { userKeys } from "@/lib/query-keys";
import { PAGINATION, DEBOUNCE } from "@/lib/constants";
import { getUserDisplayName } from "@/lib/user-display";
import type { UserListItem } from "@/features/rbac/types";

type UserSearchSelectProps = {
    value?: number | string | null;
    onChange: (item: UserListItem | null) => void;
    placeholder?: string;
    disabled?: boolean;
    excludeIds?: number[];
    hasEmployee?: boolean;
    className?: string;
};

export function UserSearchSelect({
    value,
    onChange,
    placeholder = "انتخاب کاربر",
    disabled = false,
    excludeIds = [],
    hasEmployee,
    className,
}: UserSearchSelectProps) {
    const navigate = useNavigate();
    const [search, setSearch] = useState("");
    const debouncedSearch = useDebouncedValue(search, DEBOUNCE.SEARCH);

    const { data, isLoading, fetchNextPage, hasNextPage, isFetchingNextPage } =
        useInfiniteQuery({
            queryKey: userKeys.select(debouncedSearch, hasEmployee),
            queryFn: async ({ pageParam = 1 }) => {
                const { data } = await fetchUsers({
                    filter: debouncedSearch || undefined,
                    has_employee: hasEmployee,
                    per_page: PAGINATION.SEARCH_PAGE_SIZE,
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
    const filteredItems = allItems.filter((u) => !excludeIds.includes(u.id));

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
                navigate({ to: "/users/create" })
            }
            placeholder={placeholder}
            modalTitle="انتخاب کاربر"
            modalDescription="کاربر مورد نظر را جستجو و انتخاب کنید"
            searchPlaceholder="جستجو بر اساس نام یا ایمیل..."
            emptyText="کاربری یافت نشد"
            disabled={disabled}
            getItemKey={(u) => u.id}
            getItemLabel={(u) => getUserDisplayName(u)}
            getItemSubLabel={(u) => u.email}
            getItemDisabled={(u) => !u.is_active}
            className={className}
        />
    );
}
