# Changelog

## Unreleased

- Initial Teams Selector application: Laravel 13, Breeze (Inertia + Vue), multi-tenant organizations, members, weighted rules, draft generation with penalty reporting, approve snapshots with branding, CSV/XLSX export, print view, org users and branding (logo + colors).
- Toolchain: **Vite 8**, **Tailwind CSS v4** (`@tailwindcss/vite`), **Vue 3.5**, **laravel-vite-plugin 3**, current `@inertiajs/vue3` / **axios** / **ziggy-js**. **`package.json` `engines.node`: `>=22.12.0`** (aligns with Vite 8); **`.nvmrc`** set to **22** (use Node 22 LTS or newer, e.g. 23.x, on dev/CI — run `npm ci && npm run build` with that Node).
