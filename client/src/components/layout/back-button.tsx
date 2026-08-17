import { Link } from "@tanstack/react-router";
import { IconArrowRight } from "@tabler/icons-react";
import { Button } from "@/components/ui/button";

export function BackButton({
    to,
    label,
}: {
    to: string;
    label?: string;
}) {
    if (label) {
        return (
            <Button variant="outline" nativeButton={false} render={<Link to={to} />}>
                {label}
            </Button>
        );
    }

    return (
        <Button variant="ghost" size="icon-sm" nativeButton={false} render={<Link to={to} />}>
            <IconArrowRight className="size-4" />
        </Button>
    );
}
