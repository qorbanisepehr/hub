export type DocumentCategory = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    sort_order: number;
    parent_id: number | null;
    children?: DocumentCategory[];
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

export function buildCategoryPath(
    categories: DocumentCategory[],
    categoryId: number,
): string {
    const path: string[] = [];

    function find(
        cats: DocumentCategory[],
        targetId: number,
    ): boolean {
        for (const cat of cats) {
            if (cat.id === targetId) {
                path.unshift(cat.name);
                return true;
            }
            if (cat.children && find(cat.children, targetId)) {
                path.unshift(cat.name);
                return true;
            }
        }
        return false;
    }

    find(categories, categoryId);
    return path.join(" > ");
}

export function buildParentPath(
    categories: DocumentCategory[],
    categoryId: number,
): string {
    const path: string[] = [];

    function find(
        cats: DocumentCategory[],
        targetId: number,
    ): boolean {
        for (const cat of cats) {
            if (cat.id === targetId) {
                return true;
            }
            if (cat.children && find(cat.children, targetId)) {
                path.unshift(cat.name);
                return true;
            }
        }
        return false;
    }

    find(categories, categoryId);
    return path.join(" > ");
}

export function getExactCategoryName(
    categories: DocumentCategory[],
    categoryId: number,
): string {
    function find(cats: DocumentCategory[], targetId: number): string | null {
        for (const cat of cats) {
            if (cat.id === targetId) return cat.name;
            if (cat.children) {
                const found = find(cat.children, targetId);
                if (found) return found;
            }
        }
        return null;
    }

    return find(categories, categoryId) ?? "";
}

export function getTopLevelCategoryName(
    categories: DocumentCategory[],
    categoryId: number,
): string {
    for (const cat of categories) {
        if (cat.id === categoryId) return cat.name;
        if (cat.children) {
            for (const child of cat.children) {
                if (child.id === categoryId) return cat.name;
                if (child.children) {
                    for (const grand of child.children) {
                        if (grand.id === categoryId) return cat.name;
                    }
                }
            }
        }
    }
    return "سایر";
}
