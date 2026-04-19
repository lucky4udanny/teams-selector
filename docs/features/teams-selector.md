# Teams selector

- Organizations are identified by `slug` in URLs (`/organizations/{slug}/...`).
- Approved selections store an immutable `snapshot` including `branding` (logo URL and colors) for consistent print/export.
- Team generation uses `TeamSolverService` with weighted penalties; blocking roster issues (e.g. member count vs team size) surface as `blocking_errors` on the draft state.
