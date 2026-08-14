import { Link, useNavigate, useParams } from "@tanstack/react-router";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { IconPencil } from "@tabler/icons-react";
import { toast } from "sonner";

import { Button } from "@/components/ui/button";
import { ConfirmDeleteButton } from "@/components/ui/confirm-delete-button";
import { fetchEmployee, deleteEmployee } from "@/features/employees/api";
import { getApiError } from "@/lib/error-utils";
import { EmployeeProfileView } from "@/features/employees/components/employee-profile-view";
import { ViewSkeleton } from "@/components/shared/view-skeleton";
import { PageLayout } from "@/components/shared/page-layout";
import { ErrorPage } from "@/components/shared/error-page";
import { PageHeader } from "@/components/shared/page-header";
import { employeeKeys } from "@/lib/query-keys";

export function EmployeeViewPage() {
    const { id } = useParams({ from: "/protected/employees/$id" });
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const employeeId = Number(id);

    const { data: employee, isLoading } = useQuery({
        queryKey: employeeKeys.detail(employeeId),
        queryFn: async () => {
            const { data } = await fetchEmployee(employeeId);
            return data.data;
        },
    });

    const deleteMutation = useMutation({
        mutationFn: () => deleteEmployee(employeeId),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: employeeKeys.all });
            toast.success("کارمند حذف شد");
            navigate({ to: "/employees" });
        },
        onError: (err: unknown) => {
            toast.error(getApiError(err));
        },
    });

    if (isLoading) {
        return <ViewSkeleton leftRows={6} rightRows={6} />;
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
                title={`${employee.first_name} ${employee.last_name}`}
                description={`کد پرسنلی: ${employee.personnel_code}`}
                backTo="/employees"
            >
                <div className="flex items-center gap-2">
                    {employee.capabilities.edit && (
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
                    )}
                    {employee.capabilities.delete && (
                        <ConfirmDeleteButton
                            onConfirm={() => deleteMutation.mutate()}
                            isPending={deleteMutation.isPending}
                        />
                    )}
                </div>
            </PageHeader>

            {deleteMutation.isError && (
                <div className="rounded-lg bg-destructive/10 p-3 text-sm text-destructive">
                    {getApiError(deleteMutation.error)}
                </div>
            )}

            <EmployeeProfileView employee={employee} />
        </PageLayout>
    );
}
