import { useEntityDocuments } from "@/hooks/use-entity-documents";
import type { EntityDocument } from "@/hooks/use-entity-documents";

export type EmployeeDocument = EntityDocument;

/**
 * Load the document usages attached to an employee. Unlike the grant-based
 * questionnaire/cv hooks, employees are authenticated so the underlying
 * `useEntityDocuments` switches to the Sanctum-protected API automatically.
 */
export function useEmployeeDocuments(employeeId: number | undefined) {
    return useEntityDocuments(
        "employees",
        employeeId === undefined ? undefined : String(employeeId),
    );
}
