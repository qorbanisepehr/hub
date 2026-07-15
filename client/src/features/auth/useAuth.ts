import { useEffect } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useNavigate } from "@tanstack/react-router";
import type { User } from "@/features/auth/types";
import { authClient } from "@/features/auth/auth-client";
import {
    fetchMe,
    loginWithPassword,
    logout as logoutApi,
    requestOtp,
    verifyOtp,
} from "@/features/auth/api";
import { ME_KEY } from "@/features/auth/constants";

export function useAuth() {
    const queryClient = useQueryClient();
    const navigate = useNavigate();

    const {
        data: user,
        isLoading,
        isError,
    } = useQuery({
        queryKey: ME_KEY,
        queryFn: async () => {
            const { data } = await fetchMe();

            return data.data;
        },
        enabled: authClient.isAuthenticated(),
        retry: false,
    });

    const isAuthenticated = authClient.isAuthenticated() && !isError;

    useEffect(() => {
        if (isError && !isLoading) {
            queryClient.clear();
            navigate({ to: "/login" });
        }
    }, [isError, isLoading, queryClient, navigate]);

    const loginOtpMutation = useMutation({
        mutationFn: (identifier: string) => requestOtp(identifier),
    });

    const verifyOtpMutation = useMutation({
        mutationFn: ({ identifier, code }: { identifier: string; code: string }) =>
            verifyOtp(identifier, code),
        onSuccess: () => {
            authClient.setSession();
            queryClient.invalidateQueries({ queryKey: ME_KEY });
        },
    });

    const loginPasswordMutation = useMutation({
        mutationFn: ({
            identifier,
            password,
        }: {
            identifier: string;
            password: string;
        }) => loginWithPassword(identifier, password),
        onSuccess: () => {
            authClient.setSession();
            queryClient.invalidateQueries({ queryKey: ME_KEY });
        },
    });

    const logoutMutation = useMutation({
        mutationFn: logoutApi,
        onSettled: () => {
            authClient.clearSession();
            queryClient.setQueryData(ME_KEY, null);
            queryClient.clear();
        },
    });

    return {
        user: user ?? null,
        isLoading,
        isAuthenticated,
        loginOtp: loginOtpMutation,
        verifyOtp: verifyOtpMutation,
        loginPassword: loginPasswordMutation,
        logout: logoutMutation.mutateAsync,
        isLoggingOut: logoutMutation.isPending,
    };
}
