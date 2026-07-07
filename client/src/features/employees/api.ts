import { api } from "@/lib/api";
import type { Employee, EmployeeFormData, PaginatedResponse } from "./types";

export function fetchEmployees(page = 1) {
    return api.get<PaginatedResponse<Employee>>("/employees", {
        params: { page },
    });
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
