import type {
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

export type SummaryValue = string | string[] | null;

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

/** The string list of a tags-shaped field, for the tag picker. */
export function tagSlugsOf(value: SummaryValue): string[] {
    return Array.isArray(value) ? value : [];
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
