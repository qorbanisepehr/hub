import type {
    AccessRulePolicyCondition,
    RuleBuilderMeta,
} from "@/features/rbac/types";

export const NULLARY_OPERATORS = new Set([
    "is_null",
    "is_not_null",
    "exists",
    "not_exists",
]);

export const SET_OPERATORS = new Set(["in", "not_in"]);

export const EFFECT_LABELS: Record<"allow" | "deny", string> = {
    allow: "اجازه دسترسی",
    deny: "منع دسترسی",
};

export const VALUE_SOURCE_KEYS = ["literal", "actor", "resource", "context"] as const;

export type ConditionDraft = {
    attribute: string;
    operator: string;
    value_source: (typeof VALUE_SOURCE_KEYS)[number];
    value: string;
};

export type EditorDraft = {
    permission_id: number | null;
    effect: "allow" | "deny";
    priority: string;
    is_active: boolean;
    conditions: ConditionDraft[];
};

export const emptyCondition = (): ConditionDraft => ({
    attribute: "",
    operator: "",
    value_source: "literal",
    value: "",
});

export const emptyDraft = (): EditorDraft => ({
    permission_id: null,
    effect: "allow",
    priority: "0",
    is_active: true,
    conditions: [],
});

export function policyToDraft(policy: { all?: AccessRulePolicyCondition[] } | null | undefined): ConditionDraft[] {
    if (!policy?.all) return [];

    return policy.all.map((condition) => ({
        attribute: condition.attribute,
        operator: condition.operator,
        value_source: (VALUE_SOURCE_KEYS as readonly string[]).includes(
            condition.value_source,
        )
            ? (condition.value_source as ConditionDraft["value_source"])
            : "literal",
        value:
            condition.value_source === "actor" &&
            typeof condition.value === "string"
                ? toUserAttributeKey(condition.value)
                : serializeConditionValue(condition.value),
    }));
}

export function serializeConditionValue(value: unknown): string {
    if (Array.isArray(value)) return value.join("، ");
    if (value === null || value === undefined) return "";
    return String(value);
}

export function toUserAttributeKey(value: string): string {
    return value.startsWith("user.") ? value : `user.${value}`;
}

export function fromUserAttributeKey(value: string): string {
    return value.replace(/^user\./, "");
}

export function attributeTypeFor(
    meta: RuleBuilderMeta | undefined,
    resourceKey: string | null,
    attributeKey: string,
): string | undefined {
    if (!meta) return undefined;
    const resourceType = meta.resource_types.find((rt) => rt.key === resourceKey);
    return resourceType?.attributes.find((attr) => attr.key === attributeKey)?.type;
}

export function conditionNeedsValue(operator: string): boolean {
    return !NULLARY_OPERATORS.has(operator);
}

export function conditionToLeaf(
    condition: ConditionDraft,
    attributeType: string | undefined,
): AccessRulePolicyCondition {
    const leaf: AccessRulePolicyCondition = {
        attribute: condition.attribute,
        operator: condition.operator,
        value_source: condition.value_source,
    };

    if (!conditionNeedsValue(condition.operator)) {
        leaf.value = null;
        return leaf;
    }

    if (condition.value_source !== "literal") {
        leaf.value =
            condition.value_source === "actor"
                ? fromUserAttributeKey(condition.value)
                : condition.value;
        return leaf;
    }

    if (SET_OPERATORS.has(condition.operator)) {
        leaf.value = condition.value
            .split(/[،,]/)
            .map((part) => part.trim())
            .filter(Boolean);
        return leaf;
    }

    if (attributeType === "integer" && condition.value !== "") {
        leaf.value = Number(condition.value);
        return leaf;
    }

    leaf.value = condition.value;
    return leaf;
}
