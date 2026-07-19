import { useState } from "react";
import { useInfiniteQuery } from "@tanstack/react-query";
import { useNavigate } from "@tanstack/react-router";
import { fetchEmployees } from "@/features/employees/api";
import { SearchSelectModal } from "@/components/shared/search-select-modal";
import { useDebouncedValue } from "@/hooks/use-debounced-value";
import { employeeKeys } from "@/lib/query-keys";
import type { Employee } from "@/features/employees/types";

type EmployeeSearchSelectProps = {
    value?: number | string | null;
    onChange: (item: Employee | null) => void;
    placeholder?: string;
    disabled?: boolean;
    excludeIds?: number[];
    className?: string;
};

export function EmployeeSearchSelect({
    value,
    onChange,
    placeholder = "انتخاب کارمند",
    disabled = false,
    excludeIds = [],
    className,
}: EmployeeSearchSelectProps) {
    const navigate = useNavigate();
    const [search, setSearch] = useState("");
    const debouncedSearch = useDebouncedValue(search, 300);

    const { data, isLoading, fetchNextPage, hasNextPage, isFetchingNextPage } =
        useInfiniteQuery({
            queryKey: employeeKeys.select(debouncedSearch),
            queryFn: async ({ pageParam = 1 }) => {
                const { data } = await fetchEmployees({
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
    const filteredItems = allItems.filter((e) => !excludeIds.includes(e.id));

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
                navigate({ to: "/employees/create" })
            }
            placeholder={placeholder}
            modalTitle="انتخاب کارمند"
            modalDescription="کارمند مورد نظر را جستجو و انتخاب کنید"
            searchPlaceholder="جستجو بر اساس نام، نام خانوادگی یا کد پرسنلی..."
            emptyText="کارمندی یافت نشد"
            disabled={disabled}
            getItemKey={(e) => e.id}
            getItemLabel={(e) => `${e.first_name} ${e.last_name}`}
            getItemSubLabel={(e) => `کد پرسنلی: ${e.personnel_code}`}
            className={className}
        />
    );
}
