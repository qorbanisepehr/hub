import axios from "axios";
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

        return Promise.reject(error);
    },
);
