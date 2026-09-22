# Client Refactoring Plan — Merged & Final

**Date:** 2026-08-17
**Branch:** `refactor/architecture`
**Codebase:** 339 files, 41K lines, 10 features

---

## Part 1: Issue Inventory

### CRITICAL

| # | File | Issue |
|---|------|-------|
| C1 | `hooks/use-table-url-state.ts:242` | Conditional `useCallback` inside ternary — React forbids hooks inside conditions |

### HIGH — God Components

| # | File | Lines | Responsibilities Mixed |
|---|------|------:|----------------------|
| H1 | `rbac/components/rule-builder.tsx` | 1,059 | 3 components, draft state, preview, condition builder, mutation, dialog |
| H2 | `rbac/components/role-form.tsx` | 759 | Schema, helper components, main form |
| H3 | `components/shared/file-upload-field.tsx` | 613 | 4 variant render paths, upload/delete mutations, category lookup |
| H4 | `components/shared/document-viewer.tsx` | 564 | 5 view modes, grouping logic, preview lightbox bridge |
| H5 | `rbac/components/org-chart/RoleOrgChart.tsx` | 468 | Layout, collapse, filter, fullscreen, modal, React Flow |
| H6 | `rbac/components/org-chart/RoleDetailModal.tsx` | 439 | Tab management, user assignment, role display |

### HIGH — DRY Violations

| # | Files | What's Duplicated |
|---|-------|-------------------|
| D1 | `cv-wizard.tsx` + `questionnaire-wizard.tsx` | ~650 lines step navigation, hash sync, submit, validation |
| D2 | `org-chart/CustomNode.tsx` + `org-chart/UsersNode.tsx` | `getInitials()`, `stopPropagation()`, `MAX_AVATARS`, Handle styling |
| D3 | `org-chart/` 3 files | `getInitials()` defined 3 times |
| D4 | `file-upload-field.tsx` + `document-file-item.tsx` | Delete mutation logic |
| D5 | `search-select-modal.tsx` + `permission-add-modal.tsx` | Infinite scroll with `requestAnimationFrame` throttle |
| D6 | CV sections `education-section.tsx`, `skills-section.tsx`, etc. | Pure pass-through wrappers that just add `entity="cv"` |

### MEDIUM — State Anti-Patterns

| # | File | Issue |
|---|------|-------|
| S1 | `rule-builder.tsx` | 6 useState for deeply nested draft — should useReducer |
| S2 | `employees/sections/documents-section.tsx` | 10 useState — strongest useReducer candidate |
| S3 | `org-chart/RoleOrgChart.tsx` | 5+ useState for layout/collapse/filter/modal |
| S4 | Dialog-open state reset via useEffect | rule-builder, RoleDetailModal, ExportChartDialog — should use `key` remount |
| S5 | 3 wizard files | Identical `useEffect(() => { if (isDirty) setSubmitErrors([]) })` |

### MEDIUM — Type Safety

| # | File | Issue |
|---|------|-------|
| T1 | CV + Questionnaire `WizardFormValues` | `[key: string]: unknown` index signature |
| T2 | Employee form + wizards | `form as never` casts 19+ times |
| T3 | `rbac/types.ts` | `AccessRulePolicy` uses `Record<string, unknown>` |

### LOW — Performance

| # | File | Issue |
|---|------|-------|
| P1 | `components/reui/stepper.tsx:28` | Context provider value not memoized |
| P2 | `rbac/pages/users-page.tsx:153` | `roleFilterOptions` not memoized |
| P3 | `rbac/components/org-chart/ExportChartDialog.tsx:59` | `rootOptions` not memoized |
| P4 | `rule-builder.tsx` | ~25 inline arrow functions in JSX |
| P5 | `document-thumbnail.tsx` | ~12 inline arrow functions |

### LOW — Code Quality

| # | File | Issue |
|---|------|-------|
| Q1 | `rule-builder.tsx:443` | `eslint-disable react-hooks/exhaustive-deps` |
| Q2 | `otp-verification-form.tsx:68` | `eslint-disable react-hooks/exhaustive-deps` |
| Q3 | `form-option-fields.tsx:205` | `eslint-disable react-hooks/exhaustive-deps` |
| Q4 | `org-chart/UsersNode.tsx` | Commented-out dead code (lines 121-130, 186-191, 209-213) |
| Q5 | `cv-wizard.tsx:62`, `questionnaire-wizard.tsx:79` | `SECTION_COMPONENTS` array declared but never used |
| Q6 | `home-page.tsx` | Duplicated CTA block (hero + bottom) |

---

## Part 2: Directory Structure Overhaul

### Current Problems

| Problem | Detail |
|---------|--------|
| `components/shared/` dumping ground | 45 files — sections, forms, documents, layout, navigation mixed |
| No barrel files | Only `data-table/index.ts` exists |
| Empty directories | `file-upload-field/variants/`, `document-file-item/layouts/` |
| Inconsistent feature structure | auth has hooks at root, others use `hooks/` dir |
| `lib/` has feature-specific code | `file-colors.ts`, `file-icon.tsx`, `permissions.ts` |
| Component naming inconsistency | `CustomNode.tsx` (PascalCase) vs `rule-builder.tsx` (kebab-case) |

### New Structure

```
src/
├── assets/
├── components/
│   ├── ui/                          # shadcn primitives
│   │   └── index.ts
│   ├── reui/                        # REUI components (stepper, etc.)
│   │   └── index.ts
│   ├── data-table/                  # DataTable compound component
│   │   └── index.ts
│   ├── documents/                   # Document-related shared components
│   │   ├── file-upload-field.tsx
│   │   ├── file-upload-field/
│   │   │   └── variants/
│   │   ├── document-file-item.tsx
│   │   ├── document-file-item/
│   │   │   └── layouts/
│   │   ├── document-viewer.tsx
│   │   ├── document-viewer/
│   │   │   └── views/
│   │   ├── document-thumbnail.tsx
│   │   ├── document-preview-trigger.tsx
│   │   ├── document-actions.tsx
│   │   └── index.ts
│   ├── sections/                    # Form section components
│   │   ├── personal-info-section.tsx
│   │   ├── contact-info-section.tsx
│   │   ├── education-section.tsx
│   │   ├── work-experience-section.tsx
│   │   ├── skills-section.tsx
│   │   ├── training-section.tsx
│   │   ├── additional-info-section.tsx
│   │   ├── section-card.tsx
│   │   └── index.ts
│   ├── section-views/               # Read-only section views
│   │   ├── personal-info-view.tsx
│   │   ├── contact-info-view.tsx
│   │   ├── education-view.tsx
│   │   ├── work-experience-view.tsx
│   │   ├── skills-view.tsx
│   │   ├── training-view.tsx
│   │   ├── additional-info-view.tsx
│   │   └── index.ts
│   ├── forms/                       # Shared form components
│   │   ├── form-fields.tsx
│   │   ├── form-option-fields.tsx
│   │   ├── form-repeater.tsx
│   │   ├── form-validation-summary.tsx
│   │   ├── address-form.tsx
│   │   ├── physical-condition-fields.tsx
│   │   ├── otp-verification-form.tsx
│   │   ├── otp-verified-input.tsx
│   │   └── index.ts
│   ├── layout/                      # Layout & page-level
│   │   ├── page-header.tsx
│   │   ├── page-layout.tsx
│   │   ├── page-skeleton.tsx
│   │   ├── view-skeleton.tsx
│   │   ├── table-skeleton.tsx
│   │   ├── empty-state.tsx
│   │   ├── error-banner.tsx
│   │   ├── error-boundary.tsx
│   │   ├── error-page.tsx
│   │   ├── error-section.tsx
│   │   ├── back-button.tsx
│   │   ├── unsaved-changes-dialog.tsx
│   │   └── index.ts
│   ├── wizards/                     # Wizard infrastructure
│   │   ├── wizard-stepper.tsx
│   │   ├── wizard-actions.tsx
│   │   ├── wizard-layout.tsx
│   │   └── index.ts
│   ├── navigation/                  # Navigation components
│   │   ├── user-menu.tsx
│   │   ├── theme-toggle.tsx
│   │   ├── logo.tsx
│   │   ├── public-header.tsx
│   │   └── index.ts
│   └── shared/                      # Miscellaneous (minimal)
│       ├── access-gate.tsx
│       ├── avatar-upload.tsx
│       ├── base-dropzone.tsx
│       ├── image-upload.tsx
│       ├── info-row.tsx
│       ├── qr-code.tsx
│       ├── share-dialog.tsx
│       ├── timeline.tsx
│       └── index.ts
├── features/
│   ├── auth/
│   │   ├── api.ts
│   │   ├── types.ts
│   │   ├── constants.ts
│   │   ├── guards.ts
│   │   ├── components/              # moved from root-level files
│   │   │   └── index.ts
│   │   ├── hooks/                   # moved from root-level files
│   │   │   ├── use-auth.ts
│   │   │   ├── use-authorization.ts
│   │   │   ├── use-can.ts
│   │   │   ├── use-resource-can.ts
│   │   │   └── index.ts
│   │   ├── pages/
│   │   │   └── index.ts
│   │   └── index.ts
│   ├── cv/
│   │   ├── api.ts
│   │   ├── types.ts
│   │   ├── constants.ts
│   │   ├── validation.ts
│   │   ├── schemas/
│   │   │   └── index.ts
│   │   ├── columns.tsx
│   │   ├── components/
│   │   │   ├── cv-wizard.tsx
│   │   │   ├── cv-lifecycle.tsx
│   │   │   ├── cv-resume-view.tsx
│   │   │   ├── sections/
│   │   │   │   └── index.ts
│   │   │   └── index.ts
│   │   ├── hooks/
│   │   │   └── index.ts
│   │   ├── pages/
│   │   │   └── index.ts
│   │   └── index.ts
│   ├── dashboard/
│   │   ├── components/
│   │   │   └── index.ts
│   │   ├── pages/
│   │   │   └── index.ts
│   │   └── index.ts
│   ├── documents/
│   │   ├── api.ts
│   │   ├── types.ts
│   │   ├── constants.ts            # new
│   │   ├── components/
│   │   │   └── index.ts
│   │   ├── hooks/
│   │   │   └── index.ts
│   │   └── index.ts
│   ├── employees/
│   │   ├── api.ts
│   │   ├── types.ts
│   │   ├── constants.ts
│   │   ├── validation.ts
│   │   ├── columns.tsx
│   │   ├── schemas/
│   │   │   └── index.ts
│   │   ├── components/
│   │   │   ├── employee-form.tsx
│   │   │   ├── employee-profile-form.tsx
│   │   │   ├── employee-profile-view.tsx
│   │   │   ├── employee-search-select.tsx
│   │   │   ├── sections/
│   │   │   │   └── index.ts
│   │   │   ├── views/
│   │   │   │   └── index.ts
│   │   │   └── index.ts
│   │   ├── hooks/
│   │   │   └── index.ts
│   │   ├── pages/
│   │   │   └── index.ts
│   │   └── index.ts
│   ├── form-options/
│   │   ├── api.ts
│   │   ├── types.ts
│   │   ├── constants.ts
│   │   ├── schema.ts
│   │   ├── hooks/
│   │   │   └── index.ts
│   │   └── index.ts
│   ├── home/
│   │   ├── constants.ts            # new
│   │   ├── pages/
│   │   │   └── index.ts
│   │   └── index.ts
│   ├── questionnaire/
│   │   ├── api.ts
│   │   ├── types.ts
│   │   ├── constants.ts
│   │   ├── validation.ts
│   │   ├── schemas/
│   │   │   └── index.ts
│   │   ├── components/
│   │   │   ├── questionnaire-wizard.tsx
│   │   │   ├── sections/
│   │   │   │   └── index.ts
│   │   │   └── index.ts
│   │   ├── hooks/
│   │   │   └── index.ts
│   │   ├── pages/
│   │   │   └── index.ts
│   │   └── index.ts
│   ├── rbac/
│   │   ├── api.ts
│   │   ├── types.ts
│   │   ├── constants.ts
│   │   ├── columns.tsx
│   │   ├── user-columns.tsx
│   │   ├── components/
│   │   │   ├── rule-builder.tsx
│   │   │   ├── role-form.tsx
│   │   │   ├── user-form.tsx
│   │   │   ├── org-chart/
│   │   │   │   ├── role-org-chart.tsx      # renamed from RoleOrgChart.tsx
│   │   │   │   ├── role-detail-modal.tsx   # renamed from RoleDetailModal.tsx
│   │   │   │   ├── custom-node.tsx         # renamed from CustomNode.tsx
│   │   │   │   ├── users-node.tsx          # renamed from UsersNode.tsx
│   │   │   │   ├── export-chart-dialog.tsx # renamed from ExportChartDialog.tsx
│   │   │   │   ├── layout-utils.ts
│   │   │   │   └── index.ts
│   │   │   └── index.ts
│   │   ├── hooks/
│   │   │   ├── use-permissions.ts
│   │   │   ├── use-roles.ts
│   │   │   ├── use-users.ts
│   │   │   └── index.ts
│   │   ├── pages/
│   │   │   └── index.ts
│   │   └── index.ts
│   └── settings/
│       ├── api.ts
│       ├── types.ts
│       ├── branding-css.ts
│       ├── hooks/
│       │   └── index.ts
│       ├── pages/
│       │   └── index.ts
│       └── index.ts
├── hooks/                           # Shared hooks (3+ features)
│   ├── use-section-form.ts
│   ├── use-entity-documents.ts
│   ├── use-document-validation.ts
│   ├── use-document-upload.ts       # new
│   ├── use-document-delete.ts       # new
│   ├── use-document-preview.ts      # new
│   ├── use-injected-field-errors.ts
│   ├── use-infinite-scroll.ts       # new
│   ├── use-table-url-state.ts
│   ├── use-debounced-value.ts
│   ├── use-full-screen.ts
│   ├── use-media-query.ts
│   ├── use-mobile.ts
│   └── index.ts
├── lib/                             # Pure utilities (no React)
│   ├── api.ts
│   ├── public-api.ts
│   ├── query-client.ts
│   ├── query-keys.ts
│   ├── error-utils.ts
│   ├── validation-helpers.ts
│   ├── zod-primitives.ts
│   ├── utils.ts
│   ├── constants.ts
│   ├── brand.ts
│   ├── grant.ts
│   ├── permissions.ts
│   ├── user-display.ts
│   ├── date-format.ts
│   ├── field-labels.ts
│   ├── field-rules.ts
│   ├── file-utils.ts                # merged from file-colors.ts, file-icon.tsx, file-size.ts, file-type-label.ts
│   └── index.ts
├── routes/                          # TanStack Router (unchanged)
│   ├── __root.tsx
│   ├── _protected.tsx
│   ├── _protected/
│   ├── _public.tsx
│   ├── _public/
│   └── login.tsx
├── types/                           # Global type augmentations only
│   └── tanstack-table.d.ts
├── main.tsx
└── router.ts
```

### Design Rules

1. **Barrel files everywhere** — every directory gets `index.ts`
2. **kebab-case for all files** — rename `CustomNode.tsx` → `custom-node.tsx`
3. **Feature hooks in `hooks/` subdir** — not at feature root (fix auth)
4. **Shared hooks only in root `hooks/`** — used by 3+ features
5. **`lib/` for pure utils only** — no React components, no feature-specific code
6. **Merge `lib/file-*.ts`** — into single `file-utils.ts`
7. **`types/` for global augmentations only** — feature types stay co-located

---

## Part 3: Phased Execution

### Phase 1: Directory Structure Migration (Day 1)

**Goal:** Move files to new structure, create barrel files, fix imports

| Step | Task | Est. |
|------|------|------|
| 1.1 | Create new directory structure under `components/` | 15m |
| 1.2 | Move files from `components/shared/` to domain directories | 1h |
| 1.3 | Create barrel files (`index.ts`) for every directory | 45m |
| 1.4 | Rename PascalCase files to kebab-case in `rbac/org-chart/` | 15m |
| 1.5 | Move auth hooks into `auth/hooks/` subdirectory | 15m |
| 1.6 | Merge `lib/file-*.ts` into `lib/file-utils.ts` | 20m |
| 1.7 | Update all imports across codebase | 1.5h |
| 1.8 | Verify build passes (`npm run build`) | 10m |

### Phase 2: Critical & Quick Wins (Day 1-2)

**Goal:** Fix hooks violation, memoization, dead code

| Step | Task | Files | Est. |
|------|------|-------|------|
| 2.1 | Fix conditional `useCallback` in `use-table-url-state.ts` | `hooks/use-table-url-state.ts` | 15m |
| 2.2 | Memoize `StepperContext.Provider` value | `components/reui/stepper.tsx` | 10m |
| 2.3 | Remove dead `SECTION_COMPONENTS` arrays | `cv-wizard.tsx`, `questionnaire-wizard.tsx` | 5m |
| 2.4 | Remove commented-out code in `users-node.tsx` | `rbac/org-chart/users-node.tsx` | 5m |
| 2.5 | Add `useMemo` for `roleFilterOptions` and `rootOptions` | `users-page.tsx`, `export-chart-dialog.tsx` | 10m |
| 2.6 | Move `setSubmitErrors([])` into `useSectionForm` | `use-section-form.ts`, remove from 3 wizards | 15m |

### Phase 3: Extract Shared Wizard Infrastructure (Day 2-3)

**Goal:** Eliminate ~650 lines of duplication between CV and Questionnaire wizards

| Step | Task | Files | Est. |
|------|------|-------|------|
| 3.1 | Create `useWizardState` hook | NEW: `components/wizards/use-wizard-state.ts` | 1h |
| 3.2 | Create `useWizardValidation` hook | NEW: `components/wizards/use-wizard-validation.ts` | 45m |
| 3.3 | Create `<WizardStepper>` component | NEW: `components/wizards/wizard-stepper.tsx` | 30m |
| 3.4 | Create `<WizardActions>` component | NEW: `components/wizards/wizard-actions.tsx` | 45m |
| 3.5 | Refactor `CvWizard` to use shared infrastructure | `features/cv/components/cv-wizard.tsx` | 1h |
| 3.6 | Refactor `QuestionnaireWizard` to use shared infrastructure | `features/questionnaire/components/questionnaire-wizard.tsx` | 1h |
| 3.7 | Verify both wizards work correctly | Manual test | 30m |

**`useWizardState` API:**
```ts
function useWizardState(steps: readonly WizardStep[]): {
    currentStep: number;
    goToStep: (step: number) => Promise<void>;
    nextStep: () => void;
    prevStep: () => void;
    isFirstStep: boolean;
    isLastStep: boolean;
    totalSteps: number;
}
```

**`useWizardValidation` API:**
```ts
function useWizardValidation(form, documents, documentsLoading): {
    validation: ValidationResult;
    docMessages: string[];
    canSubmit: boolean;
    optionsReady: boolean;
    injectFieldErrors: () => void;
    clearInjectedErrors: () => void;
}
```

### Phase 4: Extract Org Chart Shared Utilities (Day 3)

**Goal:** Eliminate DRY violations in org-chart components

| Step | Task | Files | Est. |
|------|------|-------|------|
| 4.1 | Create `org-chart/utils.ts` with shared helpers | NEW: `rbac/org-chart/utils.ts` | 20m |
| 4.2 | Extract shared `<NodeToolbar>` component | NEW: `rbac/org-chart/node-toolbar.tsx` | 45m |
| 4.3 | Refactor `custom-node.tsx` to use shared utils + toolbar | `rbac/org-chart/custom-node.tsx` | 20m |
| 4.4 | Refactor `users-node.tsx` to use shared utils + toolbar | `rbac/org-chart/users-node.tsx` | 20m |
| 4.5 | Refactor `role-detail-modal.tsx` to use shared `getInitials` | `rbac/org-chart/role-detail-modal.tsx` | 10m |

### Phase 5: Decompose Rule Builder (Day 4)

**Goal:** Break the 1,059-line god component

| Step | Task | Files | Est. |
|------|------|-------|------|
| 5.1 | Create `useRuleDraft` hook with useReducer | NEW: `rbac/hooks/use-rule-draft.ts` | 1h |
| 5.2 | Extract `RuleEditorDialog` to own file | NEW: `rbac/components/rule-editor-dialog.tsx` | 1h |
| 5.3 | Extract `ConditionRow` to own file | NEW: `rbac/components/condition-row.tsx` | 30m |
| 5.4 | Slim down `rule-builder.tsx` to orchestrator only | `rbac/components/rule-builder.tsx` | 30m |
| 5.5 | Remove `eslint-disable` by fixing dependency array | `rule-editor-dialog.tsx` | 15m |

### Phase 6: Decompose File Upload & Document Components (Day 5)

**Goal:** Split 613-line FileUploadField + deduplicate document hooks

| Step | Task | Files | Est. |
|------|------|-------|------|
| 6.1 | Create `useDocumentUpload` hook | NEW: `hooks/use-document-upload.ts` | 30m |
| 6.2 | Create `useDocumentDelete` hook | NEW: `hooks/use-document-delete.ts` | 20m |
| 6.3 | Create `useDocumentPreview` hook | NEW: `hooks/use-document-preview.ts` | 15m |
| 6.4 | Create `<DocumentPreviewTrigger>` component | NEW: `components/documents/document-preview-trigger.tsx` | 20m |
| 6.5 | Create `<DocumentActions>` component | NEW: `components/documents/document-actions.tsx` | 20m |
| 6.6 | Extract variant sub-components for FileUploadField | `components/documents/file-upload-field/variants/` | 1.5h |
| 6.7 | Extract layout sub-components for DocumentFileItem | `components/documents/document-file-item/layouts/` | 1h |
| 6.8 | Refactor `document-viewer.tsx` into views | `components/documents/document-viewer/views/` | 1h |

### Phase 7: Extract Infinite Scroll Hook (Day 5)

**Goal:** DRY the duplicated infinite scroll pattern

| Step | Task | Files | Est. |
|------|------|-------|------|
| 7.1 | Create `useInfiniteScroll` hook | NEW: `hooks/use-infinite-scroll.ts` | 30m |
| 7.2 | Refactor `search-select-modal.tsx` | `components/shared/search-select-modal.tsx` | 15m |
| 7.3 | Refactor `permission-add-modal.tsx` | `rbac/components/permission-add-modal.tsx` | 15m |

### Phase 8: Consolidate Form State (Day 6)

**Goal:** Replace excessive useState with useReducer

| Step | Task | Files | Est. |
|------|------|-------|------|
| 8.1 | Create `useReducer` for `documents-section.tsx` (10 → 1) | `employees/sections/documents-section.tsx` | 1h |
| 8.2 | Create `useReducer` for `role-org-chart` filter/layout | `rbac/org-chart/role-org-chart.tsx` | 45m |
| 8.3 | Fix dialog-open state reset — use `key` prop | `rule-builder.tsx`, `role-detail-modal.tsx`, `export-chart-dialog.tsx` | 30m |

### Phase 8b: Evaluate TanStack Store Adoption

**Goal:** After Phase 8 consolidates component-local state with `useReducer`, evaluate whether `@tanstack/store` would benefit cross-component/shared UI state.

**Decision criteria:**
- Is there state shared across 3+ components that currently uses prop drilling or context?
- Would fine-grained `useSelector` subscriptions measurably reduce re-renders?
- Is `@tanstack/store` stable enough (currently alpha) for production use?
- Does the team want another TanStack dependency or prefer keeping the stack minimal?

**Candidates for Store (if adopted):** global UI state (sidebar, theme density, feature flags), org-chart filter state shared between parent page and chart component.

**Verdict:** Record decision in `.ai/rules` after evaluation.

---

### Phase 9: Fix Type Safety (Day 6-7)

**Goal:** Eliminate `as never` casts and loose types

| Step | Task | Files | Est. |
|------|------|-------|------|
| 9.1 | Define `WizardFormValues` properly | `cv-wizard.tsx`, `questionnaire-wizard.tsx` | 30m |
| 9.2 | Create `SectionComponentProps` generic type | NEW or in shared types | 20m |
| 9.3 | Eliminate `form as never` casts | 3 wizard/form files | 30m |

### Phase 10: Landing Page & Home Cleanup (Day 7)

| Step | Task | Files | Est. |
|------|------|-------|------|
| 10.1 | Extract `<LandingCTA>` component | `home/components/landing-cta.tsx` | 20m |
| 10.2 | Move `services` and `reasons` to constants | `home/constants.ts` | 10m |

### Phase 11: Accessibility Improvements (Day 7-8)

| Step | Task | Files | Est. |
|------|------|-------|------|
| 11.1 | Add `aria-label` to icon-only buttons | Various | 1h |
| 11.2 | Add `role` attributes to tab/tablist components | Various | 30m |
| 11.3 | Add `aria-live` to dynamic content regions | Various | 30m |
| 11.4 | Fix keyboard navigation in modals/dialogs | Various | 30m |

### Phase 8b: Evaluate TanStack Store Adoption

**Goal:** After Phase 8 consolidates component-local state with `useReducer`, evaluate whether `@tanstack/store` would benefit cross-component/shared UI state.

**Decision criteria:**
- Is there state shared across 3+ components that currently uses prop drilling or context?
- Would fine-grained `useSelector` subscriptions measurably reduce re-renders?
- Is `@tanstack/store` stable enough (currently alpha) for production use?
- Does the team want another TanStack dependency or prefer keeping the stack minimal?

**Candidates for Store (if adopted):** global UI state (sidebar, theme density, feature flags), org-chart filter state shared between parent page and chart component.

**Verdict:** Record decision in `.ai/rules` after evaluation.

---

### Phase 12: Code Splitting (Day 8)

| Step | Task | Files | Est. |
|------|------|-------|------|
| 12.1 | Add `React.lazy` + `Suspense` to route pages | `routes/_protected/` | 1h |
| 12.2 | Add `ErrorBoundary` wrapper | `routes/_protected/` | 30m |

---

## Part 4: Naming Conventions

### File Naming

| Type | Convention | Example |
|------|-----------|---------|
| Components | `kebab-case.tsx` | `rule-builder.tsx`, `file-upload-field.tsx` |
| Hooks | `use-{name}.ts` | `use-section-form.ts`, `use-roles.ts` |
| Types | `types.ts` (co-located) | `features/rbac/types.ts` |
| Schemas | `{name}.schema.ts` | `personal-info.schema.ts` |
| Constants | `constants.ts` (co-located) | `features/employees/constants.ts` |
| Utilities | `kebab-case.ts` | `error-utils.ts`, `file-utils.ts` |

### Component Naming

| Pattern | Convention | Example |
|---------|-----------|---------|
| Page | `{Entity}{Action}Page` | `EmployeeViewPage`, `RoleEditPage` |
| Form | `{Entity}Form` | `RoleForm`, `UserForm` |
| View | `{Entity}ProfileView` | `EmployeeProfileView` |
| Section | `{Section}Section` | `PersonalInfoSection`, `EducationSection` |
| Modal/Dialog | `{Entity}{Action}Modal` | `RoleDetailModal`, `ExportChartDialog` |
| Hook | `use{Entity}` or `use{Action}` | `useRoles`, `useSectionForm` |

### Component Size Guidelines

| Size | Status | Action |
|------|--------|--------|
| < 200 lines | Healthy | No action |
| 200-400 lines | Monitor | Consider extraction |
| 400-600 lines | Needs attention | Extract sub-components or hooks |
| > 600 lines | Must refactor | Split immediately |

### State Management Decision Tree

```
Is it server state? → useQuery/useMutation (TanStack Query)
Is it form state? → useForm (TanStack Form) + Zod validation
Is it UI state with 1-2 values? → useState
Is it UI state with 3+ related values? → useReducer
Is it shared across components? → Custom hook
Is it dialog/modal state? → Use `key` prop remount
```

---

## Part 5: Migration Order & Dependencies

```
Phase 1 (Directory Structure) ← must be first
    ↓
Phase 2 (Critical & Quick Wins)
    ↓
Phase 3 (Wizard Infrastructure) ← depends on Phase 2
    ↓
Phase 4 (Org Chart DRY) ← can parallel with Phase 3
    ↓
Phase 5 (Rule Builder Decompose) ← independent
    ↓
Phase 6 (File Upload & Document Decompose) ← independent
    ↓
Phase 7 (Infinite Scroll Hook) ← independent
    ↓
Phase 8 (Form State Consolidation) ← depends on Phase 3
    ↓
Phase 9 (Type Safety) ← depends on Phase 3, 8
    ↓
Phase 10 (Landing Page) ← independent
    ↓
Phase 11 (Accessibility) ← independent
    ↓
Phase 12 (Code Splitting) ← final
    ↓
Phase 8b (Evaluate TanStack Store) ← decision point after all phases
```

**Parallelizable:** Phase 4+5, Phase 6+7, Phase 10+11

---

## Part 6: Expected Outcomes

| Metric | Before | After |
|--------|--------|-------|
| Files > 600 lines | 2 | 0 |
| Files > 400 lines | 6 | < 2 |
| `form as never` casts | 19+ | 0 |
| `eslint-disable react-hooks/exhaustive-deps` | 3 | 0 |
| Duplicated wizard logic | ~650 lines | 0 |
| DRY violations (org-chart) | ~80 lines | 0 |
| `components/shared/` files | 45 | ~9 (true misc) |
| Directories with barrel files | 1 | All |
| Conditional hook calls | 1 | 0 |
| Un-memoized context providers | 1 | 0 |
| Missing aria-labels | ~20 | 0 |
| Lazy-loaded route pages | 0 | All |
