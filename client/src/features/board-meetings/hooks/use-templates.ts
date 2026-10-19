import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";

import { exportTemplateKeys } from "@/lib/query-keys";
import { getApiError } from "@/lib/error-utils";
import {
    deleteTemplate,
    fetchTemplateFields,
    fetchTemplates,
    updateTemplate,
    uploadTemplate,
} from "../templates-api";

/**
 * The uploaded Word templates a domain can export with.
 *
 * Long-lived: the library changes when an administrator changes it, not from one
 * render to the next, and a picker that refetches on every render flickers.
 */
export function useTemplates(domain = "boardmeeting") {
    return useQuery({
        queryKey: exportTemplateKeys.list(domain),
        // The envelope's `data` is unwrapped here so no caller has to know the
        // API's response shape; only the domain comes back alongside it, and the
        // library already knows which domain it asked for.
        queryFn: async () => (await fetchTemplates(domain)).data.data
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
export function useTemplateFields(domain = "boardmeeting") {
    return useQuery({
        queryKey: exportTemplateKeys.fields(domain),
        queryFn: async () => (await fetchTemplateFields(domain)).data.data,
        staleTime: 5 * 60_000,
    });
}

export function useUploadTemplate(domain = "boardmeeting") {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: uploadTemplate,
        // The upload response carries WHAT THE FILE TURNED OUT TO BE, and this is
        // the only moment anybody can do anything about it. The export is where the
        // answer becomes visible otherwise — a template with `{{content}}` receives
        // the meeting and one without it does not — and by then it is a document
        // somebody has to open to find out they got the other kind.
        //
        // So it is said out loud rather than filed away. A person who uploaded a
        // letterhead expecting their minutes under it finds out here, not in Word.
        onSuccess: (response) => {
            void queryClient.invalidateQueries({
                queryKey: exportTemplateKeys.list(domain),
            });

            const inspection = response.data.inspection;

            toast.success(
                inspection?.kind === "frame"
                    ? "قالب بارگذاری شد — سند تولیدی در جای نشان‌داده‌شده نوشته می‌شود."
                    : "قالب بارگذاری شد — فقط نام‌های داخل آن پر می‌شوند و سندی ساخته نمی‌شود.",
            );
        },
        // The server's own reason, verbatim: it knows which part of the archive
        // it refused and why, and replacing that with "upload failed" would throw
        // away the only sentence that tells the person what to change.
        onError: (error) => toast.error(getApiError(error)),
    });
}

export function useUpdateTemplate(domain = "boardmeeting") {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({
            slug,
            ...payload
        }: { slug: string; name?: string; is_default?: boolean }) =>
            updateTemplate(slug, payload),
        onSuccess: () => {
            void queryClient.invalidateQueries({
                queryKey: exportTemplateKeys.list(domain),
            });

            toast.success("قالب به‌روزرسانی شد.");
        },
        onError: (error) => toast.error(getApiError(error)),
    });
}

export function useDeleteTemplate(domain = "boardmeeting") {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: deleteTemplate,
        onSuccess: () => {
            void queryClient.invalidateQueries({
                queryKey: exportTemplateKeys.list(domain),
            });

            toast.success("قالب حذف شد.");
        },
        onError: (error) => toast.error(getApiError(error)),
    });
}
