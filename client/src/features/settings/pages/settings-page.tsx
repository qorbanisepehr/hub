import { useEffect } from "react";
import { getRouteApi } from "@tanstack/react-router";

import { Tabs, TabsList, TabsTrigger, TabsContent } from "@/components/ui/tabs";
import { PageLayout } from "@/components/shared/page-layout";
import {
    PermissionGuard,
    usePermission,
} from "@/features/auth/components/permission-guard";
import { PERMISSIONS } from "@/lib/permissions";
import { BrandingSettingsSection } from "@/features/settings/pages/branding-settings-page";
import { PermissionsSection } from "@/features/rbac/pages/permissions-section";

const route = getRouteApi("/protected/settings");

export type SettingsTab = "branding" | "permissions";

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

    const defaultTab: SettingsTab = canBranding ? "branding" : "permissions";
    const activeTab = search.tab ?? defaultTab;

    useEffect(() => {
        if (
            (activeTab === "branding" && !canBranding) ||
            (activeTab === "permissions" && !canPermissions)
        ) {
            navigate({
                search: (prev) => ({ ...prev, tab: defaultTab }),
            });
        }
    }, [activeTab, canBranding, canPermissions, defaultTab, navigate]);

    return (
        <PageLayout>
            <div>
                <h1 className="text-2xl font-bold tracking-tight">
                    تنظیمات
                </h1>
                <p className="text-sm text-muted-foreground mt-1">
                    مدیریت برندینگ و مجوزهای سیستم
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
            </Tabs>
        </PageLayout>
    );
}
