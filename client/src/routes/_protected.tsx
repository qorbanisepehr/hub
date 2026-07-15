import { createRoute, Outlet } from "@tanstack/react-router";
import { Route as RootRoute } from "@/routes/__root";
import { AppSidebar } from "@/features/dashboard/components/app-sidebar";
import { SiteHeader } from "@/features/dashboard/components/site-header";
import { SidebarInset, SidebarProvider } from "@/components/ui/sidebar";
import { requireAuth } from "@/features/auth/guards";
import { ErrorPage } from "@/components/shared/error-page";

export const Route = createRoute({
    getParentRoute: () => RootRoute,
    id: "protected",
    beforeLoad: ({ location }) => requireAuth(location),
    errorComponent: ProtectedError,
    notFoundComponent: ProtectedNotFound,
    component: ProtectedLayout,
});

function ProtectedError({ error }: { error: Error }) {
    const message = error?.message ?? "خطای ناشناخته";
    return <ErrorPage title={message} homeTo="/dashboard" />;
}

function ProtectedNotFound() {
    return <ErrorPage status={404} homeTo="/dashboard" />;
}

function ProtectedLayout() {
    return (
        <SidebarProvider
            style={
                {
                    "--sidebar-width": "calc(var(--spacing) * 72)",
                    "--header-height": "calc(var(--spacing) * 12)",
                } as React.CSSProperties
            }
        >
            <AppSidebar variant="inset" side="right" collapsible="icon" />
            <SidebarInset>
                <SiteHeader />
                <Outlet />
            </SidebarInset>
        </SidebarProvider>
    );
}
