import axios from "axios";
import { toast } from "sonner";
import { authClient } from "@/features/auth/auth-client";

export const api = axios.create({
    baseURL: "/api",
    withCredentials: true,
    withXSRFToken: true,
    headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
    },
});

api.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 401) {
            authClient.clearSession();
        }

        if (error.response?.status === 403) {
            const message =
                error.response?.data?.message ?? "شما مجوز این عملیات را ندارید";
            toast.error(message);
        }

        return Promise.reject(error);
    },
);
