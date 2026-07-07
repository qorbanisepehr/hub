import * as React from "react";
import { Link, useNavigate, useParams } from "@tanstack/react-router";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { IconArrowRight, IconPencil, IconUsers } from "@tabler/icons-react";

import { Button } from "@/components/ui/button";
import { ConfirmDeleteButton } from "@/components/ui/confirm-delete-button";
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    CardDescription,
} from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Skeleton } from "@/components/ui/skeleton";
import { fetchEmployee, deleteEmployee } from "@/features/employees/api";
import { getApiError } from "@/lib/error-utils";

import {
    genderLabels,
    maritalLabels,
    educationLabels,
    employmentLabels,
    statusLabels,
    statusVariants,
} from "@/features/employees/constants";

function InfoRow({ label, value }: { label: string; value: React.ReactNode }) {
    return (
        <div className="flex items-baseline gap-2 py-2 border-b last:border-b-0">
            <span className="text-sm text-muted-foreground min-w-32">
                {label}
            </span>
            <span className="text-sm font-medium">{value ?? "—"}</span>
        </div>
    );
}

export function EmployeeViewPage() {
    const { id } = useParams({ from: "/protected/employees/$id" });
    const navigate = useNavigate();
    const queryClient = useQueryClient();

    const { data: employee, isLoading } = useQuery({
        queryKey: ["employee", Number(id)],
        queryFn: async () => {
            const { data } = await fetchEmployee(Number(id));
            return data.data;
        },
    });

    const deleteMutation = useMutation({
        mutationFn: () => deleteEmployee(Number(id)),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ["employees"] });
            navigate({ to: "/employees" });
        },
    });

    if (isLoading) {
        return (
            <div className="flex flex-1 flex-col gap-6 p-6">
                <Skeleton className="h-8 w-48" />
                <div className="grid gap-6 md:grid-cols-2">
                    <Skeleton className="h-64" />
                    <Skeleton className="h-64" />
                </div>
            </div>
        );
    }

    if (!employee) {
        return (
            <div className="flex flex-1 flex-col items-center justify-center gap-4 p-6 text-muted-foreground">
                <IconUsers className="size-12 opacity-30" />
                <p>کارمند مورد نظر یافت نشد</p>
                <Button
                    variant="outline"
                    nativeButton={false}
                    render={<Link to="/employees" />}
                >
                    بازگشت به لیست
                </Button>
            </div>
        );
    }

    return (
        <div className="flex flex-1 flex-col gap-6 p-6">
            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">
                        {employee.first_name} {employee.last_name}
                    </h1>
                    <p className="text-sm text-muted-foreground mt-1">
                        کد پرسنلی: {employee.personnel_code}
                    </p>
                </div>
                <div className="flex items-center gap-2">
                    <Button
                        variant="outline"
                        nativeButton={false}
                        render={
                            <Link
                                to="/employees/$id/edit"
                                params={{ id: String(employee.id) }}
                            />
                        }
                    >
                        <IconPencil className="size-4" />
                        ویرایش
                    </Button>
                    <ConfirmDeleteButton
                        onConfirm={() => deleteMutation.mutate()}
                        isPending={deleteMutation.isPending}
                    />
                </div>
            </div>

            {getApiError(deleteMutation.error) && (
                <div className="rounded-lg bg-destructive/10 p-3 text-sm text-destructive">
                    {getApiError(deleteMutation.error)}
                </div>
            )}

            <div className="grid gap-6 md:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>اطلاعات فردی</CardTitle>
                        <CardDescription>اطلاعات هویتی کارمند</CardDescription>
                    </CardHeader>
                    <CardContent className="divide-y">
                        <InfoRow
                            label="کد پرسنلی"
                            value={
                                <span dir="ltr">{employee.personnel_code}</span>
                            }
                        />
                        <InfoRow
                            label="نام و نام خانوادگی"
                            value={`${employee.first_name} ${employee.last_name}`}
                        />
                        <InfoRow
                            label="جنسیت"
                            value={
                                <Badge variant="outline">
                                    {genderLabels[employee.gender] ??
                                        employee.gender}
                                </Badge>
                            }
                        />
                        <InfoRow
                            label="تاریخ تولد"
                            value={employee.birth_date ?? "—"}
                        />
                        <InfoRow
                            label="کد ملی"
                            value={employee.id_number ?? "—"}
                        />
                        <InfoRow
                            label="وضعیت تاهل"
                            value={
                                employee.marital_status
                                    ? (maritalLabels[employee.marital_status] ??
                                      employee.marital_status)
                                    : "—"
                            }
                        />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>اطلاعات شغلی</CardTitle>
                        <CardDescription>وضعیت استخدامی کارمند</CardDescription>
                    </CardHeader>
                    <CardContent className="divide-y">
                        <InfoRow
                            label="نوع استخدام"
                            value={
                                employee.employment_type
                                    ? (employmentLabels[
                                          employee.employment_type
                                      ] ?? employee.employment_type)
                                    : "—"
                            }
                        />
                        <InfoRow
                            label="تاریخ استخدام"
                            value={employee.hire_date ?? "—"}
                        />
                        <InfoRow
                            label="وضعیت اشتغال"
                            value={
                                <Badge
                                    variant={
                                        statusVariants[
                                            employee.employment_status ?? ""
                                        ] ?? "secondary"
                                    }
                                >
                                    {statusLabels[
                                        employee.employment_status ?? ""
                                    ] ?? employee.employment_status}
                                </Badge>
                            }
                        />
                        <InfoRow
                            label="سطح تحصیلات"
                            value={
                                employee.education_level
                                    ? (educationLabels[
                                          employee.education_level
                                      ] ?? employee.education_level)
                                    : "—"
                            }
                        />
                        <InfoRow
                            label="رشته تحصیلی"
                            value={employee.education_field ?? "—"}
                        />
                        <InfoRow
                            label="کاربر مرتبط"
                            value={
                                employee.user
                                    ? `${employee.user.name} (${employee.user.email})`
                                    : "—"
                            }
                        />
                    </CardContent>
                </Card>
            </div>

            <div className="flex justify-start">
                <Button
                    variant="outline"
                    nativeButton={false}
                    render={<Link to="/employees" />}
                >
                    <IconArrowRight className="size-4" />
                    بازگشت به لیست
                </Button>
            </div>
        </div>
    );
}
