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
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { PageHeader } from "@/components/layout";
import { SectionRow } from "@/components/shared/section-row";
import { fetchTempEmployees, syncTempEmployees } from "../api";
import type { TempEmployee } from "../types";
import { TempFileExplorer } from "../components/temp-file-explorer";

const PER_PAGE = 15;

/** Prefer the real Employee's name when present, else fall back to temp fields. */
function displayName(e: TempEmployee): string {
    const emp = e.employee;
    const first = emp?.first_name?.trim();
    const last = emp?.last_name?.trim();
    if (first && last) return `${first} ${last}`;
    if (first) return first;
    if (last) return last;
    return `${e.first_name} ${e.last_name}`.trim();
}

/** Prefer the real Employee's id number when present, else fall back to temp. */
function displayIdNumber(e: TempEmployee): string | null {
    return e.employee?.id_number ?? e.id_number;
}

/** Employment status stored as raw keys — display localized labels. */
const EMPLOYMENT_STATUS_LABELS: Record<string, string> = {
    active: "فعال",
    inactive: "غیرفعال",
    suspended: "تعلیق",
};

/** Employment type stored as raw keys — display localized labels. */
const EMPLOYMENT_TYPE_LABELS: Record<string, string> = {
    official: "رسمی",
    contractual: "قراردادی",
    "project-based": "پروژه‌ای",
};

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
                skipped.length > 0 ? `، ${skipped.length} پوشه بدون الگو` : "";
            toast.success(
                `همگام‌سازی انجام شد: ${created} جدید، ${updated} به‌روزرسانی${skippedNote}`,
            );
        },
        onError: () => toast.error("همگام‌سازی ناموفق بود."),
    });

    return (
        <div className="space-y-6 p-6">
            <PageHeader
                title="مدارک پرسنلی"
                description="مدیریت فایل‌های پرسنلی"
            />

            <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <Card>
                    <CardHeader className="space-y-3">
                        <div className="flex items-center justify-between gap-2">
                            <CardTitle className="text-base">
                                کارمندان
                            </CardTitle>
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
                                onChange={(e) => setSearchInput(e.target.value)}
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
                                <span className="flex items-center justify-between gap-2">
                                    <span className="block truncate">
                                        {displayName(employee)}
                                    </span>
                                    {employee.employee && (
                                        <Badge variant="secondary" className="shrink-0">
                                            رسمی
                                        </Badge>
                                    )}
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
                                    <div className="flex items-center justify-between gap-2">
                                        <CardTitle className="text-base">
                                            {displayName(selected)}
                                        </CardTitle>
                                        {selected.employee && (
                                            <Badge variant="secondary">
                                                از جدول کارمندان
                                            </Badge>
                                        )}
                                    </div>
                                </CardHeader>
                                <CardContent className="divide-y">
                                    <SectionRow
                                        label="شماره پرسنلی"
                                        value={selected.personnel_code}
                                    />
                                    <SectionRow
                                        label="کد ملی"
                                        value={displayIdNumber(selected)}
                                    />
                                    <SectionRow
                                        label="نام"
                                        value={
                                            selected.employee?.first_name ??
                                            selected.first_name
                                        }
                                    />
                                    <SectionRow
                                        label="نام خانوادگی"
                                        value={
                                            selected.employee?.last_name ??
                                            selected.last_name
                                        }
                                    />
                                    {selected.employee?.email && (
                                        <SectionRow
                                            label="ایمیل"
                                            value={selected.employee.email}
                                        />
                                    )}
                                    {selected.employee?.mobile && (
                                        <SectionRow
                                            label="موبایل"
                                            value={selected.employee.mobile}
                                        />
                                    )}
                                    {selected.employee?.employment_type && (
                                        <SectionRow
                                            label="نوع استخدام"
                                            value={
                                                EMPLOYMENT_TYPE_LABELS[
                                                    selected.employee
                                                        .employment_type
                                                ] ??
                                                selected.employee.employment_type
                                            }
                                        />
                                    )}
                                    {selected.employee?.employment_status && (
                                        <SectionRow
                                            label="وضعیت اشتغال"
                                            value={
                                                EMPLOYMENT_STATUS_LABELS[
                                                    selected.employee
                                                        .employment_status
                                                ] ??
                                                selected.employee.employment_status
                                            }
                                        />
                                    )}
                                    {selected.employee &&
                                        selected.employee.roles.length > 0 && (
                                            <div className="flex items-center justify-between gap-4 py-2">
                                                <span className="text-sm font-medium">
                                                    نقش‌ها
                                                </span>
                                                <div className="flex flex-wrap items-center justify-end gap-1.5">
                                                    {selected.employee.roles.map(
                                                        (role) => (
                                                            <Badge
                                                                key={role.id}
                                                                variant={
                                                                    role.active
                                                                        ? "default"
                                                                        : "secondary"
                                                                }
                                                            >
                                                                {role.display_name}
                                                            </Badge>
                                                        ),
                                                    )}
                                                </div>
                                            </div>
                                        )}
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
