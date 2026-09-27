/**
 * A single notification as served by the user-scoped notifications API
 * (NotificationResource). `type` is the canonical dot-notation business
 * occurrence ("questionnaire.submitted") — the frontend only presents
 * title/body and never infers meaning from the raw identifier
 * (architecture §22, §53).
 */
export interface AppNotification {
    id: string;
    type: string;
    title: string;
    body: string | null;
    data: Record<string, unknown>;
    read_at: string | null;
    created_at: string;
}

export type NotificationListParams = {
    page?: number;
    per_page?: number;
    /**
     * Boolean unread filter: `true` = unread only, `false` = read only,
     * `undefined` = every notification (newest first).
     */
    unread?: boolean;
};