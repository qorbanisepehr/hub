import { isAxiosError } from "axios";

/**
 * Extract a human-readable error message from any error.
 * Handles Laravel 422 validation responses, plain error messages,
 * and unknown error types.
 */
export function getApiError(e: unknown): string {
    if (isAxiosError(e) && e.response?.data) {
        const data = e.response.data as Record<string, unknown>;

        if (data.errors && typeof data.errors === "object") {
            const messages = Object.values(data.errors)
                .filter(Array.isArray)
                .flat()
                .filter((m): m is string => typeof m === "string");

            if (messages.length > 0) {
                return messages[0]
                    .replace(/files\.\d+/g, "فایل");
            }
        }

        if (typeof data.message === "string" && data.message) {
            return data.message;
        }
    }

    if (e instanceof Error && e.message) {
        return e.message;
    }

    return "خطای ناشناخته";
}
