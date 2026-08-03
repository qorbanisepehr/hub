export type GrantPurpose = "view" | "edit";

export const GRANT_PURPOSE_LEVEL: Record<GrantPurpose, number> = {
    view: 0,
    edit: 1,
};

export type GrantToken = {
    token: string;
    purpose: GrantPurpose;
    expiresAt: number;
};

const STORAGE_PREFIX = "grant-access";

function storageKey(entity: string, uuid: string): string {
    return `${STORAGE_PREFIX}:${entity}:${uuid}`;
}

function readToken(entity: string, uuid: string): GrantToken | null {
    if (typeof window === "undefined") return null;

    try {
        const raw = window.sessionStorage.getItem(storageKey(entity, uuid));
        if (!raw) return null;

        const parsed = JSON.parse(raw) as GrantToken;
        if (!parsed?.token || typeof parsed.expiresAt !== "number") return null;

        return parsed;
    } catch {
        return null;
    }
}

function coversGrant(stored: GrantPurpose, required: GrantPurpose): boolean {
    return GRANT_PURPOSE_LEVEL[stored] >= GRANT_PURPOSE_LEVEL[required];
}

/**
 * Store an access grant token for an entity+uuid.
 * `expiresIn` is expressed in seconds.
 */
export function setGrantToken(
    entity: string,
    uuid: string,
    purpose: GrantPurpose,
    token: string,
    expiresIn: number,
): void {
    if (typeof window === "undefined") return;

    const grant: GrantToken = {
        token,
        purpose,
        expiresAt: Date.now() + expiresIn * 1000,
    };

    window.sessionStorage.setItem(
        storageKey(entity, uuid),
        JSON.stringify(grant),
    );
}

/**
 * Resolve the stored grant token. When `purpose` is given, only a token whose
 * purpose covers the requested one is returned.
 */
export function getGrantToken(
    entity: string,
    uuid: string,
    purpose?: GrantPurpose,
): string | null {
    const grant = readToken(entity, uuid);

    if (!grant) return null;

    if (purpose && !coversGrant(grant.purpose, purpose)) return null;

    if (grant.expiresAt <= Date.now()) {
        purgeEntityGrants(entity, uuid);
        return null;
    }

    return grant.token;
}

export function hasGrant(
    entity: string,
    uuid: string,
    purpose: GrantPurpose,
): boolean {
    return getGrantToken(entity, uuid, purpose) !== null;
}

export function purgeEntityGrants(entity: string, uuid: string): void {
    if (typeof window === "undefined") return;

    window.sessionStorage.removeItem(storageKey(entity, uuid));
}

export function purgeAllGrants(): void {
    if (typeof window === "undefined") return;

    const keys: string[] = [];

    for (let i = 0; i < window.sessionStorage.length; i++) {
        const key = window.sessionStorage.key(i);
        if (key?.startsWith(`${STORAGE_PREFIX}:`)) keys.push(key);
    }

    keys.forEach((key) => window.sessionStorage.removeItem(key));
}
