# Demo login

Demo accounts are created only by `DemoRestaurantSeeder`, which refuses the production environment. Current non-production addresses use the `@demo.test` domain and the shared seed-only password `DemoPassword2026!`; inspect the seeder for the exact role-address map because it is executable truth.

Run demo data only in an isolated local/demo database:

```bash
php artisan db:seed --class=DemoRestaurantSeeder --no-interaction
```

Never copy these credentials into production, remove the production guard, or use the demo seeder as an account-provisioning mechanism. The complete seeding contract is [`seeding.md`](seeding.md).
