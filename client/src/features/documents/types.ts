export type DocumentCategory = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    sort_order: number;
    documents_count?: number;
    created_at: string;
    updated_at: string;
};

export type Document = {
    id: number;
    document_category_id: number;
    category?: DocumentCategory;
    original_name: string;
    mime_type: string;
    file_size: number;
    file_size_formatted: string;
    notes: string | null;
    url: string | null;
    thumbnail_url: string | null;
    uploaded_by: string | null;
    created_at: string;
    updated_at: string;
    deleted_at: string | null;
};
