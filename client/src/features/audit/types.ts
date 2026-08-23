export type AuditCategory =
    | "auth"
    | "authorization"
    | "employee"
    | "document"
    | "questionnaire"
    | "workflow";

export type AuditActorType = "user" | "system";

export interface AuditActorRole {
    id: number | null;
    name: string | null;
}

export interface AuditLog {
    id: number;
    event_id: string;
    event: string;
    category: AuditCategory;
    actor: {
        type: AuditActorType;
        id: number | null;
        name: string | null;
        avatar_url: string | null;
        display_name: string | null;
        role: AuditActorRole;
    };
    subject: {
        type: string | null;
        id: number | string | null;
    };
    description: string | null;
    ip_address: string | null;
    created_at: string;
}

export interface AuditLogDetail extends AuditLog {
    subject: AuditLog["subject"] & {
        snapshot: Record<string, unknown> | null;
    };
    changes: {
        old: Record<string, unknown> | null;
        new: Record<string, unknown> | null;
    };
    metadata: Record<string, unknown> | null;
    request: {
        ip_address: string | null;
        user_agent: string | null;
        url: string | null;
        method: string | null;
        request_id: string | null;
        trace_id: string | null;
    };
}

export interface AuditRetentionPolicy {
    id: number;
    name: string;
    category: AuditCategory | null;
    event: string | null;
    retention_days: number;
    archive_after_days: number | null;
    archive_enabled: boolean;
    delete_after_archive: boolean;
    is_active: boolean;
    created_at: string;
    updated_at: string;
}

export interface AuditStats {
    total: number;
    by_category: Record<string, number>;
    by_event: Record<string, number>;
}

export interface AuditLogListParams {
    page?: number;
    per_page?: number;
    sort?: string;
    category?: AuditCategory | "";
    event?: string;
    actor_type?: AuditActorType | "";
    actor_id?: number;
    subject_type?: string;
    subject_id?: number | string;
    date_from?: string;
    date_to?: string;
    search?: string;
}
