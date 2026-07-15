import { useQuery } from "@tanstack/react-query";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { fetchRoles } from "@/features/rbac/api";
import type { Role } from "@/features/rbac/types";

interface RoleSelectProps {
    value?: string;
    onValueChange: (value: string | null) => void;
    placeholder?: string;
    clearable?: boolean;
    clearLabel?: string;
    disabled?: boolean;
    excludeIds?: number[];
}

export function RoleSelect({
    value,
    onValueChange,
    placeholder = "انتخاب نقش",
    clearable = false,
    clearLabel = "بدون",
    disabled = false,
    excludeIds = [],
}: RoleSelectProps) {
    const { data: rolesData, isLoading } = useQuery({
        queryKey: ["roles"],
        queryFn: async () => {
            const { data } = await fetchRoles();
            return data;
        },
    });

    const roles = rolesData?.data?.filter((role) => !excludeIds.includes(role.id)) ?? [];
    const roleMap = new Map<string, string>(
        roles.map((r: Role) => [String(r.id), r.display_name]),
    );

    return (
        <Select
            value={value ?? null}
            onValueChange={(val) => onValueChange(val === "__clear__" ? null : val)}
            disabled={disabled || isLoading}
            itemToStringLabel={(val) => {
                if (!val) return "";
                if (val === "__clear__") return clearLabel;
                return roleMap.get(val as string) ?? val;
            }}
        >
            <SelectTrigger>
                <SelectValue placeholder={isLoading ? "در حال بارگذاری..." : placeholder} />
            </SelectTrigger>
            <SelectContent>
                {clearable && value && (
                    <SelectItem value="__clear__">{clearLabel}</SelectItem>
                )}
                {roles.map((role: Role) => (
                    <SelectItem key={role.id} value={String(role.id)}>
                        {role.display_name}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}
