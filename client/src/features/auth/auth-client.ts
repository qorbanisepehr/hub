const SESSION_KEY = "auth-session";

function getSession(): boolean {
    return localStorage.getItem(SESSION_KEY) === "true";
}

function setSession(): void {
    localStorage.setItem(SESSION_KEY, "true");
}

function clearSession(): void {
    localStorage.removeItem(SESSION_KEY);
}

export const authClient = {
    isAuthenticated: getSession,
    setSession,
    clearSession,
};
