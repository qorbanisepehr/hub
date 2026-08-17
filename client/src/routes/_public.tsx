import { createRoute, Outlet } from "@tanstack/react-router";
import { Route as RootRoute } from "@/routes/__root";
import { PublicHeader } from "@/components/navigation";
import { Logo } from "@/components/navigation";
import { useBranding } from "@/features/settings/hooks/use-branding";
import { COMPANY_NAME } from "@/lib/brand";

export const Route = createRoute({
    getParentRoute: () => RootRoute,
    id: "public",
    component: PublicLayout,
});

function PublicLayout() {
    const { data } = useBranding();

    return (
        <div className="flex flex-col min-h-dvh">
            <PublicHeader />

            <main className="flex-1">
                <Outlet />
            </main>

            <footer className="py-8">
                <div className="mx-auto max-w-6xl px-6">
                    <div className="flex flex-col items-center justify-between gap-4 md:flex-row">
                        <div className="flex items-center gap-3">
                            <Logo className="size-8" />
                            <span className="font-heading text-lg font-bold">
                                {data?.name ?? COMPANY_NAME}
                            </span>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            تمامی حقوق محفوظ است &copy;{" "}
                            {new Date().getFullYear()}
                        </p>
                    </div>
                </div>
            </footer>
        </div>
    );
}
