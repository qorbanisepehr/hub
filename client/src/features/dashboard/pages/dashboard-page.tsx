import { useAuth } from "@/features/auth/useAuth";

export function DashboardPage() {
    const { user, isLoading } = useAuth();

    if (isLoading) {
        return (
            <div className="flex flex-1 flex-col items-center justify-center p-6">
                <p className="text-muted-foreground">در حال بارگذاری...</p>
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
                <p className="text-lg font-medium">خوش آمدید، {user.name}</p>
                <p className="text-sm text-muted-foreground mt-1">{user.email}</p>
            </div>
        </div>
    );
}
