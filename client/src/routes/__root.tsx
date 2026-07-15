import { createRootRoute, Outlet } from "@tanstack/react-router";
import { ThemeProvider } from "next-themes";
import { TooltipProvider } from "@/components/ui/tooltip";
import { Toaster } from "@/components/ui/sonner";

export const Route = createRootRoute({
    component: () => (
        <ThemeProvider
            attribute="class"
            defaultTheme="system"
            enableSystem
        >
            <TooltipProvider>
                <Outlet />
                <Toaster richColors closeButton />
            </TooltipProvider>
        </ThemeProvider>
    ),
});
