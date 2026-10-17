import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";

import { exportTemplateKeys } from "@/lib/query-keys";
import { getApiError } from "@/lib/error-utils";
import {
    deleteWordTemplate,
    fetchWordTemplateFields,
    fetchWordTemplates,
    updateWordTemplate,
    uploadWordTemplate,
} from "../word-templates-api";

/**
 * The uploaded Word templates a domain can export with.
 *
 * Long-lived: the library changes when an administrator changes it, not from one
 * render to the next, and a picker that refetches on every render flickers.
 */
export function useWordTemplates(domain = "boardmeeting") {
    return useQuery({
        queryKey: exportTemplateKeys.list(domain),
        // The envelope's `data` is unwrapped here so no caller has to know the
        // API's response shape; only the domain comes back alongside it, and the
        // library already knows which domain it asked for.
        queryFn: async () => (await fetchWordTemplates(domain)).data.data
            .templates,
        // Nothing here is secret enough to be worth a spinner on every visit,
        // but a library is small and rarely changes.
        staleTime: 30_000,
    });
}

/**
 * The cheat sheet: every name a template may refer to, grouped by row.
 *
 * Fetched for its own sake rather than beside the library, because it answers a
 * different question and is read by a different screen - the person authoring a
 * file needs the names, the person exporting needs the list.
 */
export function useWordTemplateFields(domain = "boardmeeting") {
    return useQuery({
        queryKey: exportTemplateKeys.fields(domain),
        queryFn: async () => (await fetchWordTemplateFields(domain)).data.data,
        staleTime: 5 * 60_000,
    });
}

export function useUploadWordTemplate(domain = "boardmeeting") {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: uploadWordTemplate,
        onSuccess: () => {
            void queryClient.invalidateQueries({
                queryKey: exportTemplateKeys.list(domain),
            });

            toast.success("قالب بارگذاری شد.");
        },
        // The server's own reason, verbatim: it knows which part of the archive
        // it refused and why, and replacing that with "upload failed" would throw
        // away the only sentence that tells the person what to change.
        onError: (error) => toast.error(getApiError(error)),
    });
}

export function useUpdateWordTemplate(domain = "boardmeeting") {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({
            slug,
            ...payload
        }: { slug: string; name?: string; is_default?: boolean }) =>
            updateWordTemplate(slug, payload),
        onSuccess: () => {
            void queryClient.invalidateQueries({
                queryKey: exportTemplateKeys.list(domain),
            });

            toast.success("قالب به‌روزرسانی شد.");
        },
        onError: (error) => toast.error(getApiError(error)),
    });
}

export function useDeleteWordTemplate(domain = "boardmeeting") {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: deleteWordTemplate,
        onSuccess: () => {
            void queryClient.invalidateQueries({
                queryKey: exportTemplateKeys.list(domain),
            });

            toast.success("قالب حذف شد.");
        },
        onError: (error) => toast.error(getApiError(error)),
    });
}
