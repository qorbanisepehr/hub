import { useMutation, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { IconMasksTheater, IconCheck } from "@tabler/icons-react";

import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    CardDescription,
} from "@/components/ui/card";
import { useAuth } from "@/features/auth/useAuth";
import { authKeys } from "@/lib/query-keys";
import { switchActiveRole } from "@/features/auth/api";
import { getApiError } from "@/lib/error-utils";

export function RoleSwitcher() {
    const { user } = useAuth();
    const queryClient = useQueryClient();

    const roleMutation = useMutation({
        mutationFn: (roleId: number) => switchActiveRole(roleId),
        onSuccess: async () => {
            await queryClient.invalidateQueries({ queryKey: authKeys.all });
            toast.success("نقش فعال تغییر کرد.");
        },
        onError: (err) => {
            toast.error(getApiError(err));
        },
    });

    const roles = user?.roles ?? [];

    if (roles.length === 0) {
        return (
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2 text-base">
                        <IconMasksTheater className="size-5" />
                        نقش فعال
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <p className="text-sm text-muted-foreground text-center py-4">
                        هیچ نقشی تخصیص داده شده است
                    </p>
                </CardContent>
            </Card>
        );
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2 text-base">
                    <IconMasksTheater className="size-5" />
                    نقش فعال
                </CardTitle>
                <CardDescription>
                    نقش فعال خود را انتخاب کنید
                </CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-2">
                {roles.map((role) => {
                    const isActive = user?.active_role?.id === role.id;
                    return (
                        <button
                            key={role.id}
                            type="button"
                            disabled={roleMutation.isPending || isActive}
                            onClick={() => roleMutation.mutate(role.id)}
                            className={`flex w-full items-center justify-between rounded-lg border p-3 text-start transition-colors ${
                                isActive
                                    ? "border-primary bg-primary/5 ring-1 ring-primary/20"
                                    : "border-border hover:bg-muted/50 cursor-pointer"
                            }`}
                        >
                            <div className="flex flex-col gap-0.5">
                                <span className="text-sm font-medium">
                                    {role.display_name}
                                </span>
                                {role.description && (
                                    <span className="text-xs text-muted-foreground">
                                        {role.description}
                                    </span>
                                )}
                            </div>
                            {isActive && (
                                <IconCheck className="size-4 text-primary shrink-0" />
                            )}
                        </button>
                    );
                })}
            </CardContent>
        </Card>
    );
}
