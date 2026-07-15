import { api } from "@/lib/api";
import type { PaginatedResponse, PaginatedListParams } from "@/lib/types";
import type { Employee, EmployeeFormData } from "./types";

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

export function createEmployee(data: EmployeeFormData) {
    return api.post<{ data: Employee }>("/employees", data);
}

export function updateEmployee(id: number, data: Partial<EmployeeFormData>) {
    return api.put<{ data: Employee }>(`/employees/${id}`, data);
}

export function deleteEmployee(id: number) {
    return api.delete<{ message: string }>(`/employees/${id}`);
}
