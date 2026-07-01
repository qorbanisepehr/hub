import { createRoute, Outlet } from "@tanstack/react-router";
import { Route as RootRoute } from "@/routes/__root";
import { AppSidebar } from "@/features/dashboard/components/app-sidebar";
import { SiteHeader } from "@/features/dashboard/components/site-header";
import { SidebarInset, SidebarProvider } from "@/components/ui/sidebar";
import { requireAuth } from "@/features/auth/guards";

export const Route = createRoute({
    getParentRoute: () => RootRoute,
    id: "protected",
    beforeLoad: ({ location }) => requireAuth(location),
    component: ProtectedLayout,
});

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
