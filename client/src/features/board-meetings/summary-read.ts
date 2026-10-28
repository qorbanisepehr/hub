import type {
    MeetingSuggestionTag,
    MeetingSummaryArtifact,
    MeetingSummaryFieldName,
    MeetingSummaryFieldState,
} from "./types";
import { SUMMARY_FIELDS } from "./summary-copy";

/**
 * Reading the artifact, in one place.
 *
 * The stored shape distinguishes three things per field that look alike in
 * TypeScript and are not alike to a person: never asked, asked and left empty,
 * and filled. Only `fields` can tell them apart, so every read here goes
 * through the state map rather than off the value — a `summary.topics ?? []`
 * would quietly turn "we never asked" into "the model had nothing to say",
 * which is the one distinction the per-field map exists to preserve (D26).
 */

export type SummaryValue = string | string[] | MeetingSuggestionTag[] | null;

export interface FieldReading {
    name: MeetingSummaryFieldName;
    /** The stored value, normalised to the field's own shape. */
    value: SummaryValue;
    /** Present only once a run has judged this field. */
    state: MeetingSummaryFieldState | null;
    isFilled: boolean;
}

function readValue(
    summary: MeetingSummaryArtifact | null,
    name: MeetingSummaryFieldName,
    isList: boolean,
): SummaryValue {
    const raw = summary?.[name];

    if (isList) {
        return Array.isArray(raw) ? raw : [];
    }

    return typeof raw === "string" && raw.trim() !== "" ? raw : null;
}

export function readField(
    summary: MeetingSummaryArtifact | null,
    name: MeetingSummaryFieldName,
    isList: boolean,
): FieldReading {
    const state = summary?.fields[name] ?? null;

    return {
        name,
        value: readValue(summary, name, isList),
        state,
        // A value only counts as content when its own state says it was filled.
        // A key can be present with an empty value and a `failed` state, and
        // rendering that as content would show a blank as though it were an
        // answer.
        isFilled: state?.status === "ok",
    };
}

/** Every field of a summary, in the reading order the section renders. */
export function readFields(
    summary: MeetingSummaryArtifact | null,
): FieldReading[] {
    return SUMMARY_FIELDS.map((field) =>
        readField(summary, field.name, field.isList),
    );
}

/**
 * Does this artifact hold anything a person could read?
 *
 * Used for the empty state, and deliberately based on content rather than on
 * the row status: a `partial` row with four good fields has plenty to show,
 * and an `ok` row whose values were all emptied by a later hand edit should
 * still offer to generate.
 */
export function hasContent(summary: MeetingSummaryArtifact | null): boolean {
    if (summary === null) {
        return false;
    }

    return readFields(summary).some(
        (field) => field.isFilled && !isEmptyValue(field.value),
    );
}

export function isEmptyValue(value: SummaryValue): boolean {
    return value === null || (Array.isArray(value) && value.length === 0);
}

/**
 * The slugs of a tags-shaped field, for the tag picker.
 *
 * A proposal is a name/slug pair and a legacy one may still be a bare string,
 * so both shapes have to yield a slug: the picker, the accept payload and the
 * pivot all work in identities, never in labels.
 */
export function tagSlugsOf(value: SummaryValue): string[] {
    if (!Array.isArray(value)) {
        return [];
    }

    return value
        .map((item) => (typeof item === "string" ? item : item.slug))
        .filter((slug) => slug !== "");
}

/**
 * The labels a run attached to its proposed tags, by slug.
 *
 * A proposed tag the registry does not know yet has no entry in the options
 * endpoint, so without this the decision panel would fall back to showing its
 * raw slug — the English key — to a person deciding whether to keep it. The
 * run's own name is the thing a person can actually judge, and it is the name
 * the accept endpoint will create the tag under.
 */
export function proposedTagNames(value: SummaryValue): Map<string, string> {
    const names = new Map<string, string>();

    if (!Array.isArray(value)) {
        return names;
    }

    for (const item of value) {
        if (typeof item !== "string") {
            names.set(item.slug, item.name);
        }
    }

    return names;
}

/**
 * The tag set a decision starts from.
 *
 * The accept request carries the meeting's tags *in full*, because a checkbox
 * has no third state — unticking a suggestion has to mean "this meeting is not
 * tagged that", which the list can only say by naming what it does mean. So
 * the picker is pre-ticked with the proposed set when the run produced one, and
 * with the meeting's current tags when it did not.
 *
 * The second case is the reason a run that came back without tags is not
 * allowed to blank the meeting's tagging: pre-ticking the current set is what
 * makes sending the request back a no-op instead of a deletion.
 */
export function proposedTagSlugs(
    candidate: MeetingSummaryArtifact | null,
    current: string[],
): string[] {
    const reading = readField(candidate, "suggested_tags", true);

    return reading.isFilled ? tagSlugsOf(reading.value) : current;
}

/**
 * Whether a run is under way for this meeting, as the row says.
 *
 * The stamp sits in two places depending on the write mode, and this is the
 * only place the client is allowed to look for it: under `Apply` the run is the
 * row, so the outer status reads `pending`, while under `Review` — the default
 * — the record's own content is deliberately left untouched, so the run reports
 * on the *candidate* instead and the outer status still reads `none` or `ok`.
 *
 * Reading only the outer status, as this section used to, meant a review run was
 * invisible the moment the page was reloaded: the running block came from a
 * realtime event, events are held in memory, and a refresh or a second tab had
 * nothing durable to fall back on. Both placements are the server's own words
 * for one thing, so a tab that has been open for a minute and a tab opened
 * cold answer the same question the same way.
 */
export function isRunInFlight(
    summary: MeetingSummaryArtifact | null,
): boolean {
    if (summary === null) {
        return false;
    }

    return (
        summary.status === "pending" ||
        summary.pending?.status === "pending"
    );
}

/**
 * When the run under way began, or null when nothing is.
 *
 * Stamped by the worker at the moment it picks the job up, which is the only
 * start time that means anything — dispatch says when somebody asked, not when
 * the model was actually reached. It lives beside the `pending` stamp for the
 * same reason, so a page that has never seen an event can still count how long
 * this run has been going instead of showing no number at all.
 */
export function runStartedAt(
    summary: MeetingSummaryArtifact | null,
): string | null {
    if (summary === null) {
        return null;
    }

    // `?? null` rather than a bare read: the detail endpoint passes the JSONB
    // column through as it stands, so a row written before this key existed
    // answers `undefined`, and a type that promised `string | null` would be
    // describing the writer rather than the payload.
    if (summary.status === "pending") {
        return summary.started_at ?? null;
    }

    return summary.pending?.status === "pending"
        ? (summary.pending.started_at ?? null)
        : null;
}
