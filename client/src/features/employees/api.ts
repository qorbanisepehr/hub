import { api, FORM_DATA_HEADERS } from "@/lib/api";
import type { PaginatedResponse, PaginatedListParams } from "@/lib/types";
import type { EntityDocument } from "@/hooks/use-entity-documents";
import type { Employee, EmployeeBaseFormData } from "./types";

export type EmployeeListParams = PaginatedListParams & {
    filter?: string;
    status?: string;
    status_not?: string;
};

export function fetchEmployees(params: EmployeeListParams = {}) {
    return api.get<PaginatedResponse<Employee>>("/employees", { params });
}

export type EmployeeExportField = {
    key: string;
    label: string;
    column: string;
};

export function fetchEmployeeExportFields() {
    return api.get<{ data: EmployeeExportField[] }>(
        "/employees/export/fields",
    );
}

export type EmployeeExportPresentation = {
    headers: "key" | "label";
    calendar: "gregorian" | "persian" | "both";
    digits: "latin" | "persian";
    detailSheets: boolean;
};

export function exportEmployees(params: {
    fields?: string[];
    format?: "xlsx" | "csv" | "pdf" | "docx";
    status?: string;
    status_not?: string;
    presentation?: EmployeeExportPresentation;
}) {
    const presentation = params.presentation;

    return api.get("/employees/export", {
        params: {
            fields: (params.fields ?? []).join(","),
            format: params.format ?? "xlsx",
            status: params.status,
            status_not: params.status_not,
            headers: presentation?.headers,
            calendar: presentation?.calendar,
            digits: presentation?.digits,
            details: presentation?.detailSheets ? 1 : undefined,
        },
        responseType: "blob",
    });
}

export function fetchEmployeeExportTemplate(format: "xlsx" | "csv" = "xlsx") {
    return api.get("/employees/export-template", {
        params: { format },
        responseType: "blob",
    });
}

export function fetchEmployee(id: number) {
    return api.get<{ data: Employee }>(`/employees/${id}`);
}

/**
 * The printable employee-profile document (PDF/Word) for one employee.
 * Served by GET /employees/{id}/document, gated like the detail view.
 */
export function fetchEmployeeDocument(id: number, format: "pdf" | "docx" = "pdf") {
    return api.get(`/employees/${id}/document`, {
        params: { format },
        responseType: "blob",
    });
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
        { headers: FORM_DATA_HEADERS },
    );
}
