import { useEntityDocuments } from "@/hooks/use-entity-documents";
import type { EntityDocument } from "@/hooks/use-entity-documents";

export type CvDocument = EntityDocument;

export function useCvDocuments(uuid: string | undefined) {
    return useEntityDocuments("cv", uuid);
}
