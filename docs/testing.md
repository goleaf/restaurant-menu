# Testing and quality gates

Pest 4 is the sole primary PHP test framework. Feature tests cover framework-integrated behavior; unit tests cover pure rules/value transformations; Livewire behavior remains PHP component tests. Browser automation is reserved for DOM, focus, responsive and lifecycle behavior that lower layers cannot prove.

## Test environments

Tests use an isolated SQLite database and fake local disks/external I/O. Destructive commands are never pointed at a non-test database. Factories create valid records; seed tests may use deterministic fake data but never public internet or real credentials.

## Development loop

1. Add or update a failing regression/requirement test.
2. Run the narrow file/filter with `php artisan test --compact <path>`.
3. Implement the smallest correct change and rerun the target.
4. Run `vendor/bin/pint --dirty --format agent` after PHP edits.
5. Run Larastan on affected code and the relevant domain suite.
6. Run `npm run build` after CSS/Blade/JavaScript source changes.
7. Update requirement/plan/compliance evidence.

## Final gates

```bash
php -v
composer validate --strict
composer audit
composer lint
composer translations:audit
vendor/bin/pint --format agent
vendor/bin/phpstan analyse --memory-limit=1G
php artisan test --compact
php artisan test --parallel --compact
XDEBUG_MODE=coverage php artisan test --coverage --min=90
php artisan migrate:fresh --env=testing --force
php artisan migrate:fresh --seed --env=testing --force
npm audit
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize:clear
```

Coverage is meaningful application coverage: assertion-free filler and broad exclusions are prohibited. If the runtime has no compatible coverage driver, that command is reported as an environmental blocker with the driver check; it is not reported as passing.

Architecture tests enforce no Blade PHP/model/query/service/container/facade calls, no Volt/SFC, no `env()` outside config, no forbidden SQL/debug/dependency patterns and no unsafe dynamic Tailwind construction. Query-budget tests protect critical pages/polls where deterministic.

Browser verification uses a disposable isolated Chrome profile and records URL, viewport, workflow, console/network result, keyboard/focus result and limitation. It never touches the user's personal browser state.
