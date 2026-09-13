import { createRootRoute, Outlet } from "@tanstack/react-router";
import { ThemeProvider } from "next-themes";
import { TooltipProvider } from "@/components/ui/tooltip";
import { Toaster } from "@/components/ui/sonner";
import { PreviewLightboxHost } from "@/features/documents/preview-lightbox-host";

export const Route = createRootRoute({
    component: () => (
        <ThemeProvider
            attribute="class"
            defaultTheme="system"
            enableSystem
        >
            <TooltipProvider>
                <Outlet />
                <PreviewLightboxHost />
                <Toaster position="top-center" />
            </TooltipProvider>
        </ThemeProvider>
    ),
});
