export const authKeys = {
    all: ["auth"] as const,
    me: () => [...authKeys.all, "me"] as const,
    authorization: () => [...authKeys.all, "authorization"] as const,
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
    library: (employeeId: number, sectionKey?: string, fieldKey?: string) =>
        [...documentKeys.all, "library", employeeId, sectionKey, fieldKey] as const,
    /** Namespaced per entity so invalidation stays isolated between features. */
    entityDocuments: (entity: string, uuid: string | undefined) =>
        [`${entity}-documents`, uuid] as const,
};

export const roleKeys = {
    all: ["roles"] as const,
    lists: () => [...roleKeys.all, "list"] as const,
    list: (params: Record<string, unknown>) =>
        [...roleKeys.lists(), params] as const,
    // Full roles list (plain array shape). The ONLY query bound to this key
    // - consumers must go through useRoles() so cache shapes never clash.
    listAll: () => [...roleKeys.all, "all"] as const,
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
    details: () => [...userKeys.all, "detail"] as const,
    detail: (id: number) => [...userKeys.details(), id] as const,
    select: (search: string, hasEmployee?: boolean) =>
        [...userKeys.all, "select", search, hasEmployee] as const,
    roles: (id: number) => ["user-roles", id] as const,
    authorization: (id: number) => [...userKeys.all, "authorization", id] as const,
};

export const permissionKeys = {
    all: ["permissions"] as const,
    lists: () => [...permissionKeys.all, "list"] as const,
    select: (search: string) =>
        [...permissionKeys.all, "select", search] as const,
    search: (search: string) =>
        ["permissions-search", search] as const,
};

export const ruleBuilderKeys = {
    all: ["rule-builder"] as const,
    meta: () => [...ruleBuilderKeys.all, "meta"] as const,
};

export const questionnaireKeys = {
    all: ["questionnaire"] as const,
    detail: (uuid: string) => [...questionnaireKeys.all, uuid] as const,
    lists: () => [...questionnaireKeys.all, "list"] as const,
    list: (params: Record<string, unknown>) =>
        [...questionnaireKeys.lists(), params] as const,
    managementDetail: (id: number | string) =>
        [...questionnaireKeys.all, "management", id] as const,
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

export const auditKeys = {
    all: ["audit"] as const,
    logs: () => [...auditKeys.all, "logs"] as const,
    logList: (params: Record<string, unknown>) =>
        [...auditKeys.logs(), params] as const,
    logDetail: (id: number) => [...auditKeys.all, "log", id] as const,
    stats: () => [...auditKeys.all, "stats"] as const,
    retentionPolicies: () => [...auditKeys.all, "retention-policies"] as const,
    retentionPolicy: (id: number) =>
        [...auditKeys.all, "retention-policy", id] as const,
};

export const reportingKeys = {
    all: ["reports"] as const,
    attendance: (params: Record<string, unknown>) =>
        [...reportingKeys.all, "attendance", params] as const,
    unifiedInbox: (params: Record<string, unknown>) =>
        [...reportingKeys.all, "unified-inbox", params] as const,
};

export const boardMeetingKeys = {
    all: ["board-meetings"] as const,
    lists: () => [...boardMeetingKeys.all, "list"] as const,
    list: (params: Record<string, unknown>) =>
        [...boardMeetingKeys.lists(), params] as const,
    details: () => [...boardMeetingKeys.all, "detail"] as const,
    detail: (id: number | string) =>
        [...boardMeetingKeys.details(), id] as const,
    documents: (meetingId: number | string) =>
        ["board-meeting-documents", meetingId] as const,
    /** The bulk picker: meetings still waiting for a first summary. */
    summaryCandidates: (params: Record<string, unknown>) =>
        ["board-meeting-summary-candidates", params] as const,
    /** The bulk runs still producing, so the table page can offer to stop them. */
    liveSummaryBatches: () =>
        [...boardMeetingKeys.all, "summary-batches"] as const,
};

export const tagKeys = {
    all: ["tags"] as const,
    lists: () => [...tagKeys.all, "list"] as const,
    list: (params: Record<string, unknown>) =>
        [...tagKeys.lists(), params] as const,
    options: () => [...tagKeys.all, "options"] as const,
};

export const formOptionKeys = {
    all: () => ["form-options"] as const,
    byGroup: (group: string, parentValue?: string, search?: string) =>
        [...formOptionKeys.all(), group, parentValue ?? "", search ?? ""] as const,
    byValues: (group: string, values: string[]) =>
        [
            ...formOptionKeys.all(),
            group,
            "resolve",
            values.toSorted().join(","),
        ] as const,
    admin: (group?: string, params?: Record<string, unknown>) =>
        [
            ...formOptionKeys.all(),
            "admin",
            group ?? "all",
            params ?? {},
        ] as const,
    adminGroups: () =>
        [...formOptionKeys.all(), "admin-groups"] as const,
};

export const notificationKeys = {
    all: ["notifications"] as const,
    lists: () => [...notificationKeys.all, "list"] as const,
    list: (params: Record<string, unknown>) =>
        [...notificationKeys.lists(), params] as const,
    unreadCount: () => [...notificationKeys.all, "unread-count"] as const,
};

export const aiKeys = {
    all: ["ai"] as const,
    providers: () => [...aiKeys.all, "providers"] as const,
    providerList: (params: Record<string, unknown>) =>
        [...aiKeys.providers(), params] as const,
    agents: () => [...aiKeys.all, "agents"] as const,
    agentList: (params: Record<string, unknown>) =>
        [...aiKeys.agents(), params] as const,
    /** The flat, cross-provider list a per-run model picker reads. */
    models: () => [...aiKeys.all, "models"] as const,
};

export const exportTemplateKeys = {
    all: ["export-templates"] as const,
    list: (domain: string) =>
        [...exportTemplateKeys.all, "list", domain] as const,
    /**
     * The names a template may contain. A property of the DOMAIN, not of the
     * current library, so it is keyed separately from the list — uploading a file
     * must not make it look like the vocabulary changed.
     */
    fields: (domain: string) =>
        [...exportTemplateKeys.all, "fields", domain] as const,
};
