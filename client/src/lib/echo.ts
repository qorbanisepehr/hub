import Echo from "laravel-echo";
import Pusher from "pusher-js";

/**
 * Echo's Pusher connector reads the runtime off `window`, which has no
 * declaration for it. Declared here, next to the only assignment.
 */
declare global {
    interface Window {
        Pusher: typeof Pusher;
    }
}

/**
 * Realtime client (architecture §30). The API stays the source of truth; the
 * channel only nudges the query cache so the bell reconciles immediately
 * instead of waiting for the next poll (§29).
 *
 * Returns null (safe no-op) when Reverb is not configured, so the 60s poll
 * keeps working on deployments without VITE_REVERB_APP_KEY.
 */
let echo: Echo<"reverb"> | null = null;

export function getEcho(): Echo<"reverb"> | null {
    if (echo) {
        return echo;
    }

    const appKey = import.meta.env.VITE_REVERB_APP_KEY;
    if (!appKey) {
        return null;
    }

    const host = import.meta.env.VITE_REVERB_HOST ?? "localhost";
    const port = Number(import.meta.env.VITE_REVERB_PORT ?? 8080);
    const forceTLS = import.meta.env.VITE_REVERB_SCHEME === "https";

    // Echo's Pusher connector needs the Pusher runtime on the window.
    window.Pusher = Pusher;

    echo = new Echo({
        broadcaster: "reverb",
        key: appKey,
        authEndpoint: "/api/broadcasting/auth",
        wsHost: host,
        wsPort: port,
        wssPort: port,
        forceTLS,
        enabledTransports: ["ws", "wss"],
    });

    return echo;
}