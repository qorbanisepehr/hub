import { createRoute, Outlet } from "@tanstack/react-router";
import { Route as RootRoute } from "@/routes/__root";
import { PublicHeader } from "@/components/navigation";
import { Logo } from "@/components/navigation";
import { useBranding } from "@/features/settings/hooks/use-branding";
import { ensureFormOptions } from "@/features/form-options/hooks/use-form-options";
import { queryClient } from "@/lib/query-client";
import { COMPANY_NAME } from "@/lib/brand";

export const Route = createRoute({
    getParentRoute: () => RootRoute,
    id: "public",
    // Warm the form-options dictionary for public flows (questionnaire/cv)
    // so option-backed fields never flash raw stored values on first paint.
    loader: () => ensureFormOptions(queryClient),
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
