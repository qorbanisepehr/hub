import {
    flattenNavItems,
    NAV_ITEMS,
    type FlatNavItem,
    type NavItem,
} from "@/features/dashboard/nav-items";

export interface Crumb {
    label: string;
    /** Absent on the current page crumb. */
    to?: string;
}

/**
 * Static labels for non-nav routes. Literal paths are matched exactly
 * first; `:param` patterns match any single segment and act as fallbacks.
 */
const PAGE_CRUMBS: Record<string, string> = {
    "/profile": "پروفایل",
    "/profile/edit": "ویرایش پروفایل",
    "/unauthorized": "دسترسی غیرمجاز",

    "/employees/create": "ایجاد کارمند",
    "/employees/:id": "جزئیات کارمند",
    "/employees/:id/edit": "ویرایش کارمند",

    "/users/create": "ایجاد کاربر",
    "/users/:userId": "جزئیات کاربر",
    "/users/:userId/edit": "ویرایش کاربر",
    "/users/:userId/roles": "نقش‌های کاربر",

    "/roles/create": "ایجاد نقش",
    "/roles/:roleId": "جزئیات نقش",

    "/cvs/:id": "جزئیات رزومه",

    "/audit/retention": "سیاست نگهداری لاگ‌ها",
    "/audit/:logId": "جزئیات رویداد",
};

/** Match a concrete path prefix against a `:param` pattern. */
function matchesPattern(prefixSegments: string[], pattern: string): boolean {
    const patternSegments = pattern.split("/").filter(Boolean);

    return (
        patternSegments.length === prefixSegments.length &&
        patternSegments.every(
            (segment, index) =>
                segment.startsWith(":") || segment === prefixSegments[index],
        )
    );
}

function labelForPrefix(prefix: string): string | null {
    // Exact literals win over param patterns (e.g. /users/create before
    // /users/:userId).
    const literal = PAGE_CRUMBS[prefix];

    if (literal !== undefined) {
        return literal;
    }

    const prefixSegments = prefix.split("/").filter(Boolean);

    for (const [pattern, label] of Object.entries(PAGE_CRUMBS)) {
        if (pattern.includes(":") && matchesPattern(prefixSegments, pattern)) {
            return label;
        }
    }

    return null;
}

function asCrumb(entry: FlatNavItem): Crumb[] {
    return [
        ...entry.parents.map((parent) => ({
            label: parent.title,
            ...(parent.url !== undefined ? { to: parent.url } : {}),
        })),
        { label: entry.title },
    ];
}

/** Longest nav entry whose URL is a proper ancestor of the pathname. */
function navAnchor(pathname: string): FlatNavItem | null {
    const flat = flattenNavItems(NAV_ITEMS as NavItem[]);

    return (
        flat
            .filter(
                (entry) =>
                    pathname === entry.url ||
                    pathname.startsWith(`${entry.url}/`),
            )
            .sort((a, b) => b.url.length - a.url.length)[0] ?? null
    );
}

function pageCrumbsFrom(anchorDepth: number, segments: string[]): Crumb[] {
    const crumbs: Crumb[] = [];

    for (let depth = anchorDepth + 1; depth <= segments.length; depth++) {
        const prefix = `/${segments.slice(0, depth).join("/")}`;
        const isCurrent = depth === segments.length;
        const label = labelForPrefix(prefix);

        if (label === null) {
            continue;
        }

        crumbs.push({ label, to: isCurrent ? undefined : prefix });
    }

    return crumbs;
}

/**
 * Resolve the breadcrumb trail for a pathname. Nav entries (with their
 * lineage) come from the shared NAV_ITEMS tree; deeper pages from
 * PAGE_CRUMBS. Ancestors are linked; the final crumb is the current page.
 */
export function resolveBreadcrumbs(pathname: string): Crumb[] {
    const segments = pathname.split("?")[0]!.split("/").filter(Boolean);

    if (segments.length === 0) {
        return [];
    }

    // Exact nav match: lineage + the page itself.
    const exact = flattenNavItems(NAV_ITEMS as NavItem[]).find(
        (entry) => entry.url === pathname,
    );

    if (exact) {
        return [...asCrumb(exact).slice(0, -1), { label: exact.title }];
    }

    // Otherwise anchor on the deepest covering nav section, then append
    // static page crumbs for the remaining segments.
    const anchor = navAnchor(pathname);
    const anchorSegments = anchor
        ? anchor.url.split("/").filter(Boolean).length
        : 0;

    const anchorCrumbs: Crumb[] = anchor
        ? [
              ...anchor.parents.map((parent) => ({
                  label: parent.title,
                  ...(parent.url !== undefined ? { to: parent.url } : {}),
              })),
              { label: anchor.title, to: anchor.url },
          ]
        : [];

    return [...anchorCrumbs, ...pageCrumbsFrom(anchorSegments, segments)];
}
