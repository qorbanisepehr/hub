import { useParams } from "@tanstack/react-router";
import { useQuery } from "@tanstack/react-query";

import { fetchEmployee } from "@/features/employees/api";
import { EmployeeProfileForm } from "@/features/employees/components/employee-profile-form";
import { PageLayout } from "@/components/shared/page-layout";
import { ErrorPage } from "@/components/shared/error-page";
import { PageHeader } from "@/components/shared/page-header";
import { PageSkeleton } from "@/components/shared/page-skeleton";
import { employeeKeys } from "@/lib/query-keys";

export function EmployeeEditPage() {
    const { id } = useParams({ from: "/protected/employees/$id/edit" });

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
                title="پروفایل کارمند"
                description={`${employee.first_name} ${employee.last_name} — کد پرسنلی: ${employee.personnel_code}`}
                backTo="/employees"
            />

            <EmployeeProfileForm employee={employee} />
        </PageLayout>
    );
}
