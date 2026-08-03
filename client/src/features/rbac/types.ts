import type {
    MatrixManagerType,
    EducationLevel,
    LanguageLevel,
} from "@/features/rbac/constants";

export type Permission = {
    id: number;
    name: string;
    display_name: string;
    group_id: number;
    group?: PermissionGroup;
    created_at: string;
    updated_at: string;
};

export type PermissionGroup = {
    id: number;
    name: string;
    slug: string;
    sort_order: number;
    permissions: Permission[];
    created_at: string;
    updated_at: string;
};

export type MatrixManager = {
    role_id: number;
    manager_type: MatrixManagerType;
};

export type ResolvedMatrixManager = {
    id: number;
    display_name: string;
    manager_type: MatrixManagerType;
};

export type RoleRequirements = {
    min_education?: EducationLevel | null;
    min_experience_years?: number | null;
    required_skills?: string[];
    preferred_skills?: string[];
    certifications?: string[];
    languages?: LanguageLevel[];
};

export type Role = {
    id: number;
    name: string;
    display_name: string;
    description: string | null;
    is_active: boolean;
    inherits_permissions: boolean;
    parent_id: number | null;
    matrix_managers?: MatrixManager[];
    requirements?: RoleRequirements | null;
    matrix_manager_roles?: ResolvedMatrixManager[];
    parent?: Role;
    permission_groups?: PermissionGroup[];
    permissions?: Permission[];
    children?: Role[];
    created_at: string;
    updated_at: string;
};

export type UserRoleAssignment = {
    roles: Role[];
    active_role: Role | null;
};

export type RoleChartUser = {
    id: number;
    name: string;
    avatar_url: string | null;
};

export type RoleChartChild = {
    id: number;
    display_name: string;
};

export type ChartViewMode = "roles" | "users";
export type ChartUserFilter = "all" | "with" | "without";
export type ChartStatusFilter = "all" | "active" | "inactive";

export type RoleChartRole = {
    id: number;
    name: string;
    display_name: string;
    description: string | null;
    is_active: boolean;
    parent_id: number | null;
    matrix_managers: MatrixManager[];
    matrix_manager_roles: ResolvedMatrixManager[];
    children: RoleChartChild[];
    users: RoleChartUser[];
    user_count: number;
    children_count: number;
};

export type CreateRoleData = {
    name: string;
    display_name: string;
    description?: string;
    parent_id?: number | null;
    inherits_permissions?: boolean;
    is_active?: boolean;
    matrix_managers?: MatrixManager[];
    requirements?: RoleRequirements | null;
    permission_ids?: number[];
    permission_group_ids?: number[];
};

export type UpdateRoleData = Partial<CreateRoleData>;

export type UserListItem = {
    id: number;
    name: string;
    avatar_url: string | null;
    email: string;
    is_active: boolean;
    is_super_admin: boolean;
    roles: Role[];
    active_role: Role | null;
};

export type UserDetail = {
    id: number;
    name: string;
    avatar_url: string | null;
    email: string;
    phone: string | null;
    username: string | null;
    is_active: boolean;
    is_super_admin: boolean;
    roles: Role[];
    active_role: Role | null;
};

export type UpdateUserData = {
    name?: string;
    avatar_url?: string | null;
    email?: string;
    phone?: string | null;
    username?: string | null;
    is_active?: boolean;
    password?: string | null;
};

export type CreateUserData = {
    name: string;
    avatar_url?: string | null;
    email: string;
    phone?: string | null;
    username?: string | null;
    is_active?: boolean;
    password: string;
    password_confirmation: string;
};
