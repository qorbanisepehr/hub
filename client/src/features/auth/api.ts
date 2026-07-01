import { api } from "@/lib/api";
import type { LoginResponse, User } from "@/features/auth/types";

export function requestOtp(identifier: string) {
    return api.post<LoginResponse>("/auth/login", { identifier });
}

export function verifyOtp(identifier: string, code: string) {
    return api.post<LoginResponse>("/auth/verify-otp", { identifier, code });
}

export function loginWithPassword(identifier: string, password: string) {
    return api.post<LoginResponse>("/auth/login-with-password", {
        identifier,
        password,
    });
}

export function logout() {
    return api.post("/auth/logout");
}

export function fetchMe() {
    return api.get<{ data: User }>("/auth/me");
}
