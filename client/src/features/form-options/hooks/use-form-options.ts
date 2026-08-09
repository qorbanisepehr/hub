import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";

import { getApiError } from "@/lib/error-utils";
import { formOptionKeys } from "@/lib/query-keys";

import {
    deleteFormOption,
    fetchAdminFormOptions,
    fetchFormOptions,
    fetchFormOptionsByGroup,
    storeFormOption,
    toggleFormOption,
    updateFormOption,
} from "../api";
import type { FormOptionsMap, StoreFormOptionData } from "../types";

/**
 * All groups' active options. Options rarely change, so the data is cached for
 * the whole session; admin mutations invalidate this key to refresh it.
 */
export function useFormOptions() {
    return useQuery<FormOptionsMap>({
        queryKey: formOptionKeys.all(),
        queryFn: async () => {
            const { data } = await fetchFormOptions();
            return data.data;
        },
        staleTime: Infinity,
    });
}

/**
 * One group's active options, cached for the session. Used by form sections to
 * render option lists and build dynamic validation from the same source.
 * Location groups pass the selected parent value to fetch only its children;
 * the optional search term filters by label (used by searchable comboboxes).
 */
export function useFormOptionsByGroup(group: string, parentValue?: string, search?: string) {
    return useQuery({
        queryKey: formOptionKeys.byGroup(group, parentValue, search),
        queryFn: async () => {
            const { data } = await fetchFormOptionsByGroup(group, parentValue, search);
            return data.data;
        },
        staleTime: Infinity,
    });
}

export function useAdminFormOptions(group?: string) {
    return useQuery({
        queryKey: formOptionKeys.admin(group),
        queryFn: async () => {
            const { data } = await fetchAdminFormOptions(group);
            return data.data;
        },
    });
}

export function useFormOptionsAdmin() {
    const queryClient = useQueryClient();

    const refresh = () => {
        queryClient.invalidateQueries({ queryKey: formOptionKeys.all() });
    };

    const create = useMutation({
        mutationFn: (data: StoreFormOptionData) => storeFormOption(data),
        onSuccess: () => {
            toast.success("گزینه با موفقیت ایجاد شد");
            refresh();
        },
        onError: (error) => toast.error(getApiError(error)),
    });

    const update = useMutation({
        mutationFn: ({
            id,
            data,
        }: {
            id: number;
            data: Partial<StoreFormOptionData>;
        }) => updateFormOption(id, data),
        onSuccess: () => {
            toast.success("گزینه با موفقیت به‌روزرسانی شد");
            refresh();
        },
        onError: (error) => toast.error(getApiError(error)),
    });

    const remove = useMutation({
        mutationFn: (id: number) => deleteFormOption(id),
        onSuccess: () => {
            toast.success("گزینه حذف شد");
            refresh();
        },
        onError: (error) => toast.error(getApiError(error)),
    });

    const toggle = useMutation({
        mutationFn: (id: number) => toggleFormOption(id),
        onSuccess: () => {
            toast.success("وضعیت گزینه تغییر کرد");
            refresh();
        },
        onError: (error) => toast.error(getApiError(error)),
    });

    return { create, update, remove, toggle };
}
