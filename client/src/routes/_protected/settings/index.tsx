import { createRoute } from "@tanstack/react-router";
import { Route as SettingsRoute } from "@/routes/_protected/settings";
import { redirectToFirstAllowed } from "@/features/auth/guards";
import { PERMISSIONS } from "@/lib/permissions";

/**
 * Order matters: it is the tab order, so `/settings` lands on the leftmost tab
 * the user may see. AI is last deliberately — an unconfigured AI registry is a
 * supported read-only state, so it must never be the only thing we redirect to.
 */
const TABS = [
    {
        to: "/settings/branding",
        permissions: [PERMISSIONS.BRANDING_VIEW, PERMISSIONS.BRANDING_MANAGE],
    },
    {
        to: "/settings/permissions",
        permissions: [
            PERMISSIONS.DOCUMENT_CATEGORY_VIEW,
            PERMISSIONS.DOCUMENT_CATEGORY_MANAGE,
        ],
    },
    {
        to: "/settings/form-options",
        permissions: [
            PERMISSIONS.FORM_OPTIONS_VIEW,
            PERMISSIONS.FORM_OPTIONS_MANAGE,
        ],
    },
    { to: "/settings/tags", permissions: [PERMISSIONS.TAG_VIEW, PERMISSIONS.TAG_MANAGE] },
    { to: "/settings/imports", permissions: PERMISSIONS.EMPLOYEE_IMPORT },
    {
        to: "/settings/ai",
        permissions: [PERMISSIONS.AI_PROVIDER_VIEW, PERMISSIONS.AI_PROVIDER_MANAGE],
    },
] satisfies ReadonlyArray<{
    to: string;
    permissions: string | string[];
}>;

export const Route = createRoute({
    getParentRoute: () => SettingsRoute,
    path: "/",
    beforeLoad: () => redirectToFirstAllowed(TABS),
});