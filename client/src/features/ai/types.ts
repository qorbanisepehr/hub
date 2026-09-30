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

export interface AiModelDto {
    id: number;
    provider_id: number;
    /**
     * The provider's own name, present only where the relation is loaded — the
     * flat per-run list. A dropdown cannot let somebody choose between two
     * gateways that both offer the same model, so this is what makes the list
     * usable; it is absent from the nested Settings payload, which already has
     * the provider it hangs off.
     */
    provider_name?: string | null;
    /** The name the provider itself knows the model by, not this row's id. */
    model_name: string;
    label: string | null;
    /** `label` when set, else `model_name` — what pickers should show. */
    display_label: string;
    capabilities: AiCapability[];
    is_active: boolean;
}

export type AiModelListResponse = PaginatedResponse<AiModelDto>;

export interface AiProviderDto {
    id: number;
    /**
     * The provider's identity: the SDK's config key and what a run's
     * attribution records. Frozen after creation, so the form never offers
     * to change it.
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
    id: number;
    /** The agent's identity in every caller; not editable here. */
    key: string;
    label: string | null;
    /** The consuming domain's class; not editable here. */
    agent_class: string;
    /** True when it names its own provider rather than inheriting. */
    is_bound: boolean;
    provider_id: number | null;
    provider_name: string | null;
    model_id: number | null;
    model_name: string | null;
    settings: Record<string, unknown> | null;
}

export type AiProviderListResponse = PaginatedResponse<AiProviderDto>;

export type AiAgentListResponse = PaginatedResponse<AiAgentDto>;

export interface AiProviderListParams {
    page?: number;
    per_page?: number;
    /** Whitelisted sort columns: `name` | `driver` | `created_at`. */
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
    is_default?: boolean;
}

/**
 * Every field optional, and `api_key` means "replace", never "clear". An
 * omitted key is *kept* — the server cannot send the stored one back, so an
 * untouched form has nothing to resubmit and clearing on absence would
 * destroy a working credential on every unrelated edit. Deletion is the
 * explicit `clearApiKey` flag.
 */
export interface UpdateAiProviderPayload {
    driver?: AiDriver;
    base_url?: string | null;
    api_key?: string | null;
    clear_api_key?: boolean;
    is_active?: boolean;
    is_default?: boolean;
}

export interface AiModelPayload {
    model_name: string;
    label?: string | null;
    capabilities?: AiCapability[];
    is_active?: boolean;
}

/** Both ids nullable, and null means "inherit the active default". */
export interface AiAgentBindingPayload {
    provider_id?: number | null;
    model_id?: number | null;
}

/** The result of a successful connection probe. */
export interface AiConnectionTestResult {
    ok: boolean;
    model: string | null;
    reply: string;
    tokens: number | null;
}
