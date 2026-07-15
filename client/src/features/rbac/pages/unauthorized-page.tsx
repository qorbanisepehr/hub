import { Link } from "@tanstack/react-router";
import { IconShieldLock } from "@tabler/icons-react";
import { Button } from "@/components/ui/button";

export function UnauthorizedPage() {
    return (
        <div className="flex min-h-[60vh] flex-col items-center justify-center gap-4 text-center">
            <IconShieldLock className="size-16 text-muted-foreground/30" />
            <div className="space-y-2">
                <h1 className="text-2xl font-bold tracking-tight">دسترسی غیرمجاز</h1>
                <p className="text-sm text-muted-foreground max-w-md">
                    شما مجوز کافی برای مشاهده این صفحه را ندارید. در صورت نیاز با مدیر سامانه تماس بگیرید.
                </p>
            </div>
            <Button nativeButton={false} render={<Link to="/dashboard" />}>
                بازگشت به داشبورد
            </Button>
        </div>
    );
}
