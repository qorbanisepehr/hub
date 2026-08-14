import type { ReactNode } from "react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";

type SectionCardProps = {
    title: string;
    action?: ReactNode;
    children: ReactNode;
};

export function SectionCard({ title, action, children }: SectionCardProps) {
    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between">
                <CardTitle>{title}</CardTitle>
                {action}
            </CardHeader>
            <CardContent>{children}</CardContent>
        </Card>
    );
}

export function SectionEditButton({
    onClick,
    label = "ویرایش",
}: {
    onClick: () => void;
    label?: string;
}) {
    return (
        <button
            type="button"
            className="text-sm text-primary hover:underline"
            onClick={onClick}
        >
            {label}
        </button>
    );
}
