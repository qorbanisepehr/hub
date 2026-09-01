import { Card, CardContent } from "@/components/ui/card";
import { Tabs, TabsList, TabsTrigger, TabsContent } from "@/components/ui/tabs";
import { PermissionSelector } from "@/features/rbac/components/permission-selector";
import { RuleBuilder } from "@/features/rbac/components/rule-builder";
import type { RoleFormApi } from "./role-form-schema";

type AccessCardProps = {
    form: RoleFormApi;
    inheritedPermissionIds: number[];
};

export function RoleAccessCard({
    form,
    inheritedPermissionIds,
}: AccessCardProps) {
    return (
        <form.Field name="access_rules">
            {(rulesField) => {
                const allowedPermissionIds = rulesField.state.value
                    .filter((rule) => rule.effect === "allow")
                    .filter((rule) => rule.is_active !== false)
                    .map((rule) => rule.permission_id);
                const togglePermission = (permId: number) => {
                    const rules = rulesField.state.value;
                    const existing = rules.find(
                        (rule) =>
                            rule.permission_id === permId &&
                            rule.effect === "allow",
                    );

                    rulesField.handleChange(
                        existing
                            ? rules.filter(
                                  (rule) =>
                                      !(
                                          rule.permission_id === permId &&
                                          rule.effect === "allow"
                                      ),
                              )
                            : [
                                  ...rules,
                                  {
                                      permission_id: permId,
                                      effect: "allow" as const,
                                      is_active: true,
                                  },
                              ],
                    );
                };

                const toggleGroup = (
                    _groupId: number,
                    permIds: number[],
                ) => {
                    const rules = rulesField.state.value;
                    const allAllowed = permIds.every((id) =>
                        rules.some(
                            (rule) =>
                                rule.permission_id === id &&
                                rule.effect === "allow",
                        ),
                    );

                    if (allAllowed) {
                        const removed = new Set(permIds);
                        rulesField.handleChange(
                            rules.filter(
                                (rule) =>
                                    !(
                                        rule.effect === "allow" &&
                                        removed.has(rule.permission_id)
                                    ),
                            ),
                        );
                    } else {
                        const existingAllowIds = new Set(
                            rules
                                .filter((rule) => rule.effect === "allow")
                                .map((rule) => rule.permission_id),
                        );
                        const toAdd = permIds.filter(
                            (id) => !existingAllowIds.has(id),
                        );
                        rulesField.handleChange([
                            ...rules,
                            ...toAdd.map((id) => ({
                                permission_id: id,
                                effect: "allow" as const,
                                is_active: true,
                            })),
                        ]);
                    }
                };

                return (
                    <Card>
                        <CardContent className="space-y-3">
                            <Tabs defaultValue="permissions">
                                <TabsList className="w-full">
                                    <TabsTrigger value="permissions">
                                        مجوزها
                                    </TabsTrigger>
                                    <TabsTrigger value="access-rules">
                                        قوانین دسترسی شرطی
                                        {rulesField.state.value.length > 0 &&
                                            ` (${String(rulesField.state.value.length)})`}
                                    </TabsTrigger>
                                </TabsList>
                                <TabsContent value="permissions">
                                    <PermissionSelector
                                        selectedPermissionIds={
                                            allowedPermissionIds
                                        }
                                        inheritedPermissionIds={
                                            inheritedPermissionIds
                                        }
                                        onGroupToggle={toggleGroup}
                                        onPermissionToggle={togglePermission}
                                    />
                                </TabsContent>
                                <TabsContent value="access-rules">
                                    <RuleBuilder
                                        value={rulesField.state.value}
                                        onChange={(rules) =>
                                            rulesField.handleChange(rules)
                                        }
                                    />
                                </TabsContent>
                            </Tabs>
                        </CardContent>
                    </Card>
                );
            }}
        </form.Field>
    );
}