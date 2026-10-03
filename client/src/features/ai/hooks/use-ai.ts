import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";

import { aiKeys } from "@/lib/query-keys";
import { getApiError } from "@/lib/error-utils";
import {
    createAiModel,
    createAiProvider,
    deleteAiModel,
    deleteAiProvider,
    fetchAiModels,
    fetchAiProviders,
    makeAiModelDefault,
    makeAiProviderDefault,
    testAiProviderConnection,
    updateAiModel,
    updateAiProvider,
} from "../api";
import type {
    AiModelPayload,
    AiProviderListParams,
    CreateAiProviderPayload,
    UpdateAiProviderPayload,
} from "../types";

/**
 * Server-paginated provider list. The params object is the URL-driven state
 * produced by `useDataTableUrlState`, so the query key changes exactly when
 * the URL does — the same contract the tags list follows.
 */
export function useAiProviders(params: AiProviderListParams) {
    return useQuery({
        queryKey: aiKeys.providerList(params as Record<string, unknown>),
        queryFn: async () => {
            const { data } = await fetchAiProviders(params);
            return data;
        },
    });
}

/**
 * The pickable models, for a per-run picker outside Settings.
 *
 * Long-lived on purpose: the registry changes when an administrator changes
 * it, not from one render to the next, and a dropdown that refetches on every
 * keystroke is a dropdown that flickers. `enabled` is the caller's business —
 * the endpoint is behind `ai.provider.view`, so a user without it must not
 * ask, and the picker is hidden for them instead.
 */
export function useAiModels(enabled = true) {
    return useQuery({
        queryKey: aiKeys.models(),
        queryFn: async () => {
            const { data } = await fetchAiModels({ per_page: 200 });
            return data;
        },
        enabled,
        staleTime: 5 * 60 * 1000,
    });
}

/**
 * Every mutation invalidates the whole `ai` namespace.
 *
 * Coarse on purpose, and the coarseness is the point: promoting a provider to
 * default silently demotes another row, and deleting a provider removes the
 * models an agent binding was pointing at. Narrow per-entity keys would have
 * to enumerate those hidden couplings to stay correct, and one missed edge
 * would show a user a provider list that disagrees with itself.
 */
function useInvalidateAi() {
    const queryClient = useQueryClient();

    return (message?: string) => {
        void queryClient.invalidateQueries({ queryKey: aiKeys.all });

        if (message) {
            toast.success(message);
        }
    };
}

export function useCreateAiProvider() {
    const invalidate = useInvalidateAi();

    return useMutation({
        mutationFn: (payload: CreateAiProviderPayload) => createAiProvider(payload),
        onSuccess: () => invalidate("ارائه‌دهنده ثبت شد"),
    });
}

export function useUpdateAiProvider() {
    const invalidate = useInvalidateAi();

    return useMutation({
        mutationFn: ({
            id,
            payload,
        }: {
            id: number;
            payload: UpdateAiProviderPayload;
        }) => updateAiProvider(id, payload),
        onSuccess: () => invalidate("تغییرات ذخیره شد"),
    });
}

export function useDeleteAiProvider() {
    const invalidate = useInvalidateAi();

    return useMutation({
        mutationFn: (id: number) => deleteAiProvider(id),
        onSuccess: () => invalidate("ارائه‌دهنده حذف شد"),
    });
}

export function useMakeAiProviderDefault() {
    const invalidate = useInvalidateAi();

    return useMutation({
        mutationFn: (id: number) => makeAiProviderDefault(id),
        onSuccess: () => invalidate("ارائه‌دهنده پیش‌فرض تغییر کرد"),
        onError: (error) => toast.error(getApiError(error)),
    });
}

/**
 * The connection probe.
 *
 * Its own hook, separate from the registry queries, because its result is not
 * registry state: it is a one-off answer that must not be cached, must not
 * invalidate anything, and reports failure as a *message to read* rather than
 * an error state — the whole point of the button is to be told which of the
 * three common mistakes it is.
 */
export function useTestAiProvider() {
    return useMutation({
        mutationFn: ({
            id,
            model,
        }: {
            id: number;
            model?: string;
        }) => testAiProviderConnection(id, model).then((r) => r.data),
    });
}

export function useCreateAiModel() {
    const invalidate = useInvalidateAi();

    return useMutation({
        mutationFn: ({
            providerId,
            payload,
        }: {
            providerId: number;
            payload: AiModelPayload;
        }) => createAiModel(providerId, payload),
        onSuccess: () => invalidate("مدل افزوده شد"),
        onError: (error) => toast.error(getApiError(error)),
    });
}

export function useUpdateAiModel() {
    const invalidate = useInvalidateAi();

    return useMutation({
        mutationFn: ({
            providerId,
            modelId,
            payload,
        }: {
            providerId: number;
            modelId: number;
            payload: AiModelPayload;
        }) => updateAiModel(providerId, modelId, payload),
        onSuccess: () => invalidate("مدل به‌روزرسانی شد"),
        onError: (error) => toast.error(getApiError(error)),
    });
}

export function useDeleteAiModel() {
    const invalidate = useInvalidateAi();

    return useMutation({
        mutationFn: ({ providerId, modelId }: { providerId: number; modelId: number }) =>
            deleteAiModel(providerId, modelId),
        onSuccess: () => invalidate("مدل حذف شد"),
        onError: (error) => toast.error(getApiError(error)),
    });
}

/**
 * Promote one of a provider's models to be the one runs fall back to.
 *
 * This is what the agent-binding screen used to do, and the toast says what a
 * reader needs to know: the change affects the *next* run, not the one in
 * progress, because a run resolves its model when it starts.
 */
export function useMakeAiModelDefault() {
    const invalidate = useInvalidateAi();

    return useMutation({
        mutationFn: ({ providerId, modelId }: { providerId: number; modelId: number }) =>
            makeAiModelDefault(providerId, modelId),
        onSuccess: () => invalidate("مدل پیش‌فرض تغییر کرد"),
        onError: (error) => toast.error(getApiError(error)),
    });
}
