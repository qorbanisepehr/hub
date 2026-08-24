import { formatBytes } from "@/lib/file-utils";

export type DocumentDimensions = {
    min_width?: number;
    min_height?: number;
    max_width?: number;
    max_height?: number;
    aspect_ratio?: number;
};

export type DocumentRequirement = {
    required: boolean;
    /** Minimum file count (dynamic placements carry this). */
    min_files?: number | null;
    max_files: number | null;
    field_keys: string[] | null;
    min_file_size: number | null;
    max_file_size: number | null;
    mime_types: string[] | null;
    dimensions: DocumentDimensions | null;
    section_key: string | null;
};

/** A section-owned, pattern-keyed requirement group for dynamic placements. */
export type DynamicDocumentRequirementGroup = {
    /** Section owning the placement, e.g. "dependents". */
    section_key: string;
    /** Field-key regex source the group applies to, e.g. "^dependent-(\\d+)$". */
    pattern: string;
    /** Category slug → requirement shared by every placement matching the pattern. */
    requirements: Record<string, DocumentRequirement>;
};

export type DocumentRequirementsEnvelope = {
    requirements: Record<string, DocumentRequirement>;
    dynamicGroups: DynamicDocumentRequirementGroup[];
};

/** Compiled pattern cache — patterns are stable per session, keyed by source. */
const patternCache = new Map<string, RegExp | null>();

function compilePattern(source: string): RegExp | null {
    let regex = patternCache.get(source);
    if (regex === undefined) {
        try {
            regex = new RegExp(source);
        } catch {
            regex = null;
        }
        patternCache.set(source, regex);
    }
    return regex;
}

/**
 * Resolve the requirement for a specific placement. Mirrors the backend:
 * a matching dynamic group takes precedence and blocks the static map,
 * which is only consulted when no group matches (or no placement is given).
 */
export function resolvePlacementRequirement(
    envelope: DocumentRequirementsEnvelope | null | undefined,
    categorySlug: string,
    placement?: { sectionKey?: string; fieldKey?: string },
): DocumentRequirement | null {
    if (!envelope) return null;

    const { sectionKey, fieldKey } = placement ?? {};
    if (sectionKey != null || fieldKey != null) {
        for (const group of envelope.dynamicGroups) {
            const sectionMatches =
                sectionKey == null || group.section_key === sectionKey;
            const fieldMatches =
                (fieldKey != null &&
                    compilePattern(group.pattern)?.test(fieldKey)) ||
                (fieldKey == null && sectionKey != null);
            if (sectionMatches && fieldMatches) {
                return group.requirements[categorySlug] ?? null;
            }
        }
    }

    return envelope.requirements[categorySlug] ?? null;
}

export type DocumentCategory = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    sort_order: number;
    parent_id: number | null;
    type: string;
    children?: DocumentCategory[];
    documents_count?: number;
    created_at?: string;
    updated_at?: string;
};

export type Document = {
    id: number;
    document_id?: number;
    uuid?: string;
    documentable_type: string;
    documentable_id: number;
    document_category_id: number;
    category?: DocumentCategory;
    status: "pending" | "confirmed" | "rejected";
    notes: string | null;
    meta: Record<string, unknown> | null;
    section_key: string | null;
    field_key: string | null;
    structure_name?: string | null;
    /** ASCII counterpart of structure_name, used for download file names. */
    structure_name_slug?: string | null;
    mime_type?: string;
    size?: number;
    original_name?: string;
    uploaded_by: number | null;
    uploader_name: string | null;
    serve_url?: string;
    thumbnail_url?: string;
    download_url?: string;
    url?: string;
    created_at: string;
    updated_at: string;
    deleted_at: string | null;
};

export function buildCategoryPath(
    categories: DocumentCategory[],
    categoryId: number,
): string {
    const path: string[] = [];

    function find(cats: DocumentCategory[], targetId: number): boolean {
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

    function find(cats: DocumentCategory[], targetId: number): boolean {
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

// Helper functions to access display data from a Document
export function getDocOriginalName(doc: Document): string {
    if (doc.structure_name) return doc.structure_name;
    return doc.category?.name ?? "";
}

export function getDocMimeType(doc: Document): string {
    return doc.mime_type ?? "";
}

export function getDocFileSize(doc: Document): number {
    return doc.size ?? 0;
}

export function getDocFileSizeFormatted(doc: Document): string {
    return doc.size !== undefined ? formatBytes(doc.size) : "";
}

export function getDocServeUrl(doc: Document, thumbnail = false): string {
    if (thumbnail && doc.thumbnail_url) return doc.thumbnail_url;
    if (doc.url) return doc.url;
    if (doc.serve_url) return doc.serve_url;
    const params = thumbnail ? "?thumbnail=1" : "";
    return `/api/documents/${doc.id}/serve${params}`;
}

export function getDocDownloadUrl(doc: Document): string {
    return doc.download_url ?? `/api/documents/${doc.id}/download`;
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

export function collectDocs(
    cat: DocumentCategory,
    docs: Document[],
): { doc: Document; exactCategory: string }[] {
    const result: { doc: Document; exactCategory: string }[] = [];
    for (const doc of docs) {
        if (doc.document_category_id === cat.id) {
            result.push({ doc, exactCategory: cat.name });
        }
    }
    for (const child of cat.children ?? []) {
        for (const item of collectDocs(child, docs)) {
            result.push(item);
        }
    }
    return result;
}
