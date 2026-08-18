import { useTheme } from "next-themes";
import { Button } from "@/components/ui/button";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { IconMoon, IconSun, IconDeviceLaptop } from "@tabler/icons-react";

const themes = [
    { value: "light", label: "روشن", icon: IconSun },
    { value: "dark", label: "تاریک", icon: IconMoon },
    { value: "system", label: "سیستم", icon: IconDeviceLaptop },
] as const;

export function ThemeToggle() {
    const { theme, setTheme } = useTheme();

    const ThemeIcon = themes.find((t) => t.value === theme)?.icon ?? IconSun;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger render={<Button variant="ghost" size="icon" aria-label="تغییر پوسته" />}>
                <ThemeIcon className="size-5" />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                <DropdownMenuRadioGroup
                    value={theme}
                    onValueChange={(v) => setTheme(v)}
                >
                    {themes.map(({ value, label, icon: Icon }) => (
                        <DropdownMenuRadioItem key={value} value={value}>
                            <Icon className="size-4" />
                            {label}
                        </DropdownMenuRadioItem>
                    ))}
                </DropdownMenuRadioGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
