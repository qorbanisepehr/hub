export type TempEmployee = {
    id: number;
    personnel_code: string;
    id_number: string | null;
    first_name: string;
    last_name: string;
    /** Real Employee record matched by personnel code, when one exists. */
    employee: TempEmployeeEmployee | null;
};

export type TempEmployeeEmployee = {
    personnel_code: string;
    first_name: string | null;
    last_name: string | null;
    id_number: string | null;
    email: string | null;
    mobile: string | null;
    gender: string | null;
    employment_status: string | null;
    employment_type: string | null;
    hire_date: string | null;
    roles: TempEmployeeRole[];
};

export type TempEmployeeRole = {
    id: number;
    display_name: string;
    active: boolean;
};

export type TempFileNode = {
    name: string;
    path: string;
    type: "dir" | "file";
    size: number | null;
    mime: string | null;
    modified_at: string | null;
};
