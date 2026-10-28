import { Link, useNavigate, useParams } from "@tanstack/react-router";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useSelector } from "@tanstack/react-store";
import { IconPencil } from "@tabler/icons-react";
import { isAxiosError } from "axios";
import { toast } from "sonner";

import { Button } from "@/components/ui/button";
import { ConfirmDeleteButton } from "@/components/ui/confirm-delete-button";
import { DocumentPrintMenu } from "@/components/shared/document-print-menu";
import {
    fetchEmployee,
    fetchEmployeeDocument,
    deleteEmployee,
} from "@/features/employees/api";
import { getApiError } from "@/lib/error-utils";
import { EmployeeProfileView } from "@/features/employees/components/employee-profile-view";
import { profileViewStore } from "@/features/employees/profile-view-store";
import { ViewSkeleton } from "@/components/layout";
import { PageLayout } from "@/components/layout";
import { ErrorPage } from "@/components/layout";
import { PageHeader } from "@/components/layout";
import { employeeKeys } from "@/lib/query-keys";

export function EmployeeViewPage() {
    const { id } = useParams({ from: "/protected/employees/$id" });
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const employeeId = Number(id);
    // The active view tab deep-links the edit button: the edit form opens on
    // the same keyed hash section the user was reading (#contracts, ...).
    // Written by EmployeeProfileView into the shared profile-view store.
    const activeTabKey = useSelector(profileViewStore, (s) => s.activeTab);

    const {
        data: employee,
        isLoading,
        isError,
        error,
    } = useQuery({
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
        return <ViewSkeleton leftRows={6} columns={1} />;
    }

    if (isError) {
        const status = isAxiosError(error) ? error.response?.status : undefined;

        return (
            <ErrorPage
                status={status}
                title={getApiError(error) ?? undefined}
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
                title={`${employee.first_name} ${employee.last_name}`}
                description={`کد پرسنلی: ${employee.personnel_code}`}
                backTo="/employees"
            >
                <div className="flex items-center gap-2">
                    {/* Gated like this page itself (employee.view): whoever can
                        read the profile server-side can print it. Field-access
                        denies are applied inside the document. */}
                    <DocumentPrintMenu
                        filenamePrefix={`employee-profile-${employee.id}`}
                        fetchDocument={(format) =>
                            fetchEmployeeDocument(employee.id, format)
                        }
                    />
                    {employee.capabilities.edit && (
                        <Button
                            variant="outline"
                            nativeButton={false}
                            render={
                                <Link
                                    to="/employees/$id/edit"
                                    params={{ id: String(employee.id) }}
                                    hash={activeTabKey ?? true}
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

            <EmployeeProfileView employee={employee} />
        </PageLayout>
    );
}
