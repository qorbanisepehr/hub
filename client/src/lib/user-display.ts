export function getUserDisplayName(user: {
    name: string;
    employee?: { first_name: string; last_name: string } | null;
}): string {
    if (user.employee) {
        return [user.employee.first_name, user.employee.last_name]
            .filter(Boolean)
            .join(" ");
    }

    return user.name;
}
