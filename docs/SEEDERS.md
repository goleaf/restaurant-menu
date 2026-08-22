# Seeder reference

The canonical seeding architecture, classes, safeguards, coverage and verification commands are in [`seeding.md`](seeding.md). Demo-only credentials are in [`DEMO_LOGIN.md`](DEMO_LOGIN.md).

`DatabaseSeeder` is the orchestrator; fixed role/permission/reference seeders are idempotent; `DemoRestaurantSeeder` refuses production and never truncates production data.
