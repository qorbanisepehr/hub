import { useReducer } from "react";
import { IconTrash } from "@tabler/icons-react";

import { Button } from "@/components/ui/button";
import { FileUploadField } from "@/components/documents";
import { EntityDocumentsSection } from "@/components/documents/entity-documents-section";
import { useEmployeeDocuments } from "@/features/employees/hooks/use-employee-documents";
import type { EmployeeDocument } from "@/features/employees/hooks/use-employee-documents";
import { EmployeeDocumentTrashModal } from "./employee-document-trash-modal";
import { EmployeeDocumentReplaceModal } from "./employee-document-replace-modal";
import {
    DOC_CATEGORY_SLUGS,
    getFieldKeyLabel,
} from "@/features/questionnaire/constants";

type SectionProps = {
    employeeId: number;
};

type DocSectionState = {
    trashOpen: boolean;
    replaceTarget: EmployeeDocument | null;
};

type DocSectionAction =
    | { type: "SET_TRASH_OPEN"; open: boolean }
    | { type: "SET_REPLACE_TARGET"; doc: EmployeeDocument | null };

function docSectionReducer(
    state: DocSectionState,
    action: DocSectionAction,
): DocSectionState {
    switch (action.type) {
        case "SET_TRASH_OPEN":
            return { ...state, trashOpen: action.open };
        case "SET_REPLACE_TARGET":
            return { ...state, replaceTarget: action.doc };
    }
}

export function DocumentsSection({ employeeId }: SectionProps) {
    const [state, dispatch] = useReducer(docSectionReducer, {
        trashOpen: false,
        replaceTarget: null,
    });

    const uuid = String(employeeId);
    const { documents, getDocumentsBySlugExcept, capabilities } =
        useEmployeeDocuments(employeeId);

    return (
        <EntityDocumentsSection
            uuid={uuid}
            entity="employees"
            documents={documents}
            getDocumentsBySlugExcept={getDocumentsBySlugExcept}
            replaceEnabled={capabilities.replace}
            onReplace={(doc) =>
                dispatch({ type: "SET_REPLACE_TARGET", doc })
            }
            orphanLabel={(doc) =>
                doc.structure_name ??
                getFieldKeyLabel(doc.field_key) ??
                doc.field_key
            }
            extraFixedFields={
                <FileUploadField
                    uuid={uuid}
                    entity="employees"
                    categorySlug={DOC_CATEGORY_SLUGS.SIGNATURE_SAMPLE}
                    label="نمونه امضا"
                    maxFiles={1}
                    fieldKey="signature"
                    required
                    accept="image/jpeg,image/png,image/webp"
                    replaceEnabled={capabilities.replace}
                    onReplace={(doc) =>
                        dispatch({ type: "SET_REPLACE_TARGET", doc })
                    }
                />
            }
            headerActions={
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() =>
                        dispatch({ type: "SET_TRASH_OPEN", open: true })
                    }
                >
                    <IconTrash className="size-3.5 ms-1" />
                    سطل زباله
                </Button>
            }
            modals={
                <>
                    <EmployeeDocumentTrashModal
                        open={state.trashOpen}
                        onOpenChange={(open) =>
                            dispatch({ type: "SET_TRASH_OPEN", open })
                        }
                        employeeId={employeeId}
                    />
                    <EmployeeDocumentReplaceModal
                        open={state.replaceTarget !== null}
                        onOpenChange={(next) => {
                            if (!next)
                                dispatch({
                                    type: "SET_REPLACE_TARGET",
                                    doc: null,
                                });
                        }}
                        employeeId={employeeId}
                        doc={state.replaceTarget}
                    />
                </>
            }
        />
    );
}