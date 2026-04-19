# Changelog

## Unreleased

- **Forge / `view:clear`:** Published `config/view.php` and set `view.compiled` to `env('VIEW_COMPILED_PATH') ?: storage_path('framework/views')` so the path is never empty when `storage/framework/views` is missing on first deploy (default `realpath()` was `false` and triggered `View path not found.`).
- Initial Teams Selector application: Laravel 13, Breeze (Inertia + Vue), multi-tenant organizations, members, weighted rules, draft generation with penalty reporting, approve snapshots with branding, CSV/XLSX export, print view, org users and branding (logo + colors).
- Toolchain: **Vite 8**, **Tailwind CSS v4** (`@tailwindcss/vite`), **Vue 3.5**, **laravel-vite-plugin 3**, current `@inertiajs/vue3` / **axios** / **ziggy-js**. **`package.json` `engines.node`: `>=22.12.0`** (aligns with Vite 8); **`.nvmrc`** set to **22** (use Node 22 LTS or newer, e.g. 23.x, on dev/CI — run `npm ci && npm run build` with that Node).
- Fix: restore **`AuthorizesRequests`** on base `Controller` so `$this->authorize()` works (Laravel 11+ slim skeleton omitted the trait).
