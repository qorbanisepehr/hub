import { api, FORM_DATA_HEADERS } from "@/lib/api";

/**
 * One uploaded export template.
 *
 * `format` is part of what the row IS rather than metadata about the file: a `.md`
 * template and a `.docx` template are different templates that happen to be listed
 * together, and a library that cannot say which is which is a list of names with the
 * extensions hidden.
 *
 * There is no `kind` here, and that is deliberate. A template used to be labelled
 * "content" or "style" by whether it held a placeholder, and the two were then
 * rendered by different code — so a letterhead with `{{date}}` in its footer was
 * labelled one of them and the meeting was never generated onto it. The kind is now
 * READ OFF THE FILE, and it is reported at the moment of upload rather than stored:
 * a file that says `{{content}}` wants the document, one that does not is a document
 * somebody wrote. Nothing to choose, and nothing to get wrong.
 */
export type WordTemplateDto = {
    slug: string;
    name: string;
    format: string;
    size: number;
    is_default: boolean;
};

export type WordTemplateListResponse = {
    data: {
        domain: string;
        templates: WordTemplateDto[];
    };
};

/**
 * What an uploaded file turned out to be.
 *
 * Reported once, on the upload response, because that is the only moment anybody can
 * do anything about it: the export is where the answer becomes visible otherwise, and
 * by then it is a document somebody has to open to find out they got the other kind.
 */
export type WordTemplateInspectionDto = {
    kind: "frame" | "detail";
    placeholders: string[];
    size: number;
};

export type WordTemplateUploadResponse = {
    message: string;
    data: WordTemplateDto;
    inspection: WordTemplateInspectionDto;
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
 * group must sit in one row together or a list renders as a single line. A flat list
 * of field names cannot express that, which is why the server sends it this way.
 */
export type WordTemplateRowGroupDto = {
    group: string;
    columns: WordTemplateFieldDto[];
};

/**
 * One printable section, and the values that appear in it.
 *
 * The question a person laying out a template is actually asking is not only "which
 * names may I write" but "where does each one appear", and a flat list of twenty-two
 * keys does not say that `attendee.role` lives under the attendees heading.
 */
export type WordTemplateSectionDto = {
    key: string;
    label: string;
    fields: string[];
};

/**
 * A group that may be written as a repeated BLOCK rather than a repeated row.
 *
 * The fourth shape, and the only one that produces a real list: a row repeats a table
 * ROW, a range repeats a BLOCK of paragraphs, so in Word each record becomes its own
 * paragraph — a bullet. A separator cannot do this, because a separator joins a list
 * into a sentence and several items in one paragraph wrap into one line.
 *
 * The markers are PHPWord's own `cloneBlock` shape, carried from the server rather than
 * written here so the two ends cannot drift apart.
 */
export type WordTemplateBlockGroupDto = {
    group: string;
    open: string;
    close: string;
};

export type WordTemplateFieldsResponse = {
    data: {
        domain: string;
        syntax: { open: string; close: string };
        general: WordTemplateFieldDto[];
        sections: WordTemplateSectionDto[];
        inline: WordTemplateFieldDto[];
        rows: WordTemplateRowGroupDto[];
        blocks: WordTemplateBlockGroupDto[];
        /**
         * The names holding a LIST, which therefore accept a separator of the
         * author's own — `{{summary.topics|؛ }}`. Reported because the cheat sheet is
         * read before any meeting exists, so there is nothing to infer it from.
         */
        listable: string[];
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

    return api.post<WordTemplateUploadResponse>("/word-templates", form, {
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