# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

**Commitment Corner** — KPN Corporation's annual Conflict of Interest (COI) declaration app. Laravel 12 + Inertia.js + Vue 3 + Vite + Tailwind, PHP 8.2. Employees (and contracted non-employees) fill in a yearly COI declaration; managers see their team's status; admins report, export, and manage credentials/roles.

## Commands

```bash
composer dev
```
Runs `php artisan serve`, `queue:listen`, `pail` (logs), and `npm run dev` concurrently — the normal way to develop. Queue worker matters: report exports are dispatched as jobs.

Other commands:
- `composer setup` — install, key generate, migrate, npm install, build
- `composer test` — clears config, then `php artisan test` (see note below)
- `npm run build` — production asset build
- `./vendor/bin/pint` — PHP formatter; run before committing
- `php artisan db:seed --class=PermissionSeeder` — (re)seed permissions and the five base roles

There is **no `tests/` directory** in the working tree — `phpunit.xml` declares Unit/Feature suites but `tests/` is gitignored, so `composer test` finds nothing. No ESLint/Prettier config either. Validate changes with `npm run build`, `./vendor/bin/pint`, and manual exercise of the affected screens.

## Two databases — the single most important thing to know

Two MySQL connections, configured in `config/database.php`:

- **`mysql`** (default) — the app's own database: `coi_declarations`, `coi_responses`, `non_employees`, `users` (non-employee logins), `roles`/`permissions`, `report_downloads`, `jobs`.
- **`kpncorp`** — the read-mostly HRIS database: `employees`, its `users` (employee logins, via `App\Models\User`), `locations`, `master_bisnisunits`, `companies`, `departments`.

Consequences you must respect:

1. **No cross-database joins or `whereHas`.** `CoiDeclaration::whereHas('employee')` fails outright — MySQL looks for `employees` in the app schema. Resolve ids on one connection first, then `whereIn` on the other. `DataScopeService` shows the pattern.
2. **No foreign keys across the boundary.** `non_employees.location_id → locations`, `users.employee_id → employees.employee_id` are soft links; the related model declares its own `$connection`.
3. **Migrations run on the default connection only.** Changes to `kpncorp` tables are shipped as hand-written `.sql` files at the repo root (`hcispanel_kpn_coi-*.sql`) and applied out of band.

## Declaration identity: `(user_id, type)`, never `user_id` alone

`coi_declarations.type` decides which database `user_id` points at:

- `type = 'employee'` → `kpncorp.users.id` (`App\Models\User`)
- `type = 'non_employee'` → local `users.id` (`App\Models\NonEmployeeUser`, same `users` table name, different connection)

Both tables auto-increment from 1, so the id spaces overlap: matching on `user_id` alone returns a real but *wrong* person. Use `CoiDeclaration::declarant()` / `declarantProfile()` (they branch on `type`) rather than `->user` or `->nonEmployeeUser` directly.

A non-employee who is later hired keeps **two identities** — the new SSO employee account and the old local account. Nothing is migrated; the link is `users.employee_id` on the local row, written by `CredentialController::convertToEmployee`. `DeclarationScopeService` resolves all identities an account may read; use `applyOwnership()` / `owns()` for anything employee-facing rather than hand-rolling the check.

## Auth

Two session guards (`config/auth.php`): **`web`** (HRIS employees) and **`non_employee`** (local accounts created by admins). Employee-facing routes use `auth:web,non_employee`; admin/manager routes are `web` only. Controllers serving both resolve the user as `Auth::guard('web')->user() ?? Auth::guard('non_employee')->user()`.

Entry points:
- **SSO** — `GET /dbauth` (`SsoController`), XOR+base64 payload validated against Darwinbox `checkToken`.
- **Non-employee login** — Breeze session login; credentials mailed on creation/import (`NonEmployeeCredentialMail`).
- **Magic link reset** — `POST /api/reset-password` (bot webhook) issues a `signed` URL to `password.magic`.

Authorization is spatie/laravel-permission with a custom `App\Models\Role` (adds a JSON `restrictions` column). Routes use `permission:*` middleware; `EnsureManager` (alias `manager`) checks whether anyone reports to the user via `employees.manager_l1_id/manager_l2_id`. Permission names live in `PermissionSeeder`; the Vue side reads them through `usePermission().can()`.

## Data restrictions (role-scoped visibility)

A role may pin `work_area_code`, `group_company`, and `contribution_level_code`. `DataScopeService` is the single implementation — `applyToPeople()` for `Employee`/`NonEmployee` queries, `applyToDeclarations()` for `coi_declarations`. Semantics that are easy to get wrong and are already handled there: an empty dimension means unrestricted; holding *any* role that leaves a dimension open widens it entirely; rows with a null value on a restricted dimension are hidden; legacy roles store business-unit codes while people tables store names, so both are matched. Every admin-facing service (`ReportService`, `DashboardService`) injects it — new admin queries must too, including inside queued jobs, where the requester is resolved from the row rather than the session (`GenerateReportDownload`).

## Backend layout

Thin controllers, business logic in `app/Services`:

| Service | Role |
| --- | --- |
| `CoiDeclarationService` | draft/submit; submit creates a new row and deletes the draft |
| `DeclarationScopeService` | which declarations an account owns |
| `DataScopeService` | role-based visibility restrictions |
| `ReportService` / `DashboardService` / `ManagerTeamHistoryService` | admin & manager read models |
| `CredentialImportService` | non-employee bulk XLSX import |

Validation lives in `app/Http/Requests` (`SaveDraftRequest`, `SubmitDeclarationRequest`, shared rules in `Requests/Concerns`). Excel via maatwebsite/excel (`app/Exports`, multi-sheet under `Exports/Sheets`); PDFs via dompdf against `resources/views/pdf/*`.

Report exports are **async**: `POST /admin/report/export` creates a `ReportDownload` and dispatches `GenerateReportDownload`; the UI polls `report.export.status` then hits `report.export.download`. Requires a running queue worker.

## Questions are config, not code

The declaration questionnaire is defined **once** in `config/coi.php` (`questions`, with `en`/`id` text and appendix numbering) and shared to every page as the `coiQuestions` Inertia prop by `HandleInertiaRequests`. Read it from `usePage().props.coiQuestions`. `resources/js/Config/coiQuestions.ts` is an older, shorter copy still used for its TypeScript types — do not treat it as the source of truth for question content.

`HandleInertiaRequests::share()` is also where `auth.user`, `role`, `permissions`, `navigation.is_manager`, and `flash` come from — check there before adding a prop to individual controllers.

## Frontend

`resources/js`: `Pages/` (Inertia screens, `@/` → `resources/js/`), `Layouts/` (Admin/App/Auth/Employee/Manager), `Components/` (`UI/` primitives, `Declaration/`, `Admin/`, `Layout/`), `Composables/`, `Config/`. Composition API with `ref`/`computed` and Inertia page props only — no Pinia/Vuex.

**i18n (EN/ID)** — dictionaries in `Config/locales/{en,id}.ts`, with `index.ts` typing `id` against `en`'s shape so they stay in sync. `useLocale()` exposes a module-level shared `ref` persisted to `localStorage['app-locale']`; `t` is the active dictionary, `locale` the `'en'|'id'` string used to index per-question `{ en, id }` label objects. One global toggle (Topbar + Login) drives everything, including the language stamped on a submitted declaration and its PDF. `Pages/Profile/*` and `Welcome.vue` are unrouted Breeze leftovers and are not localized.

## Legacy 2025 period

Period 2025 is a historical import, not an interactive form: one Yes/No stored under response key `2025_has_conflict` (`CoiDeclaration::LEGACY_PERIOD` / `LEGACY_CONFLICT_KEY`), plus an optional supporting document whose path is kept *inside* that response value — no dedicated column. Files go to the `local` disk (`storage/app/private`) via `DeclarationAttachmentController`. Report and history screens branch on this period (`isLegacyReport`).

## Conventions

- PHP: PascalCase classes, camelCase methods/vars, snake_case plural tables; new files use `declare(strict_types=1);`. Format with Pint.
- Vue: PascalCase SFC filenames, camelCase state/functions.
- Comments in this codebase explain *why* a non-obvious constraint exists (usually the cross-database or dual-identity ones). Keep that style — those comments are load-bearing.
- Trunk-based on `main`, remote `github.com/KPN-CORP/kpn-coi`; no CI configured. Deployment is cPanel git-push (`.cpanel.yml`), which copies `app bootstrap config database routes resources` plus `public/build` and runs `optimize:clear` — **it does not run `composer install`, `npm run build`, or migrations**. `public/build` is therefore committed: run `npm run build` and commit the output with any frontend change, and apply migrations manually.
