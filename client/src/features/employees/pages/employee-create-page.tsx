import { useNavigate, Link } from "@tanstack/react-router";
import { useMutation, useQueryClient } from "@tanstack/react-query";

import { EmployeeForm } from "@/features/employees/components/employee-form";
import { createEmployee } from "@/features/employees/api";
import { getApiError } from "@/lib/error-utils";
import { Button } from "@/components/ui/button";

export function EmployeeCreatePage() {
    const navigate = useNavigate();
    const queryClient = useQueryClient();

    const mutation = useMutation({
        mutationFn: (data: Parameters<typeof createEmployee>[0]) =>
            createEmployee(data),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ["employees"] });
            navigate({ to: "/employees" });
        },
    });

    return (
        <div className="flex flex-1 flex-col gap-6 p-6">
            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">
                        کارمند جدید
                    </h1>
                    <p className="text-sm text-muted-foreground mt-1">
                        ثبت اطلاعات کارمند جدید در سیستم
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
                onSubmit={(values) => mutation.mutate(values)}
                isPending={mutation.isPending}
                error={getApiError(mutation.error)}
            />
        </div>
    );
}
