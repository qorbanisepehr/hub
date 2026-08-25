import { useQuery } from "@tanstack/react-query";

import { documentKeys } from "@/lib/query-keys";
import { fetchDocumentRequirements } from "@/features/documents/api";

const REQUIREMENT_ENTITIES = new Set(["questionnaire", "cv", "employees"]);

/**
 * Load the document requirements for an entity: the static slug map plus
 * dynamic placement groups, so upload fields can resolve requirements and
 * validate files client-side per placement.
 */
export function useDocumentRequirements(entity: string) {
    return useQuery({
        queryKey: documentKeys.requirements(entity),
        queryFn: () => fetchDocumentRequirements(entity),
        enabled: REQUIREMENT_ENTITIES.has(entity),
    });
}
