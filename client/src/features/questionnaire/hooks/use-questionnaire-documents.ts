import { useEntityDocuments } from "@/hooks/use-entity-documents";
import type { EntityDocument } from "@/hooks/use-entity-documents";

export type QuestionnaireDocument = EntityDocument;

export function useQuestionnaireDocuments(uuid: string | undefined) {
    return useEntityDocuments("questionnaire", uuid);
}
