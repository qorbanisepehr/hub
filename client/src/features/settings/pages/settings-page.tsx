import { useEffect } from "react";
import { getRouteApi } from "@tanstack/react-router";

import { Tabs, TabsList, TabsTrigger, TabsContent } from "@/components/ui/tabs";
import { PageLayout, PageHeader } from "@/components/layout";
import {
    PermissionGuard,
    usePermission,
} from "@/features/auth/components/permission-guard";
import { PERMISSIONS } from "@/lib/permissions";
import { BrandingSettingsSection } from "@/features/settings/pages/branding-settings-page";
import { PermissionsSection } from "@/features/rbac/pages/permissions-section";
import { FormOptionsSection } from "@/features/settings/pages/form-options-section";
import { ImportSection } from "@/features/imports/import-section";
import { TagsSection } from "@/features/tags/pages/tags-section";
import { AiSettingsSection } from "@/features/ai";

const route = getRouteApi("/protected/settings");

export type SettingsTab =
    | "branding"
    | "permissions"
    | "form-options"
    | "imports"
    | "tags"
    | "ai";

export function SettingsPage() {
    const search = route.useSearch();
    const navigate = route.useNavigate();

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
    const canTags = usePermission([PERMISSIONS.TAG_VIEW, PERMISSIONS.TAG_MANAGE]);
    const canAi = usePermission([
        PERMISSIONS.AI_PROVIDER_VIEW,
        PERMISSIONS.AI_PROVIDER_MANAGE,
    ]);

    // AI is last in the fallback chain on purpose: it is the one tab whose
    // absence must never block the others, since an unconfigured AI registry
    // is a supported state (D24) and the tab still renders read-only.
    const defaultTab: SettingsTab = canBranding
        ? "branding"
        : canPermissions
          ? "permissions"
          : canFormOptions
            ? "form-options"
            : canTags
              ? "tags"
              : canAi
                ? "ai"
                : "imports";
    const activeTab = search.tab ?? defaultTab;

    useEffect(() => {
        if (
            (activeTab === "branding" && !canBranding) ||
            (activeTab === "permissions" && !canPermissions) ||
            (activeTab === "form-options" && !canFormOptions) ||
            (activeTab === "imports" && !canImports) ||
            (activeTab === "tags" && !canTags) ||
            (activeTab === "ai" && !canAi)
        ) {
            navigate({
                search: (prev) => ({ ...prev, tab: defaultTab }),
            });
        }
    }, [
        activeTab,
        canBranding,
        canPermissions,
        canFormOptions,
        canImports,
        canTags,
        canAi,
        defaultTab,
        navigate,
    ]);

    return (
        <PageLayout>
            <PageHeader
                title="تنظیمات"
                description="مدیریت برندینگ، مجوزها، گزینه‌های فرم و ورود اطلاعات سیستم"
            />

            <Tabs
                value={activeTab}
                onValueChange={(tab) =>
                    navigate({ search: (prev) => ({ ...prev, tab }) })
                }
            >
                <TabsList>
                    {canBranding && (
                        <TabsTrigger value="branding">برندینگ</TabsTrigger>
                    )}
                    {canPermissions && (
                        <TabsTrigger value="permissions">مجوزها</TabsTrigger>
                    )}
                    {canFormOptions && (
                        <TabsTrigger value="form-options">گزینه‌های فرم</TabsTrigger>
                    )}
                    {canImports && (
                        <TabsTrigger value="imports">ورود اطلاعات</TabsTrigger>
                    )}
                    {canTags && (
                        <TabsTrigger value="tags">برچسب‌ها</TabsTrigger>
                    )}
                    {canAi && <TabsTrigger value="ai">هوش مصنوعی</TabsTrigger>}
                </TabsList>

                {canBranding && (
                    <TabsContent value="branding">
                        <PermissionGuard
                            permission={[
                                PERMISSIONS.BRANDING_VIEW,
                                PERMISSIONS.BRANDING_MANAGE,
                            ]}
                        >
                            <BrandingSettingsSection />
                        </PermissionGuard>
                    </TabsContent>
                )}

                {canPermissions && (
                    <TabsContent value="permissions">
                        <PermissionGuard
                            permission={[
                                PERMISSIONS.DOCUMENT_CATEGORY_VIEW,
                                PERMISSIONS.DOCUMENT_CATEGORY_MANAGE,
                            ]}
                        >
                            <PermissionsSection />
                        </PermissionGuard>
                    </TabsContent>
                )}

                {canFormOptions && (
                    <TabsContent value="form-options">
                        <PermissionGuard
                            permission={[
                                PERMISSIONS.FORM_OPTIONS_VIEW,
                                PERMISSIONS.FORM_OPTIONS_MANAGE,
                            ]}
                        >
                            <FormOptionsSection />
                        </PermissionGuard>
                    </TabsContent>
                )}

                {canImports && (
                    <TabsContent value="imports">
                        <PermissionGuard
                            permission={PERMISSIONS.EMPLOYEE_IMPORT}
                        >
                            <ImportSection />
                        </PermissionGuard>
                    </TabsContent>
                )}

                {canTags && (
                    <TabsContent value="tags">
                        <PermissionGuard
                            permission={[
                                PERMISSIONS.TAG_VIEW,
                                PERMISSIONS.TAG_MANAGE,
                            ]}
                        >
                            <TagsSection />
                        </PermissionGuard>
                    </TabsContent>
                )}

                {canAi && (
                    <TabsContent value="ai">
                        <PermissionGuard
                            permission={[
                                PERMISSIONS.AI_PROVIDER_VIEW,
                                PERMISSIONS.AI_PROVIDER_MANAGE,
                            ]}
                        >
                            <AiSettingsSection />
                        </PermissionGuard>
                    </TabsContent>
                )}
            </Tabs>
        </PageLayout>
    );
}
