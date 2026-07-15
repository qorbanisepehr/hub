import "@/assets/styles/app.css";
import { StrictMode, Suspense } from "react";
import { createRoot } from "react-dom/client";
import { QueryClientProvider } from "@tanstack/react-query";
import { RouterProvider } from "@tanstack/react-router";
import { router } from "@/router";
import { queryClient } from "@/lib/query-client";

const rootElement = document.getElementById("app")!;
if (!rootElement.innerHTML) {
    const root = createRoot(rootElement);
    root.render(
        <StrictMode>
            <QueryClientProvider client={queryClient}>
                <Suspense fallback={null}>
                    <RouterProvider router={router} />
                </Suspense>
            </QueryClientProvider>
        </StrictMode>,
    );
}
