import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { cn } from "@/lib/utils";

import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import { PageHeader } from "@/components/layout";
import { SectionRow } from "@/components/shared/section-row";
import { fetchTempEmployees } from "../api";
import type { TempEmployee } from "../types";
import { TempFileExplorer } from "../components/temp-file-explorer";

/**
 * Temporary tool: browse temp-employee records and explore the on-disk
 * folder keyed by each personnel code (tree/table/card modes + lightbox).
 */
export function TempEmployeesPage() {
    const [selectedCode, setSelectedCode] = useState<string | null>(null);

    const { data: employees, isLoading } = useQuery({
        queryKey: ["temp-employees"],
        queryFn: async () => {
            const { data } = await fetchTempEmployees();
            return data.data;
        },
    });

    const selected: TempEmployee | undefined =
        employees?.find((e) => e.personnel_code === selectedCode) ??
        employees?.[0];

    return (
        <div className="space-y-6">
            <PageHeader
                title="پرونده‌های موقت"
                description="نمایش موقت رکوردها و فایل‌های هر کد پرسنلی"
            />

            <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">کارمندان</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-1">
                        {isLoading && (
                            <p className="text-sm text-muted-foreground">
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
                        {!isLoading && (employees ?? []).length === 0 && (
                            <p className="text-sm text-muted-foreground">
                                رکوردی ثبت نشده است.
                            </p>
                        )}
                    </CardContent>
                </Card>

                <div className="space-y-4 lg:col-span-2">
                    {selected && (
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
                    )}
                </div>
            </div>
        </div>
    );
}
