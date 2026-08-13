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
    trashed: (type?: string, entityId?: string) =>
        [...documentKeys.all, "trashed", type, entityId] as const,
    categories: (type?: string) =>
        [...documentKeys.all, "categories", type] as const,
    requirements: (entity: string) =>
        [...documentKeys.all, "requirements", entity] as const,
    library: () => [...documentKeys.all, "library"] as const,
    /** Namespaced per entity so invalidation stays isolated between features. */
    entityDocuments: (entity: string, uuid: string | undefined) =>
        [`${entity}-documents`, uuid] as const,
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
    chart: () => [...roleKeys.all, "chart"] as const,
    options: () => [...roleKeys.all, "options"] as const,
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

export const cvKeys = {
    all: ["cvs"] as const,
    lists: () => [...cvKeys.all, "list"] as const,
    list: (params: Record<string, unknown>) =>
        [...cvKeys.lists(), params] as const,
    details: () => [...cvKeys.all, "detail"] as const,
    detail: (uuid: string) => [...cvKeys.details(), uuid] as const,
    bank: (params: Record<string, unknown>) =>
        [...cvKeys.all, "bank", params] as const,
    bankDetail: (id: number | string) => [...cvKeys.all, "bank", id] as const,
    documents: (uuid: string) => [`cv-documents`, uuid] as const,
};

export const settingsKeys = {
    all: ["settings"] as const,
    branding: () => [...settingsKeys.all, "branding"] as const,
};

export const formOptionKeys = {
    all: () => ["form-options"] as const,
    byGroup: (group: string, parentValue?: string, search?: string) =>
        [...formOptionKeys.all(), group, parentValue ?? "", search ?? ""] as const,
    admin: (group?: string) =>
        [...formOptionKeys.all(), "admin", group ?? "all"] as const,
};
