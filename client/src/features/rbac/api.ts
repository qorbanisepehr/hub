import { api } from "@/lib/api";
import { PAGINATION } from "@/lib/constants";
import type { PaginatedResponse, PaginatedListParams } from "@/lib/types";
import type { AuthorizationResponse } from "@/features/auth/types";
import type {
    Role,
    Permission,
    PermissionGroup,
    UserRoleAssignment,
    CreateRoleData,
    UpdateRoleData,
    UserListItem,
    UserDetail,
    UpdateUserData,
    CreateUserData,
    RoleChartRole,
    RuleBuilderMeta,
    RulePreviewRequest,
    RulePreviewResult,
} from "./types";

export type { PaginatedResponse };

export type UserListParams = PaginatedListParams & {
    filter?: string;
    role?: string;
    has_employee?: boolean;
    is_active?: boolean;
};

export type RoleListParams = PaginatedListParams & {
    filter?: string;
    is_active?: boolean;
};

export type PermissionListParams = PaginatedListParams & {
    filter?: string;
};

// ── Roles ──

export function fetchRoles(params: RoleListParams = {}) {
    return api.get<PaginatedResponse<Role>>("/roles", { params });
}

export function fetchAllRoles() {
    return api.get<{ data: Role[] }>("/roles", {
        params: { per_page: PAGINATION.FETCH_ALL_SIZE },
    });
}

export function fetchRolesChart() {
    return api.get<{ data: RoleChartRole[] }>("/roles/chart");
}

export function fetchRole(id: number) {
    return api.get<{ data: Role }>(`/roles/${id}`);
}

export function createRole(data: CreateRoleData) {
    return api.post<{ data: Role }>("/roles", data);
}

export function updateRole(id: number, data: UpdateRoleData) {
    return api.put<{ data: Role }>(`/roles/${id}`, data);
}

export function deleteRole(id: number) {
    return api.delete<{ message: string }>(`/roles/${id}`);
}

export function toggleRole(id: number) {
    return api.patch<{ data: Role }>(`/roles/${id}/toggle`);
}

// ── Permissions ──

export function fetchPermissions() {
    return api.get<{ data: PermissionGroup[] }>("/permissions");
}

export function fetchPermissionsPaginated(params: PermissionListParams = {}) {
    return api.get<PaginatedResponse<Permission>>("/permissions/search", {
        params,
    });
}

export function fetchRegisteredPermissions() {
    return api.get<
        Record<string, { name: string; permissions: Record<string, string> }>
    >("/permissions/registered");
}

// ── Rule builder ──

export function fetchRuleBuilderMeta() {
    return api.get<{ data: RuleBuilderMeta }>(
        "/authorization/rule-builder-meta",
    );
}

export function previewRule(data: RulePreviewRequest) {
    return api.post<{ data: RulePreviewResult }>(
        "/authorization/rule-preview",
        data,
    );
}

// ── Users ──

export function fetchUsers(params: UserListParams = {}) {
    return api.get<PaginatedResponse<UserListItem>>("/users", { params });
}

export function createUser(data: CreateUserData) {
    return api.post<{ data: UserListItem }>("/users", data);
}

export function fetchUser(userId: number) {
    return api.get<{ data: UserDetail }>(`/users/${userId}`);
}

export function fetchUserAuthorization(userId: number) {
    return api.get<{ data: AuthorizationResponse }>(
        `/users/${userId}/authorization`,
    );
}

export function updateUser(userId: number, data: UpdateUserData) {
    return api.put<{ data: UserDetail }>(`/users/${userId}`, data);
}

export function fetchUserRoles(userId: number) {
    return api.get<UserRoleAssignment>(`/users/${userId}/roles`);
}

export function assignUserRole(userId: number, roleId: number, active = false) {
    return api.post<{ message: string }>(`/users/${userId}/roles`, {
        role_id: roleId,
        active,
    });
}

export function removeUserRole(userId: number, roleId: number) {
    return api.delete<{ message: string }>(`/users/${userId}/roles/${roleId}`);
}

export function switchActiveRole(userId: number, roleId: number) {
    return api.post<{ message: string }>(
        `/users/${userId}/switch-active-role`,
        {
            role_id: roleId,
        },
    );
}

// ── Export ──
export type ChartExportField = {
    key: string;
    label: string;
    column: string;
};

export function fetchChartExportFields() {
    return api.get<{ data: ChartExportField[] }>("/roles/chart/export-fields");
}

export function exportRoleChart(params: {
    scope?: "all" | "subtree";
    root_id?: number;
    fields?: string[];
    format?: string;
}) {
    return api.get("/roles/chart/export", {
        params: {
            scope: params.scope ?? "all",
            root_id: params.root_id,
            fields: (params.fields ?? []).join(","),
            format: params.format ?? "csv",
        },
        responseType: "blob",
    });
}
