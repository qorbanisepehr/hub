import { useEffect } from "react";
import { getRouteApi } from "@tanstack/react-router";

import { Tabs, TabsList, TabsTrigger, TabsContent } from "@/components/ui/tabs";
import { PageLayout } from "@/components/layout";
import {
    PermissionGuard,
    usePermission,
} from "@/features/auth/components/permission-guard";
import { PERMISSIONS } from "@/lib/permissions";
import { BrandingSettingsSection } from "@/features/settings/pages/branding-settings-page";
import { PermissionsSection } from "@/features/rbac/pages/permissions-section";
import { FormOptionsSection } from "@/features/settings/pages/form-options-section";

const route = getRouteApi("/protected/settings");

export type SettingsTab = "branding" | "permissions" | "form-options";

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

    const defaultTab: SettingsTab = canBranding
        ? "branding"
        : canPermissions
          ? "permissions"
          : "form-options";
    const activeTab = search.tab ?? defaultTab;

    useEffect(() => {
        if (
            (activeTab === "branding" && !canBranding) ||
            (activeTab === "permissions" && !canPermissions) ||
            (activeTab === "form-options" && !canFormOptions)
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
        defaultTab,
        navigate,
    ]);

    return (
        <PageLayout>
            <div>
                <h1 className="text-2xl font-bold tracking-tight">
                    تنظیمات
                </h1>
                <p className="text-sm text-muted-foreground mt-1">
                    مدیریت برندینگ، مجوزها و گزینه‌های فرم سیستم
                </p>
            </div>

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
            </Tabs>
        </PageLayout>
    );
}
