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

export type Role = {
    id: number;
    name: string;
    display_name: string;
    description: string | null;
    is_active: boolean;
    inherits_permissions: boolean;
    parent_id: number | null;
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

export type CreateRoleData = {
    name: string;
    display_name: string;
    description?: string;
    parent_id?: number | null;
    inherits_permissions?: boolean;
    is_active?: boolean;
    permission_ids?: number[];
    permission_group_ids?: number[];
};

export type UpdateRoleData = Partial<CreateRoleData>;

export type UserListItem = {
    id: number;
    name: string;
    email: string;
    roles: Role[];
    active_role: Role | null;
};

export type UserDetail = {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    username: string | null;
    roles: Role[];
    active_role: Role | null;
};

export type UpdateUserData = {
    name?: string;
    email?: string;
    phone?: string | null;
    username?: string | null;
    password?: string | null;
};

export type CreateUserData = {
    name: string;
    email: string;
    phone?: string | null;
    username?: string | null;
    password: string;
    password_confirmation: string;
};
