import { api } from "@/lib/api";
import type { PaginatedResponse, PaginatedListParams } from "@/lib/types";
import type { EntityDocument } from "@/hooks/use-entity-documents";
import type { Employee, EmployeeBaseFormData } from "./types";

export type EmployeeListParams = PaginatedListParams & {
    filter?: string;
    status?: string;
};

export function fetchEmployees(params: EmployeeListParams = {}) {
    return api.get<PaginatedResponse<Employee>>("/employees", { params });
}

export function fetchEmployee(id: number) {
    return api.get<{ data: Employee }>(`/employees/${id}`);
}

export function createEmployee(data: EmployeeBaseFormData) {
    return api.post<{ data: Employee }>("/employees", data);
}

export function updateEmployee(
    id: number,
    data: Partial<EmployeeBaseFormData>,
) {
    return api.put<{ data: Employee }>(`/employees/${id}`, data);
}

export function deleteEmployee(id: number) {
    return api.delete<{ message: string }>(`/employees/${id}`);
}

/** Save one profile section (structural validation — draft safe). */
export function saveEmployeeSection(
    id: number,
    section: string,
    data: Record<string, unknown>,
) {
    return api.post<{ data: Employee }>(
        `/employees/${id}/sections/${section}`,
        data,
    );
}

/** Submit the profile (completion validation across all sections). */
export function submitEmployee(id: number) {
    return api.post<{ data: Employee }>(`/employees/${id}/submit`);
}

export type TrashedEmployeeDocument = {
    usage_id: number;
    structure_name: string;
    category: { id: number; name: string; slug: string } | null;
    section_key: string | null;
    field_key: string | null;
    deleted_at: string | null;
};

/** List the soft-deleted document usages of an employee. */
export function fetchEmployeeTrashedDocuments(id: number) {
    return api.get<{ data: TrashedEmployeeDocument[] }>(
        `/employees/${id}/documents/trashed`,
    );
}

/** Restore a trashed employee document usage. */
export function restoreEmployeeDocument(id: number, usageId: number) {
    return api.post<{ message: string }>(
        `/employees/${id}/documents/${usageId}/restore`,
    );
}

/** Permanently delete a trashed employee document usage. */
export function forceDeleteEmployeeDocument(id: number, usageId: number) {
    return api.delete<{ message: string }>(
        `/employees/${id}/documents/${usageId}/force`,
    );
}

/**
 * Replace a current employee document usage with a new file. The backend keeps
 * the old document soft-deleted (history) and creates a fresh Document.
 */
export function replaceEmployeeDocument(
    id: number,
    usageId: number,
    formData: FormData,
) {
    return api.post<{ data: EntityDocument; message: string }>(
        `/employees/${id}/documents/${usageId}/replace`,
        formData,
        { headers: { "Content-Type": "multipart/form-data" } },
    );
}
