export const authKeys = {
    all: ["auth"] as const,
    me: () => [...authKeys.all, "me"] as const,
};

export const employeeKeys = {
    all: ["employees"] as const,
    lists: () => [...employeeKeys.all, "list"] as const,
    list: (params: Record<string, unknown>) =>
        [...employeeKeys.lists(), params] as const,
    details: () => [...employeeKeys.all, "detail"] as const,
    detail: (id: number) => [...employeeKeys.details(), id] as const,
    select: (search: string) =>
        [...employeeKeys.all, "select", search] as const,
};

export const documentKeys = {
    all: ["documents"] as const,
    lists: () => [...documentKeys.all, "list"] as const,
    list: (params?: Record<string, string>) =>
        [...documentKeys.lists(), params] as const,
    trashed: (type?: string, recordKey?: string) =>
        [...documentKeys.all, "trashed", type, recordKey] as const,
    categories: (type?: string) =>
        [...documentKeys.all, "categories", type] as const,
};

export const roleKeys = {
    all: ["roles"] as const,
    lists: () => [...roleKeys.all, "list"] as const,
    list: (params: Record<string, unknown>) =>
        [...roleKeys.lists(), params] as const,
    details: () => [...roleKeys.all, "detail"] as const,
    detail: (id: number) => [...roleKeys.details(), id] as const,
    select: (search: string) =>
        [...roleKeys.all, "select", search] as const,
    filterOptions: () => [...roleKeys.all, "filter-options"] as const,
};

export const userKeys = {
    all: ["users"] as const,
    lists: () => [...userKeys.all, "list"] as const,
    list: (params: Record<string, unknown>) =>
        [...userKeys.lists(), params] as const,
    allList: () => [...userKeys.all, "all"] as const,
    details: () => [...userKeys.all, "detail"] as const,
    detail: (id: number) => [...userKeys.details(), id] as const,
    select: (search: string, hasEmployee?: boolean) =>
        [...userKeys.all, "select", search, hasEmployee] as const,
    roles: (id: number) => ["user-roles", id] as const,
};

export const permissionKeys = {
    all: ["permissions"] as const,
    lists: () => [...permissionKeys.all, "list"] as const,
    select: (search: string) =>
        [...permissionKeys.all, "select", search] as const,
    search: (search: string) =>
        ["permissions-search", search] as const,
};
