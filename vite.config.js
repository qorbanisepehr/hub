import tailwindcss from "@tailwindcss/vite";
import laravel from "laravel-vite-plugin";
import path from "path";
import { defineConfig } from "vite";

export default defineConfig({
    plugins: [
        laravel({
            input: "client/src/main.tsx",
            refresh: true,
        }),
        tailwindcss(),
    ],
    build: {
        rolldownOptions: {
            output: {
                codeSplitting: {
                    groups: [
                        // Keep the biggest vendor libs in their own cacheable chunks so
                        // the shared app "forms" chunk stays under 500kB.
                        {
                            name: "vendor-react",
                            test: /node_modules[\\/](react|react-dom|scheduler)[\\/]/,
                        },
                        {
                            name: "vendor-tanstack",
                            test: /node_modules[\\/]@tanstack[\\/]/,
                        },
                        {
                            name: "vendor-baseui",
                            test: /node_modules[\\/]@base-ui[\\/]/,
                        },
                        {
                            name: "vendor-forms",
                            test: /node_modules[\\/](react-number-format|zod)[\\/]/,
                        },
                    ],
                },
            },
        },
    },
    server: {
        origin: "http://localhost:5173",
        watch: {
            ignored: ["**/storage/framework/views/**"],
        },
        hmr: {
            host: "localhost",
        },
        cors: {
            origin: true,
            credentials: true,
        },
    },
    resolve: {
        alias: {
            "@": path.resolve(import.meta.dirname, "client/src"),
        },
    },
    esbuild: {
        jsx: "automatic",
    },
});
