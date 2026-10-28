import type { PaginatedResponse } from "@/lib/types";

/**
 * HTTP contracts for the AI registry endpoints (mirror the API resources).
 *
 * The vocabulary here is the server's, deliberately: `driver` and
 * `capability` are the laravel/ai names, so a name that appears in a gateway
 * error message is the name that appears in this form. Translating them into
 * friendlier words here would mean a user searching a provider's own docs
 * for a term the form never shows.
 */

/** The laravel/ai driver a provider row speaks through. */
export type AiDriver =
    | "ollama"
    | "openai-compatible"
    | "openai"
    | "anthropic"
    | "gemini"
    | "openrouter";

/**
 * What a model can be asked to do. Stored per model so an agent binding can
 * be checked against the model it points at, instead of being discovered on
 * the first paid request.
 */
export type AiCapability =
    | "text"
    | "structured-output"
    | "embeddings"
    | "vision"
    | "transcription";

/**
 * The drivers the form offers, with the Persian label each one gets.
 *
 * The label is presentation, so it lives on the client; the *value* is the
 * wire contract and comes from the server's enum. A driver the server adds
 * later shows up in the raw select rather than disappearing.
 */
export const AI_DRIVERS: ReadonlyArray<{
    value: AiDriver;
    label: string;
    /** Ollama needs no credential, so the key field is optional for it. */
    keyless: boolean;
    /** Whether a URL makes sense; the hosted drivers bring their own. */
    hasBaseUrl: boolean;
}> = [
    { value: "ollama", label: "Ollama (محلی)", keyless: true, hasBaseUrl: true },
    { value: "openai-compatible", label: "سازگار با OpenAI", keyless: false, hasBaseUrl: true },
    { value: "openai", label: "OpenAI", keyless: false, hasBaseUrl: true },
    { value: "anthropic", label: "Anthropic", keyless: false, hasBaseUrl: false },
    { value: "gemini", label: "Gemini", keyless: false, hasBaseUrl: false },
    { value: "openrouter", label: "OpenRouter", keyless: false, hasBaseUrl: true },
];

export const AI_CAPABILITIES: ReadonlyArray<{
    value: AiCapability;
    label: string;
}> = [
    { value: "text", label: "متن" },
    { value: "structured-output", label: "خروجی ساختاریافته" },
    { value: "embeddings", label: "برداری‌سازی" },
    { value: "vision", label: "تصویر" },
    { value: "transcription", label: "رونویسی" },
];

/**
 * A model, addressed by (provider, model_name) rather than by an id.
 *
 * Neither half is optional, which is the whole change: a model name is unique
 * only *within* a provider, so one name can exist under two gateways and an id
 * that silently implied the provider could not be checked. With the pair, a
 * client can hold two rows of the same name and still tell them apart — and can
 * refuse the mismatch before sending rather than discovering it as a 422.
 */
export interface AiModelDto {
    /**
     * The provider's own name, present only where the relation is loaded — the
     * flat per-run list. A dropdown cannot let somebody choose between two
     * gateways that both offer the same model, so this is what makes the list
     * usable; it is absent from the nested Settings payload, which already has
     * the provider it hangs off.
     */
    provider_name?: string | null;
    /** The name the provider itself knows the model by. */
    model_name: string;
    label: string | null;
    /** `label` when set, else `model_name` — what pickers should show. */
    display_label: string;
    capabilities: AiCapability[];
    is_active: boolean;
}

export type AiModelListResponse = PaginatedResponse<AiModelDto>;

export interface AiProviderDto {
    /**
     * The provider's identity: the SDK's config key, the URL segment that
     * addresses it, and what a run's attribution records. There is no `id`
     * beside it — the name *is* the key the row is stored under, so a second
     * identifier would be one more thing to keep in step with it for no gain.
     *
     * Frozen after creation, so the form never offers to change it.
     */
    name: string;
    driver: AiDriver;
    base_url: string | null;
    /**
     * Whether a credential is stored — never the credential itself. The
     * server never sends the key back, which is why the edit form starts with
     * an empty box and a "stored" marker rather than the stored value.
     */
    has_api_key: boolean;
    headers: Record<string, string> | null;
    is_active: boolean;
    is_default: boolean;
    models: AiModelDto[];
}

export interface AiAgentDto {
    /** The agent's identity in every caller; not editable here. */
    key: string;
    label: string | null;
    /** The consuming domain's class; not editable here. */
    agent_class: string;
    /** True when it names its own binding rather than inheriting. */
    is_bound: boolean;
    provider: string | null;
    model: string | null;
    settings: Record<string, unknown> | null;
}

export type AiProviderListResponse = PaginatedResponse<AiProviderDto>;

export type AiAgentListResponse = PaginatedResponse<AiAgentDto>;

export interface AiProviderListParams {
    page?: number;
    per_page?: number;
    /**
     * Whitelisted sort columns: `name` | `driver`.
     *
     * `created_at` is gone with the columns it named. A document has no
     * timestamp of its own, and sorting configuration by when a file happened
     * to be rewritten is not a question worth answering. A table still offering
     * that header falls back to the default order rather than erroring.
     */
    sort?: string;
    order?: "asc" | "desc";
    filter?: string;
}

export interface AiAgentListParams {
    page?: number;
    per_page?: number;
    sort?: string;
    order?: "asc" | "desc";
    filter?: string;
}

export interface CreateAiProviderPayload {
    name: string;
    driver: AiDriver;
    base_url?: string | null;
    api_key?: string | null;
    is_active?: boolean;
}

/**
 * Every field optional, and `api_key` means "replace", never "clear". An
 * omitted key is *kept* — the server cannot send the stored one back, so an
 * untouched form has nothing to resubmit and clearing on absence would
 * destroy a working credential on every unrelated edit. Deletion is the
 * explicit `clearApiKey` flag.
 *
 * `driver` is deliberately absent, and that is not an oversight. The provider
 * decides the protocol every stored credential was issued for, so it is frozen
 * after creation — but the server *ignores* a driver sent back rather than
 * refusing the request, precisely because this dialog echoes the row it was
 * given and a rejected field would break editing a base URL entirely. So: not
 * sent here, and a stale copy left in the object is harmless.
 */
export interface UpdateAiProviderPayload {
    base_url?: string | null;
    api_key?: string | null;
    clear_api_key?: boolean;
    is_active?: boolean;
}

export interface AiModelPayload {
    model_name: string;
    label?: string | null;
    capabilities?: AiCapability[];
    is_active?: boolean;
}

/**
 * The binding, as the two names it is stored as. Both null means "inherit the
 * active default", and they are sent independently: a model with no provider is
 * the legitimate "prefer this model, on whatever gateway I'd resolve to" —
 * refusing to let the two be set one at a time is what made the old id pair
 * tedious to use.
 */
export interface AiAgentBindingPayload {
    provider?: string | null;
    model?: string | null;
}

/** The result of a successful connection probe. */
export interface AiConnectionTestResult {
    ok: boolean;
    model: string | null;
    reply: string;
    tokens: number | null;
}
