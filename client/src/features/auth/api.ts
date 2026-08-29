import { api } from "@/lib/api";
import type {
    AuthorizationResponse,
    LoginResponse,
    User,
} from "@/features/auth/types";

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

export function fetchEffectivePermissions() {
    return api.get<{ data: AuthorizationResponse }>("/auth/me/authorization");
}

export function updateProfile(data: { name?: string; email?: string; phone?: string }) {
    return api.put<{ data: User }>("/auth/profile", data);
}

export function uploadAvatar(file: File) {
    const formData = new FormData();
    formData.append("avatar", file);
    return api.post<{ data: User }>("/auth/avatar", formData, {
        headers: { "Content-Type": "multipart/form-data" },
    });
}

export function deleteAvatar() {
    return api.delete<{ data: User }>("/auth/avatar");
}

export function switchActiveRole(roleId: number) {
    return api.post<{ data: User }>("/auth/switch-active-role", {
        role_id: roleId,
    });
}

export function changePassword(data: {
    current_password: string;
    password: string;
    password_confirmation: string;
}) {
    return api.post<{ message: string }>("/auth/change-password", data);
}
