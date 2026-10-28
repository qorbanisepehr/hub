import { api } from "@/lib/api";

/**
 * One printable section, with the label the domain gives it.
 *
 * The label travels from the server rather than being a map in the client: a
 * client-side copy of these names is a second copy of the domain's vocabulary,
 * and it would fall behind silently — with a raw key shown where a person
 * expects a word.
 */
export type TemplateSectionDto = {
    key: string;
    label: string;
};

/**
 * Which sections a meeting's printed form contains.
 *
 * FOUR THINGS, because they answer four different questions and a reader needs
 * to see them side by side: what the sheet CAN print (`available`, carrying the
 * domain's own labels), what is explicitly stored (`stored`, null when
 * inherited), what will actually print (`effective`), and which meetings override
 * the domain (`overrides`).
 *
 * `stored` and `effective` being separate is the whole point. A screen showing
 * only `stored` would tell an administrator their template is `["summary"]` on a
 * meeting where the domain quietly supplies the other seven, and nobody would
 * ever see the two numbers next to each other to notice.
 */
export type SectionTemplateListResponse = {
    data: {
        available: TemplateSectionDto[];
        stored: string[] | null;
        effective: string[];
        overrides: { entity: string | null; sections: string[] }[];
    };
};

export type SectionTemplateMeetingResponse = {
    data: {
        available: TemplateSectionDto[];
        entity: string;
        stored: string[] | null;
        effective: string[];
    };
};

export function fetchSectionTemplates() {
    return api.get<SectionTemplateListResponse>("/board-meetings/templates");
}

export function fetchMeetingSectionTemplate(meetingId: number | string) {
    return api.get<SectionTemplateMeetingResponse>(
        `/board-meetings/templates/${meetingId}`,
    );
}

export function updateSectionTemplate(sections: string[]) {
    return api.put("/board-meetings/templates", { sections });
}

export function updateMeetingSectionTemplate(
    meetingId: number | string,
    sections: string[],
) {
    return api.put(`/board-meetings/templates/${meetingId}`, { sections });
}

/** Hand a meeting back to the domain's template. */
export function clearMeetingSectionTemplate(meetingId: number | string) {
    return api.delete(`/board-meetings/templates/${meetingId}`);
}
