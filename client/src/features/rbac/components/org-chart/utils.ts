export function getInitials(name: string): string {
    const parts = name.trim().split(/\s+/);
    return parts
        .slice(0, 2)
        .map((part) => part[0] ?? "")
        .join("");
}

export function stopPropagation(handler?: (id: number) => void, id?: number) {
    return (event: React.MouseEvent) => {
        event.stopPropagation();
        event.preventDefault();
        if (handler && id != null) {
            handler(id);
        }
    };
}
