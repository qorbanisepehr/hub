import { api } from "@/lib/api";
import { publicApi } from "@/lib/public-api";
import type {
    DocumentCategory,
    Document,
    DocumentRequirement,
} from "./types";

export function fetchDocumentCategories(type?: string) {
    const params: Record<string, string> = {};
    if (type) params.type = type;
    return api.get<{ data: DocumentCategory[] }>("/document-categories", { params });
}

/**
 * Fetch the per-domain document requirements (slug → requirement) for an
 * entity. Grant-protected entities (questionnaire/cv) use the public client;
 * employees use the Sanctum-authenticated client.
 */
export function fetchDocumentRequirements(entity: string) {
    const client = entity === "employees" ? api : publicApi;
    return client
        .get<{ data: Record<string, DocumentRequirement> }>(
            `/${entity}/document-requirements`,
        )
        .then((r) => r.data.data);
}

export function fetchDocuments(type?: string, entityId?: string, status?: string) {
    const params: Record<string, string> = {};
    if (type) params.type = type;
    if (entityId) params.id = entityId;
    if (status) params.status = status;
    return api.get<{ data: Document[] }>("/documents", { params });
}

export function uploadDocument(
    formData: FormData,
) {
    return api.post<{ data: Document }>(
        "/documents",
        formData,
        {
            headers: { "Content-Type": "multipart/form-data" },
        },
    );
}

export function deleteDocument(documentId: number) {
    return api.delete<{ message: string }>(
        `/documents/${documentId}`,
    );
}

export function fetchTrashedDocuments(type?: string, entityId?: string) {
    const params: Record<string, string> = {};
    if (type) params.type = type;
    if (entityId) params.id = entityId;
    return api.get<{ data: Document[] }>("/documents/trash", { params });
}

export function restoreDocument(documentId: number) {
    return api.post<{ data: Document }>(
        `/documents/${documentId}/restore`,
    );
}

export function forceDeleteDocument(documentId: number) {
    return api.delete<{ message: string }>(
        `/documents/${documentId}/force`,
    );
}

/** List documents that are not attached anywhere and can be reused. */
export function fetchLibraryDocuments() {
    return api.get<{ data: Document[] }>("/documents/library");
}

/**
 * Attach a copy of a library document to a target entity. The backend always
 * creates a new Document with its own identity (the source stays untouched).
 */
export function selectFromLibrary(payload: {
    source_document_id: number;
    documentable_type: string;
    documentable_id: number;
    section_key?: string | null;
    field_key?: string | null;
    notes?: string | null;
}) {
    return api.post<{ data: Document }>("/documents/from-library", payload);
}
