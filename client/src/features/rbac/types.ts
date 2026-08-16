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
    resource: string | null;
    action: string | null;
    policy_resource: string | null;
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
    access_rules?: AccessRule[];
    children?: Role[];
    created_at: string;
    updated_at: string;
};

export type AccessRuleEffect = "allow" | "deny";

export type AccessRulePolicyCondition = {
    attribute: string;
    operator: string;
    value_source: string;
    value?: unknown;
};

export type AccessRulePolicy = {
    all?: AccessRulePolicyCondition[];
    any?: AccessRulePolicyCondition[];
    not?: AccessRulePolicyCondition[];
};

export type AccessRule = {
    id: number;
    permission_id: number;
    permission?: {
        id: number;
        name: string;
        display_name: string;
        resource: string | null;
    } | null;
    effect: AccessRuleEffect;
    priority: number | null;
    policy: AccessRulePolicy | null;
    is_active: boolean;
};

export type AccessRuleInput = {
    permission_id: number;
    effect: AccessRuleEffect;
    priority?: number | null;
    is_active?: boolean;
    policy?: AccessRulePolicy | null;
};

export type RuleBuilderAttribute = {
    key: string;
    label: string;
    type: string;
    queryable: boolean;
    operators: string[];
};

export type RuleBuilderResourceType = {
    key: string;
    label: string;
    attributes: RuleBuilderAttribute[];
};

export type RuleBuilderOperator = { key: string; label: string };
export type RuleBuilderValueSource = { key: string; label: string };

export type RuleBuilderMeta = {
    resource_types: RuleBuilderResourceType[];
    operators: RuleBuilderOperator[];
    value_sources: RuleBuilderValueSource[];
};

export type RulePreviewRequest = {
    permission: string;
    policy?: AccessRulePolicy | null;
    user_id: number;
    resource_type?: string | null;
    resource_id?: number | null;
};

export type RulePreviewResult = {
    rule_matches: boolean;
    effective: {
        allowed: boolean;
        reason: string;
        matched_rules: Array<Record<string, unknown>>;
        denied_rules: Array<Record<string, unknown>>;
        policy_results: Array<Record<string, unknown>>;
        policy_pending: boolean;
    };
};

export type UserRoleAssignment = {
    roles: Role[];
    active_role: Role | null;
};

export type RoleChartUser = {
    id: number;
    name: string;
    avatar_url: string | null;
    employee: LinkedEmployee | null;
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
    access_rules?: AccessRuleInput[];
};

export type UpdateRoleData = Partial<CreateRoleData>;

export type LinkedEmployee = {
    id: number;
    first_name: string;
    last_name: string;
    personnel_code: string | null;
};

export type UserListItem = {
    id: number;
    name: string;
    avatar_url: string | null;
    email: string;
    is_active: boolean;
    is_super_admin: boolean;
    roles: Role[];
    active_role: Role | null;
    employee: LinkedEmployee | null;
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
    employee: LinkedEmployee | null;
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
