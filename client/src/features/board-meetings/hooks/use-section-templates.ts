import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";

import { getApiError } from "@/lib/error-utils";
import {
    clearMeetingSectionTemplate,
    fetchMeetingSectionTemplate,
    fetchSectionTemplates,
    updateMeetingSectionTemplate,
    updateSectionTemplate,
} from "../section-templates-api";

/** The domain's template, its overrides, and what will actually print. */
export function useSectionTemplates() {
    return useQuery({
        queryKey: ["board-meetings", "section-template"],
        queryFn: async () => (await fetchSectionTemplates()).data.data,
        staleTime: 30_000,
    });
}

/**
 * The template in force for one meeting.
 *
 * `enabled` is the caller's business — the endpoint is behind
 * `export-template.view`, so a screen that might render for somebody without it
 * must not ask.
 */
export function useMeetingSectionTemplate(
    meetingId: number | string | undefined,
    enabled = true,
) {
    return useQuery({
        queryKey: ["board-meetings", "section-template", String(meetingId)],
        queryFn: async () =>
            (await fetchMeetingSectionTemplate(String(meetingId))).data.data,
        enabled: enabled && meetingId !== undefined,
        staleTime: 30_000,
    });
}

/**
 * One prefix for both levels, so changing a meeting's override also refreshes
 * the domain screen's `overrides` list. Invalidating them separately would let a
 * person reading one while editing the other see a stale answer.
 */
function useInvalidateSectionTemplates() {
    const queryClient = useQueryClient();

    return () =>
        queryClient.invalidateQueries({
            queryKey: ["board-meetings", "section-template"],
        });
}

export function useUpdateSectionTemplate() {
    const invalidate = useInvalidateSectionTemplates();

    return useMutation({
        mutationFn: updateSectionTemplate,
        onSuccess: () => {
            void invalidate();
            toast.success("قالب ذخیره شد.");
        },
        onError: (error) => toast.error(getApiError(error)),
    });
}

export function useUpdateMeetingSectionTemplate(meetingId: number | string) {
    const invalidate = useInvalidateSectionTemplates();

    return useMutation({
        // Wrapped rather than passed by reference: the mutation's own argument is
        // the SECTIONS, and `meetingId` is fixed by the screen that opened it.
        mutationFn: (sections: string[]) =>
            updateMeetingSectionTemplate(meetingId, sections),
        onSuccess: () => {
            void invalidate();
            toast.success("قالب این جلسه ذخیره شد.");
        },
        onError: (error) => toast.error(getApiError(error)),
    });
}

export function useClearMeetingSectionTemplate(meetingId: number | string) {
    const invalidate = useInvalidateSectionTemplates();

    return useMutation({
        mutationFn: () => clearMeetingSectionTemplate(meetingId),
        onSuccess: () => {
            void invalidate();
            toast.success("قالب این جلسه پاک شد و از قالب دامنه پیروی می‌کند.");
        },
        onError: (error) => toast.error(getApiError(error)),
    });
}
