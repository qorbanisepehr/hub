export type User = {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    username: string | null;
};

export type LoginResponse = {
    message: string;
    destination?: string;
    user?: User;
    retry_after?: number;
};

export type LoginMode = "otp" | "password";
