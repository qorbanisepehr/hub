import { api } from "@/lib/api";
import type {
    AiAgentBindingPayload,
    AiAgentDto,
    AiAgentListParams,
    AiAgentListResponse,
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
 * owns them, because a model has no life of its own — the parent in the
 * path is what proves the edit is happening inside the right registry.
 *
 * Every route argument is a name rather than a row id, and that is not a
 * shorthand. A provider *is* the key its settings document is stored under, and
 * a model is a key inside that document, so an id would have been a second
 * address for something that already had one — free to disagree with it after a
 * rename, which is exactly the failure a stale id produces.
 */

export function fetchAiProviders(params: AiProviderListParams) {
    return api.get<AiProviderListResponse>("/ai/providers", { params });
}

export function fetchAiAgents(params: AiAgentListParams) {
    return api.get<AiAgentListResponse>("/ai/agents", { params });
}

/**
 * The flat model list, for a per-run picker outside Settings.
 *
 * Separate from the provider list on purpose: a picker needs one row per
 * model with its provider's name attached, and reading it off the provider
 * payload would drag `base_url`, `has_api_key` and `headers` to the browser to
 * render a dropdown of labels.
 */
export function fetchAiModels(params?: {
    page?: number;
    per_page?: number;
    sort?: string;
    order?: "asc" | "desc";
    filter?: string;
}) {
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
export function updateAiProvider(name: string, payload: UpdateAiProviderPayload) {
    return api.put<{ data: AiProviderDto }>(
        `/ai/providers/${encodeURIComponent(name)}`,
        payload,
    );
}

export function deleteAiProvider(name: string) {
    return api.delete<{ message: string }>(
        `/ai/providers/${encodeURIComponent(name)}`,
    );
}

export function makeAiProviderDefault(name: string) {
    return api.post<{ data: AiProviderDto }>(
        `/ai/providers/${encodeURIComponent(name)}/default`,
    );
}

/**
 * Run one minimal prompt against the stored credential.
 *
 * Not `GET` and not a query on a read: it makes an outbound, billable call, so
 * it is an explicit verb. The optional `model` lets an administrator check a
 * name before saving it as a row.
 */
export function testAiProviderConnection(name: string, model?: string) {
    return api.post<AiConnectionTestResult>(
        `/ai/providers/${encodeURIComponent(name)}/test`,
        null,
        { params: model ? { model } : undefined },
    );
}

export function createAiModel(providerName: string, payload: AiModelPayload) {
    return api.post<{ data: AiModelDto }>(
        `/ai/providers/${encodeURIComponent(providerName)}/models`,
        payload,
    );
}

export function updateAiModel(
    providerName: string,
    modelName: string,
    payload: AiModelPayload,
) {
    return api.put<{ data: AiModelDto }>(
        `/ai/providers/${encodeURIComponent(providerName)}/models/${encodeURIComponent(modelName)}`,
        payload,
    );
}

export function deleteAiModel(providerName: string, modelName: string) {
    return api.delete<{ message: string }>(
        `/ai/providers/${encodeURIComponent(providerName)}/models/${encodeURIComponent(modelName)}`,
    );
}

/** Binding only — `key` and `agent_class` belong to the consuming domain. */
export function updateAiAgentBinding(
    key: string,
    payload: AiAgentBindingPayload,
) {
    return api.put<{ data: AiAgentDto }>(
        `/ai/agents/${encodeURIComponent(key)}`,
        payload,
    );
}
