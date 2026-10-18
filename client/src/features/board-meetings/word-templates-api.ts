import { api, FORM_DATA_HEADERS } from "@/lib/api";

/**
 * One uploaded Word template.
 *
 * There is no `kind`, and that is the whole point: the file used to be labelled
 * "content" or "style" by whether it held a placeholder, and the two were then
 * rendered by different code. A letterhead with `{{date}}` in its footer is both
 * of those things at once, so it was labelled one of them and the meeting was
 * never generated onto it. Every template now behaves identically, so there is
 * nothing left to report.
 */
export type WordTemplateDto = {
    slug: string;
    name: string;
    size: number;
    is_default: boolean;
};

export type WordTemplateListResponse = {
    data: {
        domain: string;
        templates: WordTemplateDto[];
    };
};

/** One name a template may refer to, as the cheat sheet reports it. */
export type WordTemplateFieldDto = {
    key: string;
    label: string;
};

/**
 * Row fields, grouped by the table row they share.
 *
 * The grouping is not cosmetic: `cloneRow` duplicates a ROW, so the columns of a
 * group must sit in one row together or a list renders as a single line. A flat
 * list of field names cannot express that, which is why the server sends it this
 * way.
 */
export type WordTemplateRowGroupDto = {
    group: string;
    columns: WordTemplateFieldDto[];
};

export type WordTemplateFieldsResponse = {
    data: {
        domain: string;
        syntax: { open: string; close: string };
        inline: WordTemplateFieldDto[];
        rows: WordTemplateRowGroupDto[];
    };
};

export function fetchWordTemplates(domain = "boardmeeting") {
    return api.get<WordTemplateListResponse>("/word-templates", {
        params: { domain },
    });
}

/**
 * The names a template may contain.
 *
 * A separate call from the list, and long-lived: the fields are a property of
 * the domain, not of the current library, so they do not change when somebody
 * uploads a file and should not be refetched beside it.
 */
export function fetchWordTemplateFields(domain = "boardmeeting") {
    return api.get<WordTemplateFieldsResponse>("/word-templates/fields", {
        params: { domain },
    });
}

export function uploadWordTemplate(payload: {
    file: File;
    name: string;
    domain?: string;
    is_default?: boolean;
}) {
    const form = new FormData();
    form.append("file", payload.file);
    form.append("name", payload.name);
    form.append("domain", payload.domain ?? "boardmeeting");

    if (payload.is_default) {
        form.append("is_default", "1");
    }

    return api.post("/word-templates", form, {
        headers: FORM_DATA_HEADERS,
    });
}

export function updateWordTemplate(
    slug: string,
    payload: { name?: string; is_default?: boolean },
) {
    return api.put(`/word-templates/${slug}`, payload);
}

export function deleteWordTemplate(slug: string) {
    return api.delete(`/word-templates/${slug}`);
}

/**
 * The Word export, with a template chosen.
 *
 * `template` is the slug, and omitting it means "whatever the domain's default
 * is" - which is what keeps a single click working for an application where
 * nobody has configured anything.
 */
export function exportBoardMeetingDocx(id: number | string, template?: string) {
    return api.get(`/board-meetings/${id}/export/docx`, {
        responseType: "blob",
        params: template ? { template } : undefined,
    });
}
