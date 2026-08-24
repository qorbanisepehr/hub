/**
 * Strip `null`/`undefined` values from a server section object so they
 * don't override the form's sensible defaults when spread into
 * `buildDefaultValues`. The backend stores `null` for untouched JSONB
 * fields; spreading them over defaults would change e.g.
 * `spouse_employment_status` from `""` to `null`, which then triggers
 * auto-select useEffects and falsely marks the form dirty.
 */
export function cleanServerSection(
    serverData: Record<string, unknown> | null | undefined,
): Record<string, unknown> {
    if (!serverData) return {};
    return Object.fromEntries(
        Object.entries(serverData).filter(
            ([, v]) => v !== null && v !== undefined,
        ),
    );
}
