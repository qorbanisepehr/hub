import { api } from "@/lib/api";
import type {
    AiConnectionTestResult,
    AiModelDto,
    AiModelListResponse,
    AiModelPayload,
    AiProviderDto,
    AiProviderListParams,
    AiProviderListResponse,
    CreateAiProviderPayload,
    UpdateAiProviderPayload,
} from "./types";

/**
 * The AI registry endpoints. Models are addressed under the provider that
 * owns them, because a model row has no life of its own — the parent in the
 * path is what proves the edit is happening inside the right registry.
 */

export function fetchAiProviders(params: AiProviderListParams) {
    return api.get<AiProviderListResponse>("/ai/providers", { params });
}

/**
 * The flat model list, for a per-run picker outside Settings.
 *
 * Separate from the provider list on purpose: a picker needs one row per
 * model with its provider's name attached, and reading it off the provider
 * payload would drag `base_url`, `has_api_key` and `headers` to the browser to
 * render a dropdown of labels.
 */
export function fetchAiModels(params?: { per_page?: number }) {
    return api.get<AiModelListResponse>("/ai/models", { params });
}

export function createAiProvider(payload: CreateAiProviderPayload) {
    return api.post<{ data: AiProviderDto }>("/ai/providers", payload);
}

/**
 * Partial by design: an absent `api_key` keeps the stored credential and an
 * absent field keeps its value, so the edit form can submit one changed box
 * without reconstructing — and thereby risking — the rest.
 */
export function updateAiProvider(id: number, payload: UpdateAiProviderPayload) {
    return api.put<{ data: AiProviderDto }>(`/ai/providers/${id}`, payload);
}

export function deleteAiProvider(id: number) {
    return api.delete<{ message: string }>(`/ai/providers/${id}`);
}

export function makeAiProviderDefault(id: number) {
    return api.post<{ data: AiProviderDto }>(`/ai/providers/${id}/default`);
}

/**
 * Run one minimal prompt against the stored credential.
 *
 * Not `GET` and not a query on a read: it makes an outbound, billable call, so
 * it is an explicit verb. The optional `model` lets an administrator check a
 * name before saving it as a row.
 */
export function testAiProviderConnection(id: number, model?: string) {
    return api.post<AiConnectionTestResult>(`/ai/providers/${id}/test`, null, {
        params: model ? { model } : undefined,
    });
}

export function createAiModel(providerId: number, payload: AiModelPayload) {
    return api.post<{ data: AiModelDto }>(`/ai/providers/${providerId}/models`, payload);
}

export function updateAiModel(
    providerId: number,
    modelId: number,
    payload: AiModelPayload,
) {
    return api.put<{ data: AiModelDto }>(
        `/ai/providers/${providerId}/models/${modelId}`,
        payload,
    );
}

export function deleteAiModel(providerId: number, modelId: number) {
    return api.delete<{ message: string }>(
        `/ai/providers/${providerId}/models/${modelId}`,
    );
}

/**
 * Promote one of a provider's models to be the one runs fall back to.
 *
 * Descriptor-only, exactly like the provider's own "set as default": no body,
 * and the previous default is demoted server-side.
 */
export function makeAiModelDefault(providerId: number, modelId: number) {
    return api.post<{ data: AiModelDto }>(
        `/ai/providers/${providerId}/models/${modelId}/default`,
    );
}
