import { createRoute } from "@tanstack/react-router";
import { Route as SettingsRoute } from "@/routes/_protected/settings";
import { redirectToFirstAllowed } from "@/features/auth/guards";
import { PERMISSIONS } from "@/lib/permissions";

/**
 * Order matters: it is the tab order, so `/settings` lands on the leftmost tab
 * the user may see.
 *
 * The last two are last DELIBERATELY. An unconfigured AI registry and an empty
 * template library are both supported read-only states — nothing breaks, there
 * is simply nothing to show yet — so neither may be the only tab a user can
 * reach, or a fresh install would redirect a first-time visitor into a screen
 * with nothing on it.
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
    {
        to: "/settings/word-templates",
        permissions: [
            PERMISSIONS.EXPORT_TEMPLATE_VIEW,
            PERMISSIONS.EXPORT_TEMPLATE_MANAGE,
        ],
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