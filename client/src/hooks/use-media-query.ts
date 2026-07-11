import * as React from "react";

export function useMediaQuery(query: string) {
    const [matches, setMatches] = React.useState(() =>
        window.matchMedia(query).matches,
    );

    React.useEffect(() => {
        const mql = window.matchMedia(query);
        const handler = (e: MediaQueryListEvent) => setMatches(e.matches);

        mql.addEventListener("change", handler);

        return () => mql.removeEventListener("change", handler);
    }, [query]);

    return matches;
}
