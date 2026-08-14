import { SectionRow } from "./section-row";

export function InfoRow({
    label,
    value,
}: {
    label: string;
    value: React.ReactNode;
}) {
    return <SectionRow label={label} value={value} />;
}
