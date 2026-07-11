import { api } from "@/lib/api";
import type { DocumentCategory, Document } from "./types";

export function fetchDocumentCategories(type?: string) {
    const params = type ? { type } : {};
    return api.get<{ data: DocumentCategory[] }>("/document-categories", { params });
}

export function fetchDocuments(employeeId: number) {
    return api.get<{ data: Document[] }>(
        `/employees/${employeeId}/documents`,
    );
}

export function uploadDocument(
    employeeId: number,
    formData: FormData,
) {
    return api.post<{ data: Document }>(
        `/employees/${employeeId}/documents`,
        formData,
        {
            headers: { "Content-Type": "multipart/form-data" },
        },
    );
}

export function deleteDocument(documentId: number) {
    return api.delete<{ message: string }>(
        `/employees/documents/${documentId}`,
    );
}

export function fetchTrashedDocuments(employeeId: number) {
    return api.get<{ data: Document[] }>(
        `/employees/${employeeId}/documents/trash`,
    );
}

export function restoreDocument(documentId: number) {
    return api.post<{ data: Document }>(
        `/employees/documents/${documentId}/restore`,
    );
}

export function forceDeleteDocument(documentId: number) {
    return api.delete<{ message: string }>(
        `/employees/documents/${documentId}/force`,
    );
}
