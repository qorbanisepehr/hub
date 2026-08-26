import { useEffect, useMemo, useRef } from "react";
import { useMutation, useQuery, useQueryClient, type QueryClient } from "@tanstack/react-query";
import { toast } from "sonner";

import { getApiError } from "@/lib/error-utils";
import { formOptionKeys } from "@/lib/query-keys";

import {
    deleteFormOption,
    fetchAdminFormOptionGroups,
    fetchAdminFormOptions,
    fetchFormOptions,
    fetchFormOptionsByGroup,
    fetchFormOptionsByValues,
    storeFormOption,
    toggleFormOption,
    updateFormOption,
} from "../api";
import type { FormOptionsMap, PublicFormOption, StoreFormOptionData } from "../types";

/**
 * Location groups are excluded from the shared dictionary map
 * (`FormOptionService::LOCATION_GROUPS` server-side) because of their size;
 * they are always served per-group.
 */
const LOCATION_GROUPS = new Set(["city", "province"]);

async function fetchAllOptionsMap(): Promise<FormOptionsMap> {
    const { data } = await fetchFormOptions();
    return data.data;
}

/**
 * Warm the shared dictionary before a route renders so option-backed fields
 * resolve synchronously on first paint instead of flashing raw stored values.
 * Failures degrade gracefully — pages fall back to their normal loading state.
 */
export function ensureFormOptions(queryClient: QueryClient): Promise<void> {
    return queryClient
        .ensureQueryData({ queryKey: formOptionKeys.all(), queryFn: fetchAllOptionsMap })
        .then(() => undefined)
        .catch(() => undefined);
}

/**
 * All groups' active options. Options rarely change, so the data is cached for
 * the whole session; admin mutations invalidate this key to refresh it.
 */
export function useFormOptions() {
    return useQuery<FormOptionsMap>({
        queryKey: formOptionKeys.all(),
        queryFn: fetchAllOptionsMap,
        staleTime: Infinity,
    });
}

/**
 * One group's active options, cached for the session. Used by form sections to
 * render option lists and build dynamic validation from the same source.
 *
 * Plain reads derive from the shared dictionary map (one request per session,
 * warmable via ensureFormOptions) so views never flash raw stored values.
 * Hierarchical location groups and filtered reads (parent_value / search) keep
 * hitting their dedicated endpoint — the city group is far too large to embed
 * in the dictionary and location filtering/searching is server-side.
 */
export function useFormOptionsByGroup(group: string, parentValue?: string, search?: string) {
    const needsDedicatedFetch =
        parentValue !== undefined || search !== undefined || LOCATION_GROUPS.has(group);

    // Both queries are always declared (rules of hooks); exactly one is
    // enabled per render.
    const shared = useQuery({
        queryKey: formOptionKeys.all(),
        queryFn: fetchAllOptionsMap,
        enabled: !needsDedicatedFetch,
        staleTime: Infinity,
        select: (map: FormOptionsMap): PublicFormOption[] => map[group] ?? [],
    });

    const dedicated = useQuery({
        queryKey: formOptionKeys.byGroup(group, parentValue, search),
        queryFn: async () => {
            const { data } = await fetchFormOptionsByGroup(group, parentValue, search);
            return data.data;
        },
        enabled: needsDedicatedFetch,
        staleTime: Infinity,
    });

    return needsDedicatedFetch ? dedicated : shared;
}

/**
 * Resolve stored values back to option rows — inactive options included —
 * for display of saved records. Disabled until a stored value is missing
 * from the group's active list; the query key is the sorted value set, so
 * repeated renders with the same values hit one cache entry.
 */
export function useResolvedFormOptions(group: string, values: string[]) {
    return useQuery<PublicFormOption[]>({
        queryKey: formOptionKeys.byValues(group, values),
        queryFn: async () => {
            const { data } = await fetchFormOptionsByValues(group, values);
            return data.data;
        },
        enabled: values.length > 0,
        staleTime: Infinity,
    });
}

/**
 * Shared plumbing for entity submit options: both the CV and the questionnaire
 * wizards need the full option map plus the province/city groups before they
 * can validate place fields. Each entity keeps a thin builder that maps the
 * fetched options into its own submit-options shape.
 */
export function useFormOptionsWithPlaces<T>(
    build: (
        formOptions: FormOptionsMap | undefined,
        province: FormOptionsMap[string] | undefined,
        city: FormOptionsMap[string] | undefined,
    ) => T | undefined,
): { submitOptions: T | undefined; optionsReady: boolean } {
    const { data: formOptions } = useFormOptions();
    const { data: provinceOptions } = useFormOptionsByGroup("province");
    const { data: cityOptions } = useFormOptionsByGroup("city");

    // The builder is kept in a ref so an inline (per-render) builder never
    // invalidates the memo; submitOptions is only rebuilt when the underlying
    // option data changes, and always through the latest builder.
    const buildRef = useRef(build);
    useEffect(() => {
        buildRef.current = build;
    });

    const submitOptions = useMemo(
        () => buildRef.current(formOptions, provinceOptions, cityOptions),
        [formOptions, provinceOptions, cityOptions],
    );

    return { submitOptions, optionsReady: submitOptions !== undefined };
}

/**
 * Server-paginated admin list for one group (location groups included when
 * explicitly requested). Returns the full paginated envelope so callers can
 * drive the data table's pagination controls from `meta`.
 */
export function useAdminFormOptions(group?: string, page = 1, perPage = 20) {
    return useQuery({
        queryKey: formOptionKeys.admin(group, { page, per_page: perPage }),
        queryFn: async () => {
            const { data } = await fetchAdminFormOptions(group, page, perPage);
            return data;
        },
    });
}

/**
 * Every stored group — location groups included — with row counts, for the
 * admin management tab's group selector. Invalidated by admin mutations via
 * the `form-options` prefix.
 */
export function useAdminFormOptionGroups() {
    return useQuery({
        queryKey: formOptionKeys.adminGroups(),
        queryFn: async () => {
            const { data } = await fetchAdminFormOptionGroups();
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
