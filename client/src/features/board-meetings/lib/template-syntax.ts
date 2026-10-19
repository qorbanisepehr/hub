/**
 * Reading a template without a server.
 *
 * EVERY RULE HERE COMES FROM THE PAYLOAD, so this works for any domain on the same
 * template kernel without being told anything about that domain. A person cannot
 * discover a macro from the server — they discover it from the screen, and the screen
 * can only show what it is told. This is the screen asking the question back.
 *
 * WHY CLIENT-SIDE. There is nothing to send: a template is text, the names are
 * already in memory, and a round trip to re-ask would put a network between a
 * keystroke and an underline. It also means no data is involved at all — no meeting,
 * no record, nothing that would have to be private to be useful.
 *
 * WHAT IT KNOWS, and the order that matters. A marker is checked against the GROUPS
 * rather than the fields, because `{{#summary}}` is the mistake people actually make
 * and it looks exactly like a field that exists. Then the wrapped list, then the
 * separator list, then a plain value — most specific first, so a macro is never
 * explained by the vaguest rule that would accept it.
 */

export type TemplateIssue = {
    /** The macro as written, without braces. */
    macro: string;
    /** Why it is wrong, in the reader's own terms. */
    problem: string;
    /** The nearest thing that would work, when there is one. */
    suggestion?: string;
};

export type TemplatePayload = {
    syntax: { open: string; close: string };
    general: { key: string; label: string }[];
    inline: { key: string; label: string }[];
    rows: { group: string; columns: { key: string; label: string }[] }[];
    blocks: { group: string }[];
    listable: string[];
    dates: string[];
};

export function validateTemplate(
    text: string,
    fields: TemplatePayload,
): TemplateIssue[] {
    const { open, close } = fields.syntax;

    const groups = new Set(
        [...fields.rows.map((r) => r.group), ...fields.blocks.map((b) => b.group)],
    );
    const listable = new Set(fields.listable);
    const columns = new Set([
        ...fields.rows.flatMap((r) => r.columns.map((c) => c.key)),
        ...listable,
    ]);
    const singles = new Set([
        ...fields.inline.map((f) => f.key),
        ...fields.general.map((f) => f.key),
        "content",
    ]);

    // Every name a macro may legitimately mean, for the "did you mean" line.
    const known = [...groups, ...columns, ...singles];

    const pattern = new RegExp(
        `${escape(open)}([^{}]*?)${escape(close)}`,
        "g",
    );

    const issues: TemplateIssue[] = [];
    const opened = new Set<string>();
    const closed = new Set<string>();

    for (const match of text.matchAll(pattern)) {
        const raw = (match[1] ?? "").trim();

        if (raw === "") {
            continue;
        }

        // A block range: `{{#group}}` / `{{/group}}`. Checked first because a name
        // that is only a group is the mistake that looks most like a field.
        const marker = raw[0] === "#" || raw[0] === "/" ? raw[0] : null;

        if (marker !== null) {
            const group = raw.slice(1).trim();

            if (!groups.has(group)) {
                issues.push({
                    macro: raw,
                    problem: `there is no group called “${group}”`,
                    suggestion: nearest(group, known),
                });
            } else if (marker === "#") {
                opened.add(group);
            } else {
                closed.add(group);
            }

            continue;
        }

        // A wrapped list: `{{@before|name|after|between}}`.
        const bar = raw.indexOf("|");
        const dot = raw.indexOf(".");

        if (bar !== -1 && (dot === -1 || bar < dot)) {
            const name = raw.slice(bar + 1).split("|")[0]?.trim() ?? "";

            if (!columns.has(name)) {
                issues.push({
                    macro: raw,
                    problem: `“${name}” is not a column that repeats`,
                    suggestion: nearest(name, [...columns]),
                });
            }

            continue;
        }

        // A separator list: `{{column|separator}}`.
        if (bar !== -1) {
            const name = raw.slice(0, bar).trim();

            if (!columns.has(name)) {
                issues.push({
                    macro: raw,
                    problem: `“${name}” is not a column that repeats`,
                    suggestion: nearest(name, [...columns]),
                });
            }

            continue;
        }

        // A plain value, including the `@jalali` variant of a date.
        const at = raw.lastIndexOf("@");
        const base = at > 0 ? raw.slice(0, at) : raw;
        const variant = at > 0 ? raw.slice(at + 1) : null;

        if (variant !== null && variant !== "jalali") {
            issues.push({
                macro: raw,
                problem: `“@${variant}” is not a form this application knows`,
                suggestion: "the only variant is @jalali",
            });

            continue;
        }

        if (!singles.has(base) && !singles.has(raw)) {
            issues.push({
                macro: raw,
                problem: `there is no placeholder called “${base}”`,
                suggestion: nearest(base, known),
            });
        }
    }

    // HALF A RANGE. Reported at the end, because it is only a mistake once both
    // ends have been looked for — and it is the mistake that silently deletes a
    // block, which is worse than one that prints.
    for (const group of opened) {
        if (!closed.has(group)) {
            issues.push({
                macro: `${open}#${group}${close}`,
                problem: `this block opens but never closes — add ${open}/${group}${close}`,
            });
        }
    }

    for (const group of closed) {
        if (!opened.has(group)) {
            issues.push({
                macro: `${open}/${group}${close}`,
                problem: `this block closes but never opens — add ${open}#${group}${close}`,
            });
        }
    }

    return issues;
}

/**
 * The closest name to what was typed, or nothing when nothing is close.
 *
 * NOT AN EDIT DISTANCE. A full one is the right answer for a typo and the wrong
 * amount of work for a hint; what this actually needs to catch is a name that is
 * almost right — one letter out, a suffix dropped, a plural — and every one of those
 * shares a long run with the name that was meant. So the rule is a shared prefix,
 * and nothing is offered when there is not one, because a bad suggestion is worse
 * than none: it sends the reader looking for a name that does not exist.
 */
function nearest(typed: string, candidates: string[]): string | undefined {
    if (typed === "") {
        return undefined;
    }

    const lower = typed.toLowerCase();

    const contains = candidates.find((c) =>
        c.toLowerCase().includes(lower) || lower.includes(c.toLowerCase()),
    );

    if (contains) {
        return contains;
    }

    const shares = candidates.find((candidate) => {
        const other = candidate.toLowerCase();

        let shared = 0;

        while (
            shared < other.length &&
            shared < lower.length &&
            other[shared] === lower[shared]
        ) {
            shared++;
        }

        // Half the shorter name, or three characters — whichever asks less of a
        // three-letter name than "half".
        return shared >= Math.min(3, Math.ceil(Math.min(other.length, lower.length) / 2));
    });

    return shares;
}

function escape(value: string): string {
    return value.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
}