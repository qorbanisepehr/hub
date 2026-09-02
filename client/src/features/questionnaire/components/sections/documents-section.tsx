import { EntityDocumentsSection } from "@/components/documents/entity-documents-section";
import { useQuestionnaireDocuments } from "@/features/questionnaire/hooks/use-questionnaire-documents";

type SectionProps = {
    uuid?: string;
};

export function DocumentsSection({ uuid }: SectionProps) {
    const { documents, getDocumentsBySlugExcept } =
        useQuestionnaireDocuments(uuid);

    if (!uuid) return null;

    return (
        <EntityDocumentsSection
            uuid={uuid}
            entity="questionnaire"
            documents={documents}
            getDocumentsBySlugExcept={getDocumentsBySlugExcept}
        />
    );
}