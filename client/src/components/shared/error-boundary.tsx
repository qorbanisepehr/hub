import { Component, type ReactNode } from "react";
import { useLocation } from "@tanstack/react-router";

import { ErrorPage } from "@/components/shared/error-page";

interface Props {
    children: ReactNode;
    fallback?: ReactNode;
}

interface State {
    hasError: boolean;
    error: Error | null;
}

class ErrorBoundaryInner extends Component<Props, State> {
    state: State = { hasError: false, error: null };

    static getDerivedStateFromError(error: Error): State {
        return { hasError: true, error };
    }

    componentDidCatch(error: Error, info: React.ErrorInfo) {
        console.error("ErrorBoundary caught:", error, info.componentStack);
    }

    render() {
        if (this.state.hasError) {
            return this.props.fallback ?? <ErrorPage status={500} />;
        }
        return this.props.children;
    }
}

function ErrorBoundaryWrapper({ children, fallback }: Props) {
    const location = useLocation();

    return (
        <ErrorBoundaryInner key={location.href} fallback={fallback}>
            {children}
        </ErrorBoundaryInner>
    );
}

export { ErrorBoundaryWrapper as ErrorBoundary };
