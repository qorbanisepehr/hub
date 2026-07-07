import { useNavigate, Link, useParams } from "@tanstack/react-router";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { IconLoader2, IconUsers } from "@tabler/icons-react";

import { EmployeeForm } from "@/features/employees/components/employee-form";
import { fetchEmployee, updateEmployee } from "@/features/employees/api";
import { getApiError } from "@/lib/error-utils";
import { Button } from "@/components/ui/button";

export function EmployeeEditPage() {
    const { id } = useParams({ from: "/protected/employees/$id/edit" });
    const navigate = useNavigate();
    const queryClient = useQueryClient();

    const {
        data: employee,
        isLoading,
        isError,
    } = useQuery({
        queryKey: ["employee", Number(id)],
        queryFn: async () => {
            const { data } = await fetchEmployee(Number(id));
            return data.data;
        },
    });

    const mutation = useMutation({
        mutationFn: (data: Parameters<typeof updateEmployee>[1]) =>
            updateEmployee(Number(id), data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ["employees"] });
            queryClient.invalidateQueries({
                queryKey: ["employee", Number(id)],
            });
            navigate({ to: "/employees" });
        },
    });

    if (isLoading) {
        return (
            <div className="flex flex-1 items-center justify-center p-6">
                <IconLoader2 className="size-6 animate-spin text-muted-foreground" />
            </div>
        );
    }

    if (isError) {
        return (
            <div className="flex flex-1 flex-col items-center justify-center gap-4 p-6 text-muted-foreground">
                <IconUsers className="size-12 opacity-30" />
                <p>خطا در بارگذاری اطلاعات کارمند</p>
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
                        ویرایش کارمند
                    </h1>
                    <p className="text-sm text-muted-foreground mt-1">
                        {employee.first_name} {employee.last_name}
                    </p>
                </div>
                <Button
                    variant="outline"
                    nativeButton={false}
                    render={<Link to="/employees" />}
                >
                    بازگشت به لیست
                </Button>
            </div>

            <EmployeeForm
                defaultValues={{
                    personnel_code: employee.personnel_code,
                    first_name: employee.first_name,
                    last_name: employee.last_name,
                    gender: employee.gender,
                    birth_date: employee.birth_date ?? "",
                    id_number: employee.id_number ?? "",
                    marital_status: (employee.marital_status ?? "") as
                        | ""
                        | "single"
                        | "married",
                    education_level: (employee.education_level ?? "") as
                        | ""
                        | "diploma"
                        | "associate"
                        | "bachelor"
                        | "master"
                        | "doctorate",
                    education_field: employee.education_field ?? "",
                    employment_type: (employee.employment_type ?? "") as
                        | ""
                        | "official"
                        | "contractual"
                        | "project-based",
                    hire_date: employee.hire_date ?? "",
                    employment_status: (employee.employment_status ?? "") as
                        | ""
                        | "active"
                        | "inactive"
                        | "suspended",
                }}
                onSubmit={(values) => mutation.mutate(values)}
                isPending={mutation.isPending}
                error={getApiError(mutation.error)}
                submitLabel="ذخیره تغییرات"
            />
        </div>
    );
}
