import { useMemo, useRef } from "react";
import { IconBrain, IconFileImport, IconFileTypography, IconListDetails, IconPalette, IconShieldLock, IconTag } from "@tabler/icons-react";
import { Outlet, useRouterState, useNavigate } from "@tanstack/react-router";
import { PageLayout, PageHeader } from "@/components/layout";
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs";
// vertical rail removed in favour of a top segmented nav
import { usePermission } from "@/features/auth/components/permission-guard";
import { PERMISSIONS } from "@/lib/permissions";

export function SettingsPage() {
    const pathname = useRouterState({ select: (s) => s.location.pathname });
    const navigate = useNavigate();

    const canBranding = usePermission([
        PERMISSIONS.BRANDING_VIEW,
        PERMISSIONS.BRANDING_MANAGE,
    ]);
    const canPermissions = usePermission([
        PERMISSIONS.DOCUMENT_CATEGORY_VIEW,
        PERMISSIONS.DOCUMENT_CATEGORY_MANAGE,
    ]);
    const canFormOptions = usePermission([
        PERMISSIONS.FORM_OPTIONS_VIEW,
        PERMISSIONS.FORM_OPTIONS_MANAGE,
    ]);
    const canImports = usePermission([PERMISSIONS.EMPLOYEE_IMPORT]);
    const canTags = usePermission([
        PERMISSIONS.TAG_VIEW,
        PERMISSIONS.TAG_MANAGE,
    ]);
    const canAi = usePermission([
        PERMISSIONS.AI_PROVIDER_VIEW,
        PERMISSIONS.AI_PROVIDER_MANAGE,
    ]);
    const canTemplates = usePermission([
        PERMISSIONS.EXPORT_TEMPLATE_VIEW,
        PERMISSIONS.EXPORT_TEMPLATE_MANAGE,
    ]);

    const activeTab = pathname.endsWith("/branding")
        ? "branding"
        : pathname.endsWith("/permissions")
          ? "permissions"
          : pathname.endsWith("/form-options")
            ? "form-options"
            : pathname.endsWith("/imports")
              ? "imports"
              : pathname.endsWith("/templates")
                ? "templates"
                : pathname.endsWith("/tags")
                  ? "tags"
                  : pathname.endsWith("/ai")
                    ? "ai"
                    : "branding";

    const settingsTabs = useMemo(
        () => [
            ...(canBranding
                ? [
                      {
                          key: "branding",
                          label: "برندینگ",
                          icon: <IconPalette className="size-5" />,
                          description: "هویت بصری سیستم",
                      },
                  ]
                : []),
            ...(canPermissions
                ? [
                      {
                          key: "permissions",
                          label: "مجوزها",
                          icon: <IconShieldLock className="size-5" />,
                          description: "دسترسی‌ها و نقش‌ها",
                      },
                  ]
                : []),
            ...(canFormOptions
                ? [
                      {
                          key: "form-options",
                          label: "گزینه‌های فرم",
                          icon: <IconListDetails className="size-5" />,
                          description: "مقادیر قابل انتخاب",
                      },
                  ]
                : []),
            ...(canImports
                ? [
                      {
                          key: "imports",
                          label: "ورود اطلاعات",
                          icon: <IconFileImport className="size-5" />,
                          description: "وارد کردن دسته‌ای",
                      },
                  ]
                : []),
            ...(canTags
                ? [
                      {
                          key: "tags",
                          label: "برچسب‌ها",
                          icon: <IconTag className="size-5" />,
                          description: " برچسب‌گذاری",
                      },
                  ]
                : []),
            ...(canAi
                ? [
                      {
                          key: "ai",
                          label: "هوش مصنوعی",
                          icon: <IconBrain className="size-5" />,
                          description: "ارائه‌دهندگان AI",
                      },
                  ]
                : []),
            ...(canTemplates
                ? [
                      {
                          key: "templates",
                          label: "قالب خروجی",
                          icon: <IconFileTypography className="size-5" />,
                          description: "فایل‌های قالب",
                      },
                  ]
                : []),
        ],
        [
            canBranding,
            canPermissions,
            canFormOptions,
            canImports,
            canTags,
            canAi,
            canTemplates,
        ],
    );

    const contentRef = useRef<HTMLDivElement>(null);

    return (
        <PageLayout>
            <PageHeader
                title="تنظیمات"
                description="مدیریت برندینگ، مجوزها، گزینه‌های فرم و ورود اطلاعات سیستم"
            />

            <Tabs
                value={activeTab}
                onValueChange={(tab) => {
                    if (tab) {
                        const target = `/settings/${tab}`;
                        void navigate({ to: target as never });
                    }
                }}
                className="grid gap-4"
            >
                <div className="sticky top-0 z-10 rounded-2xl border border-border/60 bg-card/80 p-2 shadow-sm backdrop-blur">
                    <TabsList className="flex w-full gap-1 overflow-x-auto bg-transparent p-0 scroll-fade-x no-scrollbar">
                        {settingsTabs.map((tab) => (
                            <TabsTrigger
                                key={tab.key}
                                value={tab.key}
                                className="flex shrink-0 items-center gap-2 rounded-xl px-3 py-2 text-sm"
                            >
                                {tab.icon}
                                <span className="font-medium">
                                    {tab.label}
                                </span>
                            </TabsTrigger>
                        ))}
                    </TabsList>
                </div>

                <div
                    ref={contentRef}
                    className="rounded-2xl border border-border/60 bg-card shadow-sm p-4 lg:p-6 min-w-0"
                >
                    <Outlet />
                </div>
            </Tabs>
        </PageLayout>
    );
}
