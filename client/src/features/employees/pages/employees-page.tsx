import * as React from "react";
import { useQuery } from "@tanstack/react-query";
import { Link } from "@tanstack/react-router";
import { IconEye, IconPencil, IconPlus, IconUsers } from "@tabler/icons-react";

import { Button } from "@/components/ui/button";
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    CardDescription,
} from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Skeleton } from "@/components/ui/skeleton";
import { fetchEmployees } from "@/features/employees/api";
import type { Employee } from "@/features/employees/types";
import {
    genderLabels,
    statusLabels,
    statusVariants,
} from "@/features/employees/constants";

function EmployeeRow({ employee }: { employee: Employee }) {
    return (
        <tr className="border-b last:border-b-0 transition-colors hover:bg-muted/50">
            <td className="py-3 px-4 text-sm font-medium" dir="ltr">
                {employee.personnel_code}
            </td>
            <td className="py-3 px-4 text-sm">
                <Link
                    to="/employees/$id"
                    params={{ id: String(employee.id) }}
                    className="hover:text-primary transition-colors"
                >
                    {employee.first_name} {employee.last_name}
                </Link>
            </td>
            <td className="py-3 px-4 text-sm">
                <Badge variant="outline">
                    {genderLabels[employee.gender] ?? employee.gender}
                </Badge>
            </td>
            <td className="py-3 px-4">
                <Badge
                    variant={
                        statusVariants[employee.employment_status ?? ""] ??
                        "secondary"
                    }
                >
                    {statusLabels[employee.employment_status ?? ""] ??
                        employee.employment_status}
                </Badge>
            </td>
            <td className="py-3 px-4 text-sm text-muted-foreground">
                {employee.hire_date ?? "—"}
            </td>
            <td className="py-3 px-4">
                <div className="flex items-center gap-1">
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        nativeButton={false}
                        render={
                            <Link
                                to="/employees/$id"
                                params={{ id: String(employee.id) }}
                            />
                        }
                    >
                        <IconEye className="size-4" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        nativeButton={false}
                        render={
                            <Link
                                to="/employees/$id/edit"
                                params={{ id: String(employee.id) }}
                            />
                        }
                    >
                        <IconPencil className="size-4" />
                    </Button>
                </div>
            </td>
        </tr>
    );
}

function TableSkeleton() {
    return (
        <div className="space-y-2">
            {Array.from({ length: 5 }).map((_, i) => (
                <Skeleton key={i} className="h-12 w-full" />
            ))}
        </div>
    );
}

export function EmployeesPage() {
    const [page, setPage] = React.useState(1);

    const { data, isLoading, isError } = useQuery({
        queryKey: ["employees", page],
        queryFn: async () => {
            const { data } = await fetchEmployees(page);
            return data;
        },
    });

    return (
        <div className="flex flex-1 flex-col gap-6 p-6">
            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">
                        کارمندان
                    </h1>
                    <p className="text-sm text-muted-foreground mt-1">
                        مدیریت اطلاعات کارمندان شرکت
                    </p>
                </div>
                <Button
                    nativeButton={false}
                    render={<Link to="/employees/create" />}
                >
                    <IconPlus className="size-4" />
                    کارمند جدید
                </Button>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <IconUsers className="size-5" />
                        لیست کارمندان
                    </CardTitle>
                    {data && (
                        <CardDescription>
                            مجموع {data.meta.total} کارمند
                        </CardDescription>
                    )}
                </CardHeader>
                <CardContent className="p-0">
                    {isLoading ? (
                        <div className="p-4">
                            <TableSkeleton />
                        </div>
                    ) : isError ? (
                        <div className="flex flex-col items-center justify-center py-12 text-muted-foreground">
                            <p>خطا در بارگذاری اطلاعات</p>
                        </div>
                    ) : !data?.data.length ? (
                        <div className="flex flex-col items-center justify-center py-12 text-muted-foreground">
                            <IconUsers className="size-12 mb-4 opacity-30" />
                            <p>هیچ کارمندی یافت نشد</p>
                            <Button
                                variant="link"
                                className="mt-2"
                                nativeButton={false}
                                render={<Link to="/employees/create" />}
                            >
                                اولین کارمند را ثبت کنید
                            </Button>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead>
                                    <tr className="border-b bg-muted/50">
                                        <th className="py-3 px-4 text-right text-sm font-medium text-muted-foreground">
                                            کد پرسنلی
                                        </th>
                                        <th className="py-3 px-4 text-right text-sm font-medium text-muted-foreground">
                                            نام و نام خانوادگی
                                        </th>
                                        <th className="py-3 px-4 text-right text-sm font-medium text-muted-foreground">
                                            جنسیت
                                        </th>
                                        <th className="py-3 px-4 text-right text-sm font-medium text-muted-foreground">
                                            وضعیت
                                        </th>
                                        <th className="py-3 px-4 text-right text-sm font-medium text-muted-foreground">
                                            تاریخ استخدام
                                        </th>
                                        <th className="py-3 px-4 text-right text-sm font-medium text-muted-foreground">
                                            عملیات
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {data.data.map((employee) => (
                                        <EmployeeRow
                                            key={employee.id}
                                            employee={employee}
                                        />
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </CardContent>
            </Card>

            {data && data.meta.last_page > 1 && (
                <div className="flex items-center justify-between text-sm text-muted-foreground">
                    <span>
                        صفحه {data.meta.current_page} از {data.meta.last_page}
                    </span>
                    <div className="flex gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={page <= 1}
                            onClick={() => setPage((p) => Math.max(1, p - 1))}
                        >
                            قبلی
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={page >= data.meta.last_page}
                            onClick={() => setPage((p) => p + 1)}
                        >
                            بعدی
                        </Button>
                    </div>
                </div>
            )}
        </div>
    );
}
