import { createStore } from "@tanstack/react-store";

/**
 * Employee profile view state (P1 of the state-management migration plan,
 * docs/state-management-migration-plan.md).
 *
 * The profile view owns the active tab (via `useWizardState`, which syncs the
 * URL hash). The page header's edit button needs the same value to deep-link
 * the edit form to the section the user was reading — a genuine cross-subtree
 * read, so it lives here instead of being threaded through an
 * `onActiveTabChange` callback prop.
 *
 * One-way sync: `useWizardState` remains the source of truth; the view writes
 * the value here, consumers only read.
 *
 * No-mirror rule: no server data in this store — tab keys only.
 */
type ProfileViewState = {
    /** Active section key, e.g. "contracts". Null when no profile is open. */
    activeTab: string | null;
};

export const profileViewStore = createStore<ProfileViewState>({
    activeTab: null,
});

export function setActiveTab(key: string): void {
    profileViewStore.setState(() => ({ activeTab: key }));
}

export function clearActiveTab(): void {
    profileViewStore.setState(() => ({ activeTab: null }));
}
