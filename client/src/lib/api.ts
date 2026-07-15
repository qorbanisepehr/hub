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
        if (axios.isCancel(error)) {
            return Promise.reject(error);
        }

        const status = error.response?.status;

        if (status === 401) {
            authClient.clearSession();
            return Promise.reject(error);
        }

        if (status === 403) {
            const message =
                error.response?.data?.message ?? "شما مجوز این عملیات را ندارید";
            toast.error(message);
            return Promise.reject(error);
        }

        if (status === 500) {
            toast.error("خطای سرور. لطفاً بعداً دوباره تلاش کنید.");
            return Promise.reject(error);
        }

        if (!error.response) {
            toast.error("خطای شبکه. اتصال اینترنت خود را بررسی کنید.");
            return Promise.reject(error);
        }

        return Promise.reject(error);
    },
);
