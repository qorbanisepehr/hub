import { Skeleton } from "@/components/ui/skeleton";
import { useAuth } from "@/features/auth";
import { getUserDisplayName } from "@/lib/user-display";

export function DashboardPage() {
    const { user, isLoading } = useAuth();

    if (isLoading) {
        return (
            <div className="flex flex-1 flex-col items-center justify-center p-6">
                <div className="text-center space-y-2">
                    <Skeleton className="h-6 w-48 mx-auto" />
                    <Skeleton className="h-4 w-32 mx-auto" />
                </div>
            </div>
        );
    }

    if (!user) {
        return (
            <div className="flex flex-1 flex-col items-center justify-center p-6">
                <p className="text-muted-foreground">لطفا وارد شوید</p>
            </div>
        );
    }

    return (
        <div className="flex flex-1 flex-col items-center justify-center p-6">
            <div className="text-center">
                <p className="text-lg font-medium">خوش آمدید، {getUserDisplayName(user)}</p>
                <p className="text-sm text-muted-foreground mt-1">{user.email}</p>
            </div>
        </div>
    );
}
