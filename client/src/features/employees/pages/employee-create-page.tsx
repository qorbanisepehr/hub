import { useNavigate } from "@tanstack/react-router";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";

import { EmployeeForm } from "@/features/employees/components/employee-form";
import { createEmployee } from "@/features/employees/api";
import { getApiError } from "@/lib/error-utils";
import { PageLayout } from "@/components/shared/page-layout";
import { PageHeader } from "@/components/shared/page-header";
import { employeeKeys } from "@/lib/query-keys";

export function EmployeeCreatePage() {
    const navigate = useNavigate();
    const queryClient = useQueryClient();

    const mutation = useMutation({
        mutationFn: (data: Parameters<typeof createEmployee>[0]) =>
            createEmployee(data),
        onSuccess: ({ data }) => {
            queryClient.invalidateQueries({ queryKey: employeeKeys.all });
            toast.success("کارمند جدید ثبت شد");
            navigate({
                to: "/employees/$id/edit",
                params: { id: String(data.data.id) },
            });
        },
        onError: (err: unknown) => {
            toast.error(getApiError(err));
        },
    });

    return (
        <PageLayout>
            <PageHeader
                title="کارمند جدید"
                description="ثبت اطلاعات کارمند جدید در سیستم"
                backTo="/employees"
            />

            <EmployeeForm
                onSubmit={(values) => mutation.mutate(values)}
                isPending={mutation.isPending}
                error={getApiError(mutation.error)}
            />
        </PageLayout>
    );
}
