import { Outlet, useRouterState, useNavigate } from "@tanstack/react-router";
import { PageLayout, PageHeader } from "@/components/layout";
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs";
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
    const canWordTemplates = usePermission([
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

    return (
        <PageLayout>
            <PageHeader
                title="تنظیمات"
                description="مدیریت برندینگ، مجوزها، گزینه‌های فرم و ورود اطلاعات سیستم"
            />

            <Tabs
                value={activeTab}
                onValueChange={(tab) => {
                    const target = `/settings/${tab}`;
                    void navigate({ to: target as never });
                }}
            >
                <TabsList>
                    {canBranding && (
                        <TabsTrigger value="branding">برندینگ</TabsTrigger>
                    )}
                    {canPermissions && (
                        <TabsTrigger value="permissions">مجوزها</TabsTrigger>
                    )}
                    {canFormOptions && (
                        <TabsTrigger value="form-options">
                            گزینه‌های فرم
                        </TabsTrigger>
                    )}
                    {canImports && (
                        <TabsTrigger value="imports">ورود اطلاعات</TabsTrigger>
                    )}
                    {canTags && (
                        <TabsTrigger value="tags">برچسب‌ها</TabsTrigger>
                    )}
                    {canAi && <TabsTrigger value="ai">هوش مصنوعی</TabsTrigger>}
                    {canWordTemplates && (
                        <TabsTrigger value="templates">
                            قالب خروجی
                        </TabsTrigger>
                    )}
                </TabsList>

                <Outlet />
            </Tabs>
        </PageLayout>
    );
}
