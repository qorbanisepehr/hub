import { useEffect, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import {
    IconChevronLeft,
    IconChevronRight,
    IconLoader2,
    IconRefresh,
    IconSearch,
} from "@tabler/icons-react";
import { toast } from "sonner";
import { cn } from "@/lib/utils";

import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import { PageHeader } from "@/components/layout";
import { SectionRow } from "@/components/shared/section-row";
import {
    fetchTempEmployees,
    syncTempEmployees,
} from "../api";
import type { TempEmployee } from "../types";
import { TempFileExplorer } from "../components/temp-file-explorer";

const PER_PAGE = 15;

/**
 * Temporary tool: browse temp-employee records and explore the on-disk
 * folder keyed by each personnel code (tree/table/card modes + lightbox).
 */
export function TempEmployeesPage() {
    const queryClient = useQueryClient();
    const [searchInput, setSearchInput] = useState("");
    const [search, setSearch] = useState("");
    const [page, setPage] = useState(1);
    const [selectedCode, setSelectedCode] = useState<string | null>(null);

    // Debounce the search box into the actual query param.
    useEffect(() => {
        const timer = setTimeout(() => {
            setSearch(searchInput.trim());
            setPage(1);
        }, 300);

        return () => clearTimeout(timer);
    }, [searchInput]);

    const { data, isLoading } = useQuery({
        queryKey: ["temp-employees", { search, page }],
        queryFn: async () => {
            const { data } = await fetchTempEmployees({
                search: search || undefined,
                page,
                per_page: PER_PAGE,
            });
            return { employees: data.data, meta: data.meta };
        },
    });

    const employees = data?.employees ?? [];
    const meta = data?.meta;

    const selected: TempEmployee | undefined =
        employees.find((e) => e.personnel_code === selectedCode) ??
        (page === 1 && search === "" ? employees[0] : undefined);

    const syncMutation = useMutation({
        mutationFn: syncTempEmployees,
        onSuccess: (response) => {
            queryClient.invalidateQueries({ queryKey: ["temp-employees"] });

            const { created, updated, skipped } = response.data.data;
            const skippedNote =
                skipped.length > 0
                    ? `، ${skipped.length} پوشه بدون الگو`
                    : "";
            toast.success(
                `همگام‌سازی انجام شد: ${created} جدید، ${updated} به‌روزرسانی${skippedNote}`,
            );
        },
        onError: () => toast.error("همگام‌سازی ناموفق بود."),
    });

    return (
        <div className="space-y-6">
            <PageHeader
                title="پرونده‌ها"
                description="مدیریت فایل‌های پرسنلی"
            />

            <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <Card>
                    <CardHeader className="space-y-3">
                        <div className="flex items-center justify-between gap-2">
                            <CardTitle className="text-base">کارمندان</CardTitle>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                disabled={syncMutation.isPending}
                                onClick={() => syncMutation.mutate()}
                            >
                                {syncMutation.isPending ? (
                                    <IconLoader2 className="size-3.5 ms-1 animate-spin" />
                                ) : (
                                    <IconRefresh className="size-3.5 ms-1" />
                                )}
                                همگام‌سازی با پوشه‌ها
                            </Button>
                        </div>

                        {/* Debounced as-you-type; Enter or the icon submits immediately. */}
                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                setSearch(searchInput.trim());
                                setPage(1);
                            }}
                            className="relative"
                        >
                            <IconSearch className="absolute top-1/2 size-4 -translate-y-1/2 text-muted-foreground start-2.5" />
                            <Input
                                value={searchInput}
                                onChange={(e) =>
                                    setSearchInput(e.target.value)
                                }
                                placeholder="جستجو: کد پرسنلی، کد ملی، نام…"
                                className="ps-8 pe-9"
                            />
                            <Button
                                type="submit"
                                variant="ghost"
                                size="icon-sm"
                                title="جستجو"
                                className="absolute top-1/2 -translate-y-1/2 end-1"
                            >
                                <IconSearch className="size-4" />
                            </Button>
                        </form>
                    </CardHeader>

                    <CardContent className="space-y-1">
                        {isLoading && (
                            <p className="py-4 text-center text-sm text-muted-foreground">
                                در حال بارگذاری…
                            </p>
                        )}
                        {(employees ?? []).map((employee) => (
                            <button
                                key={employee.id}
                                type="button"
                                onClick={() =>
                                    setSelectedCode(employee.personnel_code)
                                }
                                className={cn(
                                    "w-full rounded-md px-3 py-2 text-start text-sm hover:bg-muted",
                                    selected?.id === employee.id &&
                                        "bg-muted font-medium",
                                )}
                            >
                                <span className="block">
                                    {employee.first_name}{" "}
                                    {employee.last_name}
                                </span>
                                <span
                                    className="text-xs text-muted-foreground"
                                    dir="ltr"
                                >
                                    {employee.personnel_code}
                                </span>
                            </button>
                        ))}
                        {!isLoading && employees.length === 0 && (
                            <p className="py-4 text-center text-sm text-muted-foreground">
                                رکوردی یافت نشد.
                            </p>
                        )}

                        {meta && meta.last_page > 1 && (
                            <div className="flex items-center justify-between pt-3">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    disabled={meta.current_page <= 1}
                                    onClick={() =>
                                        setPage(meta.current_page - 1)
                                    }
                                >
                                    <IconChevronRight className="size-3.5" />
                                    قبلی
                                </Button>
                                <span
                                    className="text-xs text-muted-foreground"
                                    dir="ltr"
                                >
                                    {meta.current_page} / {meta.last_page}
                                </span>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    disabled={
                                        meta.current_page >= meta.last_page
                                    }
                                    onClick={() =>
                                        setPage(meta.current_page + 1)
                                    }
                                >
                                    بعدی
                                    <IconChevronLeft className="size-3.5" />
                                </Button>
                            </div>
                        )}
                    </CardContent>
                </Card>

                <div className="space-y-4 lg:col-span-2">
                    {selected ? (
                        <>
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">
                                        {selected.first_name}{" "}
                                        {selected.last_name}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="divide-y">
                                    <SectionRow
                                        label="شماره پرسنلی"
                                        value={selected.personnel_code}
                                    />
                                    <SectionRow
                                        label="کد ملی"
                                        value={selected.id_number}
                                    />
                                    <SectionRow
                                        label="نام"
                                        value={selected.first_name}
                                    />
                                    <SectionRow
                                        label="نام خانوادگی"
                                        value={selected.last_name}
                                    />
                                </CardContent>
                            </Card>

                            <Card>
                                <CardContent className="pt-6">
                                    <TempFileExplorer employee={selected} />
                                </CardContent>
                            </Card>
                        </>
                    ) : (
                        !isLoading &&
                        employees.length > 0 && (
                            <p className="text-sm text-muted-foreground">
                                یک کارمند را از فهرست انتخاب کنید.
                            </p>
                        )
                    )}
                </div>
            </div>
        </div>
    );
}
