import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import { bunny } from "laravel-vite-plugin/fonts";
import tailwindcss from "@tailwindcss/vite";
import path from "path";

export default defineConfig({
    plugins: [
        laravel({
            input: "client/src/main.tsx",
            refresh: true,
        }),
        tailwindcss(),
    ],
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
            "@": path.resolve(__dirname, "client/src"),
        },
    },
    esbuild: {
        jsx: "automatic",
    },
});
