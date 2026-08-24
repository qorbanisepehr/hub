import { api } from "@/lib/api";
import type { PaginatedResponse } from "@/lib/types";

import type {
    FormOption,
    FormOptionGroup,
    FormOptionsMap,
    PublicFormOption,
    StoreFormOptionData,
} from "./types";

export function fetchFormOptions() {
    return api.get<{ data: FormOptionsMap }>("/form-options");
}

export function fetchFormOptionsByGroup(
    group: string,
    parentValue?: string,
    search?: string,
) {
    return api.get<{ data: PublicFormOption[] }>(`/form-options/${group}`, {
        params: {
            parent_value: parentValue || undefined,
            search: search || undefined,
        },
    });
}

/**
 * Resolve stored values back to their option rows — inactive options
 * included — so saved records keep displaying proper labels after an option
 * is deactivated.
 */
export function fetchFormOptionsByValues(group: string, values: string[]) {
    return api.get<{ data: PublicFormOption[] }>(
        `/form-options/${group}/resolve`,
        { params: { values: values.join(",") } },
    );
}

export function fetchAdminFormOptions(group?: string, page = 1, perPage = 20) {
    return api.get<PaginatedResponse<FormOption>>("/admin/form-options", {
        params: {
            group: group || undefined,
            page,
            per_page: perPage,
        },
    });
}

export function fetchAdminFormOptionGroups() {
    return api.get<{ data: FormOptionGroup[] }>("/admin/form-options/groups");
}

export function storeFormOption(data: StoreFormOptionData) {
    return api.post<{ data: FormOption }>("/admin/form-options", data);
}

export function updateFormOption(id: number, data: Partial<StoreFormOptionData>) {
    return api.put<{ data: FormOption }>(`/admin/form-options/${id}`, data);
}

export function deleteFormOption(id: number) {
    return api.delete(`/admin/form-options/${id}`);
}

export function toggleFormOption(id: number) {
    return api.post<{ data: FormOption }>(`/admin/form-options/${id}/toggle`);
}
