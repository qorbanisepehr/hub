import type { ReactNode } from "react";
import {
    IconClipboardList,
    IconClipboardText,
    IconDashboard,
    IconFileImport,
    IconFileCv,
    IconHierarchy2,
    IconIdBadge2,
    IconListDetails,
    IconMasksTheater,
    IconPalette,
    IconSettings,
    IconShield,
    IconUsers,
} from "@tabler/icons-react";

import { PERMISSIONS } from "@/lib/permissions";

export interface NavItem {
    title: string;
    /** Leaf link. Omitted on pure grouping nodes (collapsible only). */
    url?: string;
    icon?: ReactNode;
    permission?: string | string[];
    search?: Record<string, unknown>;
    children?: NavItem[];
}

/**
 * Single source of truth for the app's navigation tree. Consumed by the
 * sidebar (multi-level rendering) and the breadcrumb resolver, so both
 * always agree on section names and paths.
 */
export const NAV_ITEMS: NavItem[] = [
    { title: "داشبورد", url: "/dashboard", icon: <IconDashboard /> },
    {
        title: "کارمندان",
        url: "/employees",
        icon: <IconIdBadge2 />,
        permission: PERMISSIONS.EMPLOYEE_LIST,
    },
    {
        title: "کاربران",
        url: "/users",
        icon: <IconUsers />,
        permission: PERMISSIONS.USER_VIEW,
    },
    {
        title: "نقش‌ها",
        icon: <IconMasksTheater />,
        permission: PERMISSIONS.ROLE_VIEW,
        children: [
            { title: "فهرست نقش‌ها", url: "/roles", icon: <IconListDetails /> },
            {
                title: "نقشه سازمانی",
                url: "/roles/chart",
                icon: <IconHierarchy2 />,
            },
        ],
    },
    {
        title: "بانک رزومه",
        url: "/cvs",
        icon: <IconFileCv />,
        permission: PERMISSIONS.CV_VIEW,
    },
    {
        title: "پرسشنامه‌ها",
        url: "/questionnaires",
        icon: <IconClipboardText />,
        permission: PERMISSIONS.QUESTIONNAIRE_VIEW,
    },
    {
        title: "تنظیمات",
        icon: <IconSettings />,
        permission: [
            PERMISSIONS.DOCUMENT_CATEGORY_VIEW,
            PERMISSIONS.DOCUMENT_CATEGORY_MANAGE,
            PERMISSIONS.BRANDING_VIEW,
            PERMISSIONS.BRANDING_MANAGE,
            PERMISSIONS.FORM_OPTIONS_VIEW,
            PERMISSIONS.FORM_OPTIONS_MANAGE,
            PERMISSIONS.EMPLOYEE_IMPORT,
        ],
        children: [
            {
                title: "برندینگ",
                url: "/settings",
                search: { tab: "branding" },
                icon: <IconPalette />,
                permission: [
                    PERMISSIONS.BRANDING_VIEW,
                    PERMISSIONS.BRANDING_MANAGE,
                ],
            },
            {
                title: "مجوزها",
                url: "/settings",
                search: { tab: "permissions" },
                icon: <IconShield />,
                permission: [
                    PERMISSIONS.DOCUMENT_CATEGORY_VIEW,
                PERMISSIONS.DOCUMENT_CATEGORY_MANAGE,
                ],
            },
            {
                title: "گزینه‌های فرم",
                url: "/settings",
                search: { tab: "form-options" },
                icon: <IconListDetails />,
                permission: [
                    PERMISSIONS.FORM_OPTIONS_VIEW,
                    PERMISSIONS.FORM_OPTIONS_MANAGE,
                ],
            },
            {
                title: "ورود اطلاعات",
                url: "/settings",
                search: { tab: "imports" },
                icon: <IconFileImport />,
                permission: PERMISSIONS.EMPLOYEE_IMPORT,
            },
        ],
    },
    {
        title: "لاگ فعالیت",
        url: "/audit",
        icon: <IconClipboardList />,
        permission: PERMISSIONS.AUDIT_VIEW,
    },
];

export interface FlatNavItem {
    title: string;
    url: string;
    /** Ancestor titles, outermost first. Groups may have no URL of their own. */
    parents: Array<{ title: string; url?: string }>;
}

/** Flatten the tree into linkable entries carrying their lineage. */
export function flattenNavItems(items: NavItem[]): FlatNavItem[] {
    const flat: FlatNavItem[] = [];

    const walk = (nodes: NavItem[], parents: FlatNavItem["parents"]) => {
        for (const item of nodes) {
            if (item.url !== undefined) {
                flat.push({
                    title: item.title,
                    url: item.url,
                    parents,
                });
            }

            if (item.children) {
                walk(
                    item.children,
                    item.title
                        ? [...parents, { title: item.title, url: item.url }]
                        : parents,
                );
            }
        }
    };

    walk(items, []);

    return flat;
}
