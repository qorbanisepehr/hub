import type { Role, Permission } from "@/features/rbac/types";

export type User = {
    id: number;
    name: string;
    avatar_url: string | null;
    email: string;
    phone: string | null;
    username: string | null;
    is_active: boolean;
    is_super_admin: boolean;
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
    expires_in?: number;
    code_sent?: boolean;
};

export type LoginMode = "otp" | "password";
