import { api, FORM_DATA_HEADERS } from "@/lib/api";

/** An importable entity offered by the backend definition registry. */
export type ImportEntity = {
    entity: string;
    label: string;
};

export function fetchImportEntities() {
    return api.get<{ data: ImportEntity[] }>("/imports/entities");
}

/** Download a fresh fill-and-import template for one entity. */
export function fetchImportTemplate(entity: string, format: "xlsx" | "csv") {
    return api.get(`/imports/${entity}/template`, {
        params: { format },
        responseType: "blob",
    });
}

export type ImportMapping = {
    matched: string[];
    missing_required: string[];
    unknown: string[];
};

/** One rejected row — errors keyed by column key (Laravel validator shape). */
export type ImportRejectedRow = {
    index: number;
    row: number;
    errors: Record<string, string[]>;
};

/** The dry-run plan: how the file mapped and which rows may land. */
export type ImportPlan = {
    valid: boolean;
    source: {
        format: string;
        file: string;
        schema_version: number | null;
        template: boolean;
    };
    mapping: ImportMapping;
    total: number;
    importable: number;
    rejected: ImportRejectedRow[];
};

/** The confirm outcome: what landed and what was refused. */
export type ImportOutcome = {
    created: number;
    updated: number;
    processed: number;
    rejected: ImportRejectedRow[];
    meta: Record<string, unknown>;
};

export function dryRunImport(entity: string, file: File, format: string) {
    const body = new FormData();
    body.append("file", file);
    body.append("format", format);

    return api.post<{ data: ImportPlan }>(`/imports/${entity}/dry-run`, body, {
        headers: FORM_DATA_HEADERS,
    });
}

export function confirmImport(entity: string, file: File, format: string) {
    const body = new FormData();
    body.append("file", file);
    body.append("format", format);

    return api.post<{ data: ImportOutcome }>(
        `/imports/${entity}/confirm`,
        body,
        { headers: FORM_DATA_HEADERS },
    );
}
