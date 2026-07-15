import type { Role, Permission } from "@/features/rbac/types";

export type User = {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    username: string | null;
    active_role_id: number | null;
    roles?: Role[];
    active_role?: Role | null;
    permissions?: Permission[];
};

export type LoginResponse = {
    message: string;
    destination?: string;
    user?: User;
    retry_after?: number;
};

export type LoginMode = "otp" | "password";
