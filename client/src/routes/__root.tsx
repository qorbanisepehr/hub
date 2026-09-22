import { createRootRoute, Outlet } from "@tanstack/react-router";
import { ThemeProvider } from "@/components/ui/theme-provider";
import { TooltipProvider } from "@/components/ui/tooltip";
import { Toaster } from "@/components/ui/sonner";
import { TopLoader } from "@/components/layout/top-loader";
import { PreviewLightboxHost } from "@/features/documents/preview-lightbox-host";

export const Route = createRootRoute({
    component: () => (
        <ThemeProvider
            attribute="class"
            defaultTheme="system"
            enableSystem
        >
            <TooltipProvider>
                <TopLoader />
                <Outlet />
                <PreviewLightboxHost />
                <Toaster position="top-center" />
            </TooltipProvider>
        </ThemeProvider>
    ),
});
