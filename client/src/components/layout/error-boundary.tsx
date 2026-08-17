import { Component, type ReactNode } from "react";

import { ErrorPage } from "@/components/layout";

interface Props {
    children: ReactNode;
    fallback?: ReactNode;
    homeTo?: string;
    onReset?: () => void;
}

interface State {
    hasError: boolean;
    error: Error | null;
}

export class ErrorBoundary extends Component<Props, State> {
    state: State = { hasError: false, error: null };

    static getDerivedStateFromError(error: Error): State {
        return { hasError: true, error };
    }

    componentDidCatch(error: Error, info: React.ErrorInfo) {
        console.error("ErrorBoundary caught an error:", error, info.componentStack);
    }

    private resetError = () => {
        this.setState({ hasError: false, error: null });
        this.props.onReset?.();
    };

    render() {
        if (this.state.hasError) {
            if (this.props.fallback) {
                return this.props.fallback;
            }

            return (
                <ErrorPage
                    status={500}
                    title="خطای غیرمنتظره در رابط کاربری"
                    homeTo={this.props.homeTo ?? "/"}
                />
            );
        }

        return this.props.children;
    }
}
