import { Link } from "@tanstack/react-router";
import { Button, buttonVariants } from "@/components/ui/button";
import { Logo, LogoType } from "@/components/shared/logo";
import { ThemeToggle } from "@/components/shared/theme-toggle";
import { UserMenu } from "@/components/shared/user-menu";
import { useAuth } from "@/features/auth/useAuth";
import { useBranding } from "@/features/settings/hooks/use-branding";
import { IconLogin } from "@tabler/icons-react";
import { COMPANY_SUB_NAME } from "@/lib/brand";

export function PublicHeader() {
    const { isAuthenticated } = useAuth();
    const { data } = useBranding();

    return (
        <header className="fixed top-0 inset-x-0 z-50 flex h-16 items-center justify-between gap-4 border-b bg-background/70 backdrop-blur-md px-6">
            <Link
                to={isAuthenticated ? "/dashboard" : "/"}
                className="flex items-center gap-1"
            >
                <Logo className="size-9! group-data-[collapsible=icon]:size-6!" />
                <div className="mt-2">
                    <LogoType className="w-16!" />
                    <span className="text-[10px] text-primary/50">
                        {data?.sub_name ?? COMPANY_SUB_NAME}
                    </span>
                </div>
            </Link>

            <div className="flex items-center gap-2">
                <ThemeToggle />
                {isAuthenticated ? (
                    <UserMenu />
                ) : (
                    <Link to="/login" className={buttonVariants()}>
                        <span>ورود</span>
                        <IconLogin className="size-4" />
                    </Link>
                )}
            </div>
        </header>
    );
}
