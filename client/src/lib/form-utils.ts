/**
 * Safely walk a dot-notation path (e.g. `personal_info.military_status.status`)
 * through an arbitrary form-values tree. Returns `undefined` when any segment
 * is missing or the walk hits a non-object (null/primitive) before the end.
 *
 * This replaces the former `let current: any` store-selector walks: the leaf
 * stays `unknown`, so the narrow cast happens once at the call site (where the
 * expected field type is known) instead of the whole walk being untyped.
 */
export function getFormValuePath(values: unknown, path: string): unknown {
    let current: unknown = values;

    for (const key of path.split(".")) {
        if (current === null || typeof current !== "object") {
            return undefined;
        }

        current = (current as Record<string, unknown>)[key];
    }

    return current;
}

/**
 * Strip `null`/`undefined` values from a server section object so they
 * don't override the form's sensible defaults when spread into
 * `buildDefaultValues`. The backend stores `null` for untouched JSONB
 * fields; spreading them over defaults would change e.g.
 * `spouse_employment_status` from `""` to `null`, which then triggers
 * auto-select useEffects and falsely marks the form dirty.
 */
export function cleanServerSection(
    serverData: unknown,
): Record<string, unknown> {
    if (typeof serverData !== "object" || serverData === null) return {};
    return Object.fromEntries(
        Object.entries(serverData as Record<string, unknown>).filter(
            ([, v]) => v !== null && v !== undefined,
        ),
    );
}
