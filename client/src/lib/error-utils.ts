import { isAxiosError } from "axios";

export function getApiError(e: unknown): string | null {
    if (isAxiosError<{ message?: string }>(e) && e.response?.data?.message) {
        return e.response.data.message;
    }

    return null;
}
