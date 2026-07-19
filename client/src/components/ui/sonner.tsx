import { useTheme } from "next-themes";
import { Toaster as Sonner, type ToasterProps } from "sonner";
import {
    IconCircleCheck,
    IconInfoCircle,
    IconAlertTriangle,
    IconAlertOctagon,
    IconLoader,
} from "@tabler/icons-react";

const Toaster = ({ ...props }: ToasterProps) => {
    const { theme = "system" } = useTheme();

    return (
        <Sonner
            theme={theme as ToasterProps["theme"]}
            className="toaster group"
            dir="rtl"
            icons={{
                success: <IconCircleCheck className="size-4 text-success" />,
                info: <IconInfoCircle className="size-4 text-info" />,
                warning: <IconAlertTriangle className="size-4 text-warning" />,
                error: <IconAlertOctagon className="size-4 text-destructive" />,
                loading: <IconLoader className="size-4 animate-spin" />,
            }}
            toastOptions={{
                classNames: {
                    toast: "group toast group-[.toaster]:bg-background group-[.toaster]:text-foreground group-[.toaster]:border-border group-[.toaster]:shadow-lg",
                    description: "group-[.toast]:text-muted-foreground",
                    actionButton:
                        "group-[.toast]:bg-primary group-[.toast]:text-primary-foreground",
                    cancelButton:
                        "group-[.toast]:bg-muted group-[.toast]:text-muted-foreground",
                },
            }}
            {...props}
        />
    );
};

export { Toaster };
