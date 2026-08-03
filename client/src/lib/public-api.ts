import axios from "axios";

import { getGrantToken, purgeEntityGrants } from "@/lib/grant";
import type { GrantPurpose } from "@/lib/grant";

declare module "axios" {
    export interface AxiosRequestConfig {
        /** When set, the request is sent with an access grant for this entity. */
        grant?: GrantRequest;
    }
}

export type GrantRequest = {
    entity: string;
    uuid: string;
    /** Minimum purpose the stored grant must cover. */
    purpose?: GrantPurpose;
};

export type GrantUnauthorizedDetail = {
    entity: string;
    uuid: string;
};

export const GRANT_UNAUTHORIZED_EVENT = "grant:unauthorized";

export const publicApi = axios.create({
    baseURL: "/api",
    headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
    },
});

publicApi.interceptors.request.use((config) => {
    const grant = config.grant;

    if (grant) {
        const token = getGrantToken(grant.entity, grant.uuid, grant.purpose);

        if (token) {
            config.headers = config.headers ?? {};
            config.headers["X-Access-Token"] = token;
        }
    }

    return config;
});

publicApi.interceptors.response.use(
    (response) => response,
    (error) => {
        if (axios.isCancel(error)) {
            return Promise.reject(error);
        }

        const grant: GrantRequest | undefined = error.config?.grant;

        if (grant && error.response?.status === 401) {
            purgeEntityGrants(grant.entity, grant.uuid);
            window.dispatchEvent(
                new CustomEvent<GrantUnauthorizedDetail>(
                    GRANT_UNAUTHORIZED_EVENT,
                    {
                        detail: { entity: grant.entity, uuid: grant.uuid },
                    },
                ),
            );
        }

        return Promise.reject(error);
    },
);

export function onGrantUnauthorized(
    handler: (detail: GrantUnauthorizedDetail) => void,
): () => void {
    const listener = (event: Event) => {
        handler((event as CustomEvent<GrantUnauthorizedDetail>).detail);
    };

    window.addEventListener(GRANT_UNAUTHORIZED_EVENT, listener);

    return () => window.removeEventListener(GRANT_UNAUTHORIZED_EVENT, listener);
}
