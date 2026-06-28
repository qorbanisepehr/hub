import "@/assets/styles/app.css";
import { StrictMode, Suspense } from "react";
import { createRoot } from "react-dom/client";

const rootElement = document.getElementById("app")!;
if (!rootElement.innerHTML) {
    const root = createRoot(rootElement);
    root.render(
        <StrictMode>
            <div className="bg-gray-900 w-full h-dvh text-2xl font-black text-brand flex justify-center items-center">
                ســــــــــــــلام دنــیا!
            </div>
        </StrictMode>,
    );
}
