export type PublicFormOption = {
    value: string;
    label: string;
    parent_value: string | null;
    group_label: string | null;
};

export type FormOption = PublicFormOption & {
    id: number;
    group: string;
    sort_order: number;
    is_active: boolean;
    meta: Record<string, unknown> | null;
};

export type FormOptionsMap = Record<string, PublicFormOption[]>;

export type StoreFormOptionData = {
    group: string;
    value: string;
    label: string;
    parent_value?: string | null;
    group_label?: string | null;
    meta?: Record<string, unknown> | null;
    sort_order?: number;
    is_active?: boolean;
};
