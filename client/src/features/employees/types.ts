export type Employee = {
    id: number;
    personnel_code: string;
    first_name: string;
    last_name: string;
    gender: "male" | "female";
    birth_date: string | null;
    id_number: string | null;
    marital_status: "single" | "married" | null;
    education_level: "diploma" | "associate" | "bachelor" | "master" | "doctorate" | null;
    education_field: string | null;
    employment_type: "official" | "contractual" | "project-based" | null;
    hire_date: string | null;
    employment_status: "active" | "inactive" | "suspended" | null;
    user: { id: number; name: string; email: string } | null;
    created_at: string;
    updated_at: string;
};

export type EmployeeFormData = {
    personnel_code: string;
    first_name: string;
    last_name: string;
    gender: "male" | "female" | "";
    birth_date: string;
    id_number: string;
    marital_status: "single" | "married" | "";
    education_level: "diploma" | "associate" | "bachelor" | "master" | "doctorate" | "";
    education_field: string;
    employment_type: "official" | "contractual" | "project-based" | "";
    hire_date: string;
    employment_status: "active" | "inactive" | "suspended" | "";
    user_id?: number | null;
};

export type PaginatedResponse<T> = {
    data: T[];
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
};
