import { redirect } from "@tanstack/react-router";
import { authClient } from "@/features/auth/auth-client";

export function requireAuth(location: { href: string }) {
    if (!authClient.isAuthenticated()) {
        throw redirect({
            to: "/login",
            search: { redirect: location.href },
        });
    }
}

export function redirectIfAuthenticated() {
    if (authClient.isAuthenticated()) {
        throw redirect({ to: "/dashboard" });
    }
}
