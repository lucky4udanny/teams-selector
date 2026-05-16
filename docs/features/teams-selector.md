# Teams selector

- **Auth entry:** Visit `/` or `/login` for the sign-in form (Breeze + Inertia); after login, users land on organizations via the `dashboard` route.
- **Migration order:** Domain tables use one second per migration: `155540` organizations, `155541` selection_drafts, `155542` approved_selections, `155543` members, `155544` organization_user, `155545` rules—so FK order is explicit without relying on alphabetical filename order.
- Organizations are identified by `slug` in URLs (`/organizations/{slug}/...`).
- Approved selections store an immutable `snapshot` including `branding` (logo URL and colors) for consistent print/export.
- Team generation uses `TeamSolverService` with weighted penalties; blocking roster issues (e.g. member count vs team size) surface as `blocking_errors` on the draft state.
- Frontend toolchain: **Vite 8**, **Tailwind CSS v4** (`@tailwindcss/vite`), **Vue 3.5+**; Node **≥ 22.12** per `package.json` / `.nvmrc`.
- **Forge:** If `view:clear` fails with `View path not found.`, ensure `storage/framework/views` exists before optimize/clear commands, avoid empty `VIEW_COMPILED_PATH`, and use published `config/view.php` (fallback path without `realpath()`).
- Policies use `$this->authorize()` from **`Illuminate\Foundation\Auth\Access\AuthorizesRequests`** on the base `Controller`; ensure that trait is present (default Laravel skeleton includes it; minimal scaffolds may not).
