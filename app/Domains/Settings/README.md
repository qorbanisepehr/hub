# Settings Domain — Development Rules

This document is the source of truth for **adding to or changing the settings system**. It captures the architecture decisions, conventions, and the exact step-by-step checklists to follow so new settings stay consistent. Read it fully before touching anything in `app/Domains/Settings`, `config/settings.php`, `client/src/features/settings`, or the branding assets.

---

## 1. Architecture Overview

Settings have **no database tables and no models**. Everything is:

- **Scalar settings** → one small JSON file per key under `config('settings.storage_path')` (default `storage/app/settings`), named `<key>.json`. Example: `storage/app/settings/branding.json`.
- **Branding images** → files on the dedicated `branding` filesystem disk (`storage/app/branding`, see `config/filesystems.php`). One file per image kind, named `<kind>.<uploaded-extension>` (e.g. `logo.svg`, `favicon.png`). The public URL has **no extension** — it is a fixed route path versioned by file mtime.

```
storage/app/settings/branding.json   ← scalar settings (name, colors)
storage/app/branding/logo.svg        ← uploaded image files (kind + extension)
```

### Data flow (public SPA)

```
app.blade.php
  ├─ inline <style>: :root { --brand, --brand-secondary }     (set server-side → no color FOUC)
  ├─ window.__BRANDING__ = @json(Branding::data())            (full branding payload incl. logo_svg)
  └─ @vite bundle → useBranding() seeds React Query from window.__BRANDING__
```

- `resources/views/app.blade.php` renders server-side brand values directly (title, favicon link, og/twitter meta, preload links, `--brand` CSS vars) and injects `window.__BRANDING__` **before** the bundle loads.
- The client hook `useBranding()` (`client/src/features/settings/hooks/use-branding.ts`) reads that injected payload with `initialData` + `staleTime: Infinity`. There is **no public GET settings endpoint** — the server injects once; admin mutations write their own response back into the cache.
- Brand colors flow to Tailwind via `--color-brand: var(--brand)` (see `client/src/assets/styles/app.css`), so utilities like `text-brand`, `bg-brand` follow the stored color automatically.

---

## 2. File Map

### Backend (`app/Domains/Settings`)

| File | Responsibility |
|------|----------------|
| `Repositories/SettingsRepositoryInterface.php` | Persistence contract (`get`, `set`, `lastModified`, `flush`) |
| `Repositories/FileSettingsRepository.php` | JSON file implementation (atomic temp-file writes, request-scoped in-memory cache) |
| `Services/SettingsService.php` | The single service: `branding()`, `updateBranding()`, `uploadImage()`, `deleteImage()`, `serveImage()`, `flush()`. Bound as a **singleton** in `AppServiceProvider`. |
| `Support/BrandingSettings.php` | Immutable DTO; `toArray()` **is** the JSON contract sent to the client. |
| `Support/Branding.php` | Static read facade for Blade (`name()`, `logoUrl()`, `data()`, …). |
| `Enums/BrandingImageType.php` | One case per image kind; `filename()`, `routeName()`, `config($key, $default)`. |
| `Controllers/BrandingController.php` | Thin HTTP adapter over the service; one upload/delete/serve trio per image kind. |
| `Requests/UpdateBrandingRequest.php` | Validation for scalar fields. |
| `Requests/UploadBrandingImageRequest.php` | Validation for image uploads; derives the kind from the **last URL segment**. |
| `Resources/BrandingResource.php` | Wraps `BrandingSettings` as `{ data: { … } }`. |
| `routes/api.php` | Public + admin routes; required from root `routes/api.php`. |

Supporting config: `config/settings.php` (storage path + per-kind rules), `config/filesystems.php` (`branding` disk), `config/company.php` (default colors), `config/permissions.php` (`branding` group), `database/seeders/AuthorizationSeeder.php` (grant to admin role).

### Client (`client/src/features/settings`)

| File | Responsibility |
|------|----------------|
| `types.ts` | `BrandingSettings` (must mirror DTO `toArray()` keys), `UpdateBrandingData`. |
| `api.ts` | `updateBranding`, `uploadBrandingImage(kind, file)`, `deleteBrandingImage(kind)`; `BrandingImageKind` union. |
| `hooks/use-branding.ts` | `useBranding()` (seeds from `window.__BRANDING__`), mutations + `writeBranding`, `useBrandingImage(kind)`. |
| `branding-css.ts` | `applyBrandingCss()` → sets `--brand` / `--brand-secondary`. |
| `pages/settings-page.tsx` | Tabbed `/settings` page (tabs driven by permissions). |
| `pages/branding-settings-page.tsx` | The branding form; `IMAGE_SLOTS` config array + `BrandingImageSlot`. |

Shared pieces: `client/src/components/shared/image-upload.tsx` (generic upload surface), `client/src/components/shared/logo.tsx` (`Logo`, `LogoType`, `useBrandImage`, `recolorSvgContent`), `client/src/components/shared/qr-code.tsx`.

Other touch points: `resources/views/app.blade.php`, `client/vite-env.d.ts`, `client/src/lib/query-keys.ts`, `client/src/lib/permissions.ts`, `client/src/routes/_protected/settings.tsx`, sidebar nav (`nav-main.tsx`), and every component that renders `Logo`/`LogoType` or brand text.

---

## 3. Core Conventions (invariants)

1. **No DB.** Settings are JSON files; images are files on the `branding` disk. Do not add tables/models for settings.
2. **snake_case** for JSON keys, API fields, URL segments, and config keys. The DTO property is camelCase (`logoUrl`), `toArray()` emits snake_case (`logo_url`).
3. **`toArray()` is the contract.** Any field you want the client (or Blade) to read must be added to `BrandingSettings::toArray()`, the PHPDoc array shape, the client `BrandingSettings` type, and `vite-env.d.ts`.
4. **`branding()` is memoized per request.** `SettingsService` caches it; every mutation reassigns `$this->brandingCache = $this->buildBranding()`. `flush()` clears both service + repository caches.
5. **Public image URLs are immutable & versioned.** GET routes serve with `Cache-Control: public, max-age=31536000, immutable` and the URL includes `?v=<file mtime>`. A re-upload → new mtime → new URL → never stale. Never change these routes to be mutable.
6. **The URL segment IS the enum value.** `UploadBrandingImageRequest::imageType()` does `BrandingImageType::tryFrom(end($segments))`. That is why the OG image uses `og_image` (underscore) in routes, config, and enum — **the segment must match the enum case exactly** or the upload 422s.
7. **Client must never detect SVG by URL extension.** The public URL (`…/settings/branding/logo`) has no extension. SVG-vs-raster is decided server-side and communicated via injected content (`logo_svg`).
8. **SVG logos are recolored, not shown raw.** Server injects the raw SVG markup into the payload; the client rewrites fixed `fill`/`stroke`/`stop-color` to `currentColor` and renders it inline inside a `text-brand` element. This keeps the logo in sync with the live brand color **without a client fetch** (fetch = on-load flash of the generic logo — never reintroduce it).
9. **No image upload/delete endpoint is public.** All mutations sit behind `auth:sanctum` + `permission:branding.manage`. Public GETs are unauthenticated (serving images).
10. **Admin mutations return the full new `BrandingSettings`** (not a 204). The client writes that response into the React Query cache so the same tab updates instantly.
11. **Blade sets `--brand` inline in `<head>`.** This prevents a color flash before React mounts. Keep it.

---

## 4. Adding a New Branding Image Kind (checklist)

This is the favicon/og_image playbook. Say the kind is `mark` (a stamp):

1. **`config/settings.php`** — add a block:
   ```php
   'mark' => [
       'filename' => 'mark',
       'allowed_types' => ['jpg', 'jpeg', 'png', 'webp', 'svg'],
       'max_size_kb' => 2048,
   ],
   ```
2. **`BrandingImageType` enum** — add `case Mark = 'mark';`. That string becomes the route segment, config key, and API kind all at once. Nothing else to implement (the trio methods are shared).
3. **`SettingsService::buildBranding()`** — resolve the file (`findImageFile`), URL (`imageUrl`), and — only if the client renders it inline — the content (`svgContent`), then pass them to the DTO.
4. **`BrandingSettings` DTO** — add `markUrl` and (if inline-rendered) `markSvg` fields in **constructor order**, extend `toArray()` + its PHPDoc, and update `Branding::data()` PHPDoc. Add a `Branding::markUrl()` accessor only if Blade needs it.
5. **`BrandingController`** — add `uploadMark`, `deleteMark`, `mark` (serve) methods.
6. **`routes/api.php`** — add the public GET (`settings/branding/mark`) + admin POST/DELETE. The URL segment must be `mark`.
7. **Blade (`app.blade.php`)** — add whatever the kind needs (favicon → `<link rel="icon">`, og_image → og meta; a stamp may need nothing).
8. **Client** — `types.ts`, `vite-env.d.ts`, `FALLBACK_BRANDING`/`getInitialBranding`, `BrandingImageKind` union in `api.ts`, and an `IMAGE_SLOTS` entry in `branding-settings-page.tsx` (url accessor, placeholder, container className). If it is rendered inline anywhere, wire it through `useBrandImage(url, data?.mark_svg)`.
9. **Tests** — in `BrandingSettingsTest.php`, mirror the favicon tests: upload (asserts URL + `mark_svg` for SVG uploads), delete (asserts URL + svg null), immutable serve, 404-when-missing, invalid-type 422. Raster upload → `mark_svg` stays null.
10. **Verification** — see §7.

### Rules for `allowed_types` per kind
- `svg` and `ico` are NOT acceptable for the social-share (`og_image`) kind — keep its list to `jpg, jpeg, png, webp`.
- If a kind is not rendered inline, do not add the `*Svg` DTO field.

---

## 5. Adding / Changing a Scalar Setting

Example: add an `address` field to branding.

1. **`SettingsService::updateBranding()`** — add `'address'` to the field loop.
2. **`UpdateBrandingRequest`** — add `'address' => ['sometimes', 'nullable', 'string', 'max:255']` (Persian messages only if you need custom ones; keep hex regex rules for colors).
3. **`BrandingSettings` DTO** — add `address` (constructor, `toArray`, PHPDoc).
4. **Facade** — add `Branding::address()` if Blade renders it.
5. **Blade** — render it where appropriate (title/meta/header).
6. **Client** — `types.ts`, `vite-env.d.ts`, fallback seed, and the form field + schema on the branding page (use the existing `FormColorField`/`FormTextField` patterns). Use `writeBranding` response as the source of truth.
7. **Tests** — assert the update persists the value and the validation rejects bad input.

Rules:
- Every scalar lives in the **same `branding` JSON key** unless you are genuinely building a second settings group (see §6).
- Colors are **hex only** (`#rgb` or `#rrggbb`), validated server- and client-side.
- Do not store computed/derived values (`version`, image URLs, SVG content) — those are derived at read time in `buildBranding()`.

---

## 6. Adding a New Settings Section / Tab

1. **Permission** (if section-gated): add a group to `config/permissions.php`, grant it to roles in `database/seeders/AuthorizationSeeder.php`, add the const to `client/src/lib/permissions.ts`, and re-run `php artisan db:seed --class=AuthorizationSeeder` (then `php artisan optimize:clear`). The settings route guard + tab gating read these permissions.
2. **Route** — extend `client/src/routes/_protected/settings.tsx` `validateSearch` enum with the new tab and add it to the `beforeLoad` permission list.
3. **Page** — add the section component and a `TabsTrigger`/`TabsContent` pair in `settings-page.tsx`, wrapped in `PermissionGuard`. Add a sidebar item in `nav-main.tsx` using `search: { tab: "…" }`.
4. Follow §4/§5 for whatever the section manages.

---

## 7. Verification (run all before finishing)

```bash
vendor/bin/pint --dirty --format agent          # PHP style (fixes, not --test)
php artisan test --compact --filter=Branding    # branding feature tests
npx tsc --noEmit                                # from client/ — TS types
npm run build                                   # from client/ — bundle
```

- Every PHP change is covered by a test — add/update `BrandingSettingsTest.php` alongside the change.
- **Windows gotcha:** `UploadedFile::fake()->create(...)` produces a 0-byte temp file on Windows. Use `UploadedFile::fake()->image('favicon.png', 32, 32)` for raster uploads or `UploadedFile::fake()->createWithContent('logo.svg', '<svg…>')` for SVG content assertions.
- After changing `config/permissions.php` or the seeder, re-seed and clear config/route caches before testing.

---

## 8. Frequent Mistakes to Avoid

- ❌ Detecting SVG from the URL (`/\.svg/` on the public URL) — the URL has no extension. Use the injected `logo_svg`/`logotype_svg` content.
- ❌ Re-introducing a client `fetch` + re-render to show uploaded logos — it causes the generic→uploaded flash. Injected content is synchronous.
- ❌ A route segment that doesn't match the enum value (e.g. `og-image` vs `og_image`) — uploads 422.
- ❌ Forgetting to add a new field to both `toArray()` and the client `BrandingSettings`/`vite-env.d.ts` — the field silently never reaches the SPA.
- ❌ Making public image routes mutable or dropping the `?v=mtime` versioning — browsers cache forever and show stale logos.
- ❌ Adding a DTO `*Svg` field for kinds that are never rendered inline (e.g. `og_image`) — bloats every HTML response.
- ❌ Editing settings JSON by hand while a server is running — the service caches per request; prefer `php artisan tinker` or the admin UI, then hard-refresh.
