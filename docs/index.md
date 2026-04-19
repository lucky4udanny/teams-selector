# Documentation index

- [Forge deployment](DEPLOY_FORGE.md) — includes deploy script ordering (`storage/framework/views` before `view:clear` / `optimize`) and `VIEW_COMPILED_PATH` pitfalls.
- Domain migrations use **unique sequential timestamps** per file so table order is explicit (see [teams-selector](features/teams-selector.md)); avoid reusing the same `Y_m_d_His` prefix for multiple files.
- Laravel 11+ slim `app/Http/Controllers/Controller.php` may omit **`AuthorizesRequests`**; without it, `$this->authorize()` in controllers throws — add the trait to the base controller.
