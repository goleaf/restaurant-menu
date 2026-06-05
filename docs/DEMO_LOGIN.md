# Demo Login

This document is for local/demo development only. It is not a production
runbook, and the demo password below must not be reused for production,
training, staging customer data, or real restaurant accounts.

`DemoRestaurantSeeder` is blocked when `APP_ENV=production`.

## Local Demo Password

All seeded demo users use this local-only password:

```text
DemoPassword2026!
```

## Demo Accounts

| Email | Role | Access |
| --- | --- | --- |
| `superadmin@demo.test` | superadmin | Platform access. |
| `owner@demo.test` | owner | Owns Demo Food Group and has demo branch access. |
| `director@demo.test` | director | Manages the demo organization and demo branches. |
| `admin@demo.test` | restaurant_admin | Restaurant administration access. |
| `manager@demo.test` | shift_manager | Shift and floor operations access. |
| `waiter@demo.test` | waiter | Waiter order and table-session access. |
| `chef@demo.test` | head_chef | Kitchen department lead access. |
| `cook@demo.test` | cook | Kitchen department access. |
| `bartender@demo.test` | bartender | Bar department access. |
| `cashier@demo.test` | cashier | Payment and cashier access. |
| `accountant@demo.test` | accountant | Reports, payments, audit, and export access. |
| `marketer@demo.test` | marketer | Menu and content-oriented access where permissions allow it. |

## Seeding

Create the local demo restaurant and users with:

```shell
php artisan db:seed --class=DemoRestaurantSeeder --env=local
```

The seeder is idempotent. Re-running it must not duplicate demo users, role
links, organization memberships, branch assignments, or QR records.
