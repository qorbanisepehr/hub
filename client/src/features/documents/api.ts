import { api } from "@/lib/api";
import type { DocumentCategory, Document } from "./types";

export function fetchDocumentCategories(type?: string) {
    const params: Record<string, string> = {};
    if (type) params.type = type;
    return api.get<{ data: DocumentCategory[] }>("/document-categories", { params });
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
