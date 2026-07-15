export function InfoRow({
    label,
    value,
}: {
    label: string;
    value: React.ReactNode;
}) {
    return (
        <div className="flex items-baseline gap-2 py-2 border-b last:border-b-0">
            <span className="text-sm text-muted-foreground min-w-32">
                {label}
            </span>
            <span className="text-sm font-medium">{value ?? "—"}</span>
        </div>
    );
}
