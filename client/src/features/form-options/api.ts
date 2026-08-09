import { api } from "@/lib/api";

import type {
    FormOption,
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

export function fetchAdminFormOptions(group?: string) {
    return api.get<{ data: FormOption[] }>("/admin/form-options", {
        params: group ? { group } : {},
    });
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
