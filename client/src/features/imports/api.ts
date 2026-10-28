import { api, FORM_DATA_HEADERS } from "@/lib/api";

/** An importable entity offered by the backend definition registry. */
export type ImportEntity = {
    entity: string;
    label: string;
    /** Which arm of the import kernel backs the entity. */
    kind?: "tabular" | "document";
    /** Per-batch file cap for document entities (absent on tabular ones). */
    max_files?: number;
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

// ── Document-bundle arm ────────────────────────────────────────────────
// A bulk of scanned files whose filenames carry the user-defined pattern
// contract. The default below is the Archive shape; the admin may edit it
// per run (it must keep the named groups `number` and `date`).

export const ARCHIVE_DOCUMENT_PATTERN =
    "/^(?<number>\\d{4})(?:-(?<ordinal>\\d+))?\\((?<date>\\d{4}-\\d{2}-\\d{2})\\)\\.pdf$/";

export type DocumentBundleRowStatus = "ready" | "pattern" | "no_meeting" | "duplicate";

/** One file in the dry-run preview — the «what and how» of its mapping. */
export type DocumentBundlePlanRow = {
    file: string;
    status: DocumentBundleRowStatus;
    number: string | null;
    date: string | null;
    meeting: { id: number; label: string } | null;
    reason_code: string | null;
    reason: string | null;
};

/** The dry-run plan: per-file statuses, nothing persisted. */
export type DocumentBundlePlan = {
    pattern: string;
    total: number;
    importable: number;
    rejected: number;
    rows: DocumentBundlePlanRow[];
};

/** The confirm report for one imported file. */
export type DocumentBundleImported = {
    source_file: string;
    meeting: { id: number; label: string } | null;
    document: {
        id: number;
        usage_id: number;
        metadata: Record<string, unknown>;
        url: string;
        download_url: string;
    };
};

/** A skipped or failed file in the confirm report. */
export type DocumentBundleRejected = {
    file: string;
    reason_code: string;
    reason: string;
};

/** The confirm outcome — every file is accounted for. */
export type DocumentBundleOutcome = {
    imported: DocumentBundleImported[];
    skipped: DocumentBundleRejected[];
    failed: DocumentBundleRejected[];
};

export function dryRunDocumentBundle(entity: string, files: File[], pattern: string) {
    const body = new FormData();
    // The `[]` is load-bearing: PHP merges repeated bracket-less keys by
    // keeping only the last, so `files` would arrive as a single file.
    files.forEach((file) => body.append("files[]", file));
    body.append("pattern", pattern);

    return api.post<{ data: DocumentBundlePlan }>(
        `/imports/${entity}/documents/dry-run`,
        body,
        { headers: FORM_DATA_HEADERS },
    );
}

export function confirmDocumentBundle(entity: string, files: File[], pattern: string) {
    const body = new FormData();
    files.forEach((file) => body.append("files[]", file));
    body.append("pattern", pattern);

    return api.post<{ data: DocumentBundleOutcome; message: string }>(
        `/imports/${entity}/documents/confirm`,
        body,
        { headers: FORM_DATA_HEADERS },
    );
}
