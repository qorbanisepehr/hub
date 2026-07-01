import { LoginForm } from "@/features/auth/components/login-form";

export function LoginPage({ redirectTo }: { redirectTo?: string }) {
    return (
        <div className="flex min-h-svh w-full items-center justify-center p-6 md:p-10">
            <div className="w-full max-w-sm">
                <LoginForm redirectTo={redirectTo} />
            </div>
        </div>
    );
}
