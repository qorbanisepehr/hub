import { useQuery } from "@tanstack/react-query";

import { documentKeys } from "@/lib/query-keys";
import { fetchDocumentRequirements } from "@/features/documents/api";
import type { DocumentRequirement } from "@/features/documents/types";

const REQUIREMENT_ENTITIES = new Set(["questionnaire", "cv", "employees"]);

/**
 * Load the per-domain document requirements (slug → requirement) so upload
 * fields can resolve the placement section and validate files client-side.
 */
export function useDocumentRequirements(entity: string) {
    return useQuery({
        queryKey: documentKeys.requirements(entity),
        queryFn: () => fetchDocumentRequirements(entity),
        enabled: REQUIREMENT_ENTITIES.has(entity),
    });
}
