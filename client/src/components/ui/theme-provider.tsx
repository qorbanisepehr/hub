import {
    createContext,
    useCallback,
    useContext,
    useEffect,
    useLayoutEffect,
    useMemo,
    useRef,
    useState,
} from "react";
import type { ReactNode } from "react";

export type Theme = "light" | "dark" | "system";
export type ResolvedTheme = "light" | "dark";

const STORAGE_KEY = "theme";
const THEMES: readonly Theme[] = ["light", "dark", "system"];

const isSystemDark = (): boolean =>
    typeof window !== "undefined" &&
    typeof window.matchMedia === "function"
    ? window.matchMedia("(prefers-color-scheme: dark)").matches
    : false;

const getSystemTheme = (): ResolvedTheme => (isSystemDark() ? "dark" : "light");

const getStoredTheme = (storageKey: string, fallback: Theme): Theme => {
    if (typeof window === "undefined") {
        return fallback;
    }
    try {
        const stored = window.localStorage.getItem(storageKey);
        if (stored && THEMES.includes(stored as Theme)) {
            return stored as Theme;
        }
    } catch {
        // Storage is unavailable (private mode / blocked); fall back.
    }
    return fallback;
};

const applyThemeClass = (
    theme: Theme,
    systemTheme: ResolvedTheme,
    attribute: "class" | "data-theme",
): void => {
    const dark = theme === "system" ? systemTheme === "dark" : theme === "dark";
    const root = document.documentElement;
    if (attribute === "class") {
        root.classList.toggle("dark", dark);
    } else {
        root.setAttribute("data-theme", dark ? "dark" : "light");
    }
};

type ThemeProviderProps = {
    children: ReactNode;
    attribute?: "class" | "data-theme";
    defaultTheme?: Theme;
    enableSystem?: boolean;
    storageKey?: string;
};

type ThemeContextValue = {
    theme: Theme;
    setTheme: (theme: Theme) => void;
    resolvedTheme: ResolvedTheme;
    systemTheme: ResolvedTheme;
    themes: readonly Theme[];
};

const ThemeContext = createContext<ThemeContextValue | null>(null);

/**
 * In-repo replacement for next-themes.
 *
 * next-themes 0.4.6 renders a `<script>` element (with dangerouslySetInnerHTML)
 * for the theme-init snippet. React 19 only allows inline `script` data blocks,
 * so it logs "Encountered a script tag..." for those and the element is inert.
 * We avoid the head script entirely: the initial theme is applied synchronously
 * via `applyInitialTheme()` in main.tsx before React mounts (no flash), and the
 * provider keeps the class in sync from then on. Same switching API as
 * next-themes: `useTheme()` -> { theme, setTheme, resolvedTheme, ... }.
 */
export function ThemeProvider({
    children,
    attribute = "class",
    defaultTheme = "system",
    storageKey = STORAGE_KEY,
}: ThemeProviderProps) {
    const [theme, setThemeState] = useState<Theme>(() =>
        getStoredTheme(storageKey, defaultTheme),
    );
    const [systemTheme, setSystemTheme] =
        useState<ResolvedTheme>(getSystemTheme);
    const attributeRef = useRef(attribute);
    attributeRef.current = attribute;

    useEffect(() => {
        const media = window.matchMedia("(prefers-color-scheme: dark)");
        const onChange = (event: MediaQueryListEvent) =>
            setSystemTheme(event.matches ? "dark" : "light");
        media.addEventListener("change", onChange);
        return () => media.removeEventListener("change", onChange);
    }, []);

    useLayoutEffect(() => {
        applyThemeClass(theme, systemTheme, attributeRef.current);
    }, [theme, systemTheme]);

    const setTheme = useCallback(
        (next: Theme) => {
            setThemeState((current) => {
                if (current === next) {
                    return current;
                }
                try {
                    window.localStorage.setItem(storageKey, next);
                } catch {
                    // Storage is unavailable; keep running for this session.
                }
                return next;
            });
        },
        [storageKey],
    );

    const resolvedTheme: ResolvedTheme =
        theme === "system" ? systemTheme : theme;

    const value = useMemo<ThemeContextValue>(
        () => ({ theme, setTheme, resolvedTheme, systemTheme, themes: THEMES }),
        [theme, setTheme, resolvedTheme, systemTheme],
    );

    return (
        <ThemeContext.Provider value={value}>{children}</ThemeContext.Provider>
    );
}

export function useTheme(): ThemeContextValue {
    const value = useContext(ThemeContext);
    if (!value) {
        throw new Error("useTheme must be used within a ThemeProvider");
    }
    return value;
}

/**
 * Apply the persisted/system theme before React mounts so the very first
 * painted frame already has the dark class (no flash of the light theme).
 * Call once at module scope, prior to `createRoot`/`render`.
 */
export function applyInitialTheme(options?: {
    defaultTheme?: Theme;
    storageKey?: string;
    attribute?: "class" | "data-theme";
}): void {
    if (typeof window === "undefined") {
        return;
    }
    const { defaultTheme = "system", storageKey = STORAGE_KEY, attribute = "class" } =
        options ?? {};
    applyThemeClass(getStoredTheme(storageKey, defaultTheme), getSystemTheme(), attribute);
}