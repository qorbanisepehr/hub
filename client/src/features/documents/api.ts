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

export function bulkUploadDocuments(
    employeeId: number,
    formData: FormData,
    onUploadProgress?: (progressEvent: { loaded: number; total?: number }) => void,
) {
    return api.post<{
        data: {
            uploaded: Document[];
            failed: { name: string; error: string }[];
            skipped: { name: string; reason: string }[];
        };
    }>(`/employees/${employeeId}/documents/bulk`, formData, {
        headers: { "Content-Type": "multipart/form-data" },
        onUploadProgress,
    });
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

export function bulkDownloadDocuments(
    employeeId: number,
    documentIds?: number[],
) {
    return api.post(
        `/employees/${employeeId}/documents/download`,
        { document_ids: documentIds },
        { responseType: "blob" },
    );
}

export function zipUploadDocuments(
    employeeId: number,
    formData: FormData,
    onUploadProgress?: (progressEvent: { loaded: number; total?: number }) => void,
) {
    return api.post<{
        data: {
            uploaded: Document[];
            failed: { name: string; error: string }[];
            skipped: { name: string; reason: string }[];
        };
    }>(`/employees/${employeeId}/documents/zip`, formData, {
        headers: { "Content-Type": "multipart/form-data" },
        onUploadProgress,
    });
}
