export type TempEmployee = {
    id: number;
    personnel_code: string;
    id_number: string | null;
    first_name: string;
    last_name: string;
};

export type TempFileNode = {
    name: string;
    path: string;
    type: "dir" | "file";
    size: number | null;
    mime: string | null;
    modified_at: string | null;
};
