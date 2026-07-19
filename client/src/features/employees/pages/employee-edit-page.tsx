import { useNavigate, useParams } from "@tanstack/react-router";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";

import { EmployeeForm } from "@/features/employees/components/employee-form";
import { fetchEmployee, updateEmployee } from "@/features/employees/api";
import { getApiError } from "@/lib/error-utils";
import { PageLayout } from "@/components/shared/page-layout";
import { ErrorPage } from "@/components/shared/error-page";
import { PageHeader } from "@/components/shared/page-header";
import { PageSkeleton } from "@/components/shared/page-skeleton";
import { employeeKeys } from "@/lib/query-keys";

export function EmployeeEditPage() {
    const { id } = useParams({ from: "/protected/employees/$id/edit" });
    const navigate = useNavigate();
    const queryClient = useQueryClient();

    const {
        data: employee,
        isLoading,
        isError,
    } = useQuery({
        queryKey: employeeKeys.detail(Number(id)),
        queryFn: async () => {
            const { data } = await fetchEmployee(Number(id));
            return data.data;
        },
    });

    const mutation = useMutation({
        mutationFn: (data: Parameters<typeof updateEmployee>[1]) =>
            updateEmployee(Number(id), data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: employeeKeys.all });
            queryClient.invalidateQueries({
                queryKey: employeeKeys.detail(Number(id)),
            });
            toast.success("اطلاعات کارمند به‌روزرسانی شد");
            navigate({ to: "/employees" });
        },
        onError: () => {
            toast.error("خطا در به‌روزرسانی اطلاعات کارمند");
        },
    });

    if (isLoading) {
        return <PageSkeleton />;
    }

    if (isError) {
        return (
            <ErrorPage
                title="خطا در بارگذاری اطلاعات کارمند"
                homeTo="/employees"
                homeLabel="بازگشت به لیست"
            />
        );
    }

    if (!employee) {
        return (
            <ErrorPage
                status={404}
                title="کارمند مورد نظر یافت نشد"
                homeTo="/employees"
                homeLabel="بازگشت به لیست"
            />
        );
    }

    return (
        <PageLayout>
            <PageHeader
                title="ویرایش کارمند"
                description={`${employee.first_name} ${employee.last_name}`}
                backTo="/employees"
            />

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
                    user_id: employee.user?.id ?? null,
                }}
                onSubmit={(values) => mutation.mutate(values)}
                isPending={mutation.isPending}
                error={getApiError(mutation.error)}
                submitLabel="ذخیره تغییرات"
            />
        </PageLayout>
    );
}
