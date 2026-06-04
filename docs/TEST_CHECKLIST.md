# Smoke Test Checklist

This checklist verifies the main restaurant flow without adding a heavy browser
test framework. Use it after migrations on a local or shared-hosting-like
SQLite setup.

## Rules

- Use SQLite only.
- Use database cache, database sessions, and database queue.
- Do not enable Redis, WebSockets, S3, Docker, Push, SMS, Telegram API, Stripe,
  PayPal, or paid services.
- Do not commit `.env`, `database/database.sqlite`, local uploads, backups,
  `vendor`, or `node_modules`.

## Daily Memory Update - 2026-06-04

Project memory was refreshed in `README.md`, `CHANGELOG.md`,
`docs/AI_CONTEXT.md`, and `docs/NEXT_STEPS.md`.

After Prompt 101, include the public restaurant profile in setup smoke checks:
branch settings should save public name, description, local logo/cover, contact
links, default language, and default currency, and the QR landing should show
those values or polished fallback text.

After Prompt 102, include branch opening hours in setup and guest smoke checks:
branch settings should save closed days and several intervals per day, the QR
landing should show whether the restaurant is open or closed, and guest ordering
should be blocked while a configured branch is closed.

Use these focused checks after documentation-only maintenance:

```bash
php artisan migrate --no-interaction
php artisan route:list
php artisan test --compact tests/Feature/ProjectCleanupConsistencyTest.php tests/Feature/VerticalSliceFlowTest.php
```

Use the full checks before larger code changes or before handing off a release:

```bash
php artisan test --compact
npm run build
```

The next recommended prompt is documented in `docs/NEXT_STEPS.md`. Do not
implement it until the user explicitly requests it.

## Prompt 102 Branch Opening Hours Results

Programmatic coverage was added in `tests/Feature/BranchOpeningHoursTest.php`.
The feature currently verifies:

- `branch_opening_hours` stores weekly branch schedules;
- one day can have several opening intervals;
- a day can be marked closed;
- branch settings can save the schedule;
- status checks respect the branch timezone;
- the public QR page still opens when the branch is closed;
- guests can view the table/menu while closed;
- guest draft item creation and send-to-waiter are blocked while a configured
  branch schedule is closed.

Focused command:

```bash
php artisan test --compact tests/Feature/BranchOpeningHoursTest.php
```

Manual check:

1. Open branch settings.
2. Enable opening hours.
3. Configure Monday with two intervals, for example `10:00-14:00` and
   `18:00-22:00`.
4. Mark one weekday as closed.
5. Save settings.
6. Open a QR URL for the branch.
7. Confirm the QR landing/table UI shows `Сейчас открыто` during an open
   interval.
8. Confirm it shows `Сейчас закрыто` and `Откроется в ...` outside an open
   interval.
9. Confirm the menu remains visible while closed.
10. Confirm adding a dish and sending a draft to the waiter are blocked while
    closed.

## Prompt 101 Restaurant Public Profile Results

Programmatic coverage was added in `tests/Feature/RestaurantPublicProfileTest.php`.
The feature currently verifies:

- `branches` stores public profile fields for venue name, description, cover
  image, phone, email, website, Instagram, Facebook, and TikTok;
- a branch manager can update the profile from the existing branch settings
  page;
- logo and cover uploads are stored locally on the `public` disk;
- default language and default currency remain local branch/settings data;
- public `/q/{public_token}` landing shows the branch public profile;
- missing profile details show tidy fallback text;
- the QR URL stays token-only.

Focused command:

```bash
php artisan test --compact tests/Feature/RestaurantPublicProfileTest.php
```

Manual check:

1. Open branch settings.
2. Fill public venue name, short description, phone, email, website, and social
   links.
3. Upload a logo and cover image.
4. Save settings.
5. Open a service point QR URL `/q/{public_token}`.
6. Confirm the landing page shows the public profile, local images, default
   language, default currency, current zone, and current service point.
7. Clear optional profile/contact fields and confirm the guest fallback text is
   still polished.

## Prompt 096 Access Control Results

Programmatic audit coverage was added in `tests/Feature/AccessControlAuditTest.php`.
The audit currently verifies:

- ordinary users see only their own organizations;
- branch-assigned employees cannot see or open another branch without access;
- waiter-style users without `change_prices` cannot change menu item prices;
- cooks cannot open staff management;
- marketers cannot resolve order-confirmation access;
- accountants can view payment branches but cannot manage payments or edit menu;
- superadmins bypass organization and branch restrictions.

Focused command:

```bash
php artisan test --compact tests/Feature/AccessControlAuditTest.php
```

## Prompt 098 Cleanup Results

Programmatic cleanup coverage was added in `tests/Feature/ProjectCleanupConsistencyTest.php`.
The cleanup currently verifies:

- resolved infrastructure stays shared-hosting friendly: SQLite, local public storage, no Redis, no S3 disk, no Pusher/Reverb WebSocket driver;
- Composer and Node dependencies do not include Docker/Sail, Redis, Pusher, WebSocket, or S3 client packages;
- default `php artisan db:seed` does not create the starter `test@example.com` user;
- public entry pages do not show starter placeholder or "not implemented yet" copy.
- routes, migrations, model naming, seeders, and current policy usage were reviewed without adding new business routes or tables.

Focused command:

```bash
php artisan test --compact tests/Feature/ProjectCleanupConsistencyTest.php
```

## Prompt 099 Vertical Slice Results

Programmatic first-slice coverage was added in `tests/Feature/VerticalSliceFlowTest.php`.
The regression currently verifies:

- real user registration through Fortify;
- organization, brand, branch, zone, service point, and permanent QR setup;
- public QR URL shaped as `/q/{public_token}` without technical IDs;
- guest name entry and guest-created pending table session;
- second guest invite link, waiting state, and first-guest approval;
- guest menu item selection and shared draft totals by guest;
- sending the shared draft to the waiter;
- waiter confirmation and explicit kitchen/bar dispatch;
- kitchen and bar ready states;
- waiter served handoff;
- guest bill request;
- manual table payment;
- table-session close;
- service point returns to `free`;
- permanent QR token and active status stay unchanged after close.

Focused command:

```bash
php artisan test --compact tests/Feature/VerticalSliceFlowTest.php
```

Affected-flow command used for Prompt 099:

```bash
php artisan test --compact tests/Feature/VerticalSliceFlowTest.php tests/Feature/OnboardingRestaurantWizardTest.php tests/Feature/GuestCreatedPendingSessionTest.php tests/Feature/GuestInviteShareLinkTest.php tests/Feature/GuestJoinApprovalUiTest.php tests/Feature/GuestMenuDisplayTest.php tests/Feature/WaiterDraftReviewTest.php tests/Feature/KitchenTicketDispatchTest.php tests/Feature/KitchenScreenTest.php tests/Feature/BarDepartmentScreenTest.php tests/Feature/ReadyItemsToWaiterTest.php tests/Feature/BillRequestTest.php tests/Feature/ManualPaymentTest.php tests/Feature/TableSessionCloseTest.php
```

## Prepare

1. Run migrations:

```bash
php artisan migrate
```

2. Seed system data and demo restaurant:

```bash
php artisan db:seed
php artisan db:seed --class=DemoRestaurantSeeder
```

The default seeder creates system data only. Demo users are created only by
`DemoRestaurantSeeder`.

3. Confirm the expected drivers:

```bash
php artisan config:show database.default
php artisan config:show cache.default
php artisan config:show session.driver
php artisan config:show queue.default
```

Expected values:

- `database.default`: `sqlite`
- `cache.default`: `database`
- `session.driver`: `database`
- `queue.default`: `database`

4. Demo staff accounts:

```text
demo.owner@example.com
demo.admin@example.com
demo.waiter@example.com
demo.chef@example.com
demo.bartender@example.com
demo.cashier@example.com
```

Default password:

```text
password
```

## Setup Flow

Use `demo.admin@example.com` or `demo.owner@example.com`.

1. Log in.
2. Open `Organizations`.
3. Create or open an organization.
   - Demo seed already creates `Demo Food Group`.
4. Create or open a brand.
   - Demo seed already creates `Bella Pizza`.
5. Create a branch.
   - Example name: `Smoke Test Branch`.
   - Use `EUR`, `Europe/Vilnius`, active state.
6. Open branch settings.
7. Fill or confirm the public restaurant profile:
   - public venue name;
   - short description;
   - logo;
   - cover image;
   - phone/email/site/social links if available;
   - default language;
   - default currency.
8. Confirm safe defaults:
   - waiter confirmation required for orders;
   - guest-created sessions allowed;
   - waiter-opened sessions allowed;
   - guest invite links allowed;
   - new guests require approval;
   - polling interval is 1 second.
9. Configure branch opening hours or leave them disabled intentionally.
   - If enabled, include at least one open day with two intervals and one closed
     day.
10. Open branch zones.
11. Create a zone.
   - Example name: `Smoke Main Hall`.
12. Open branch service points.
13. Create a service point.
    - Type: table.
    - Example name: `Smoke Table 1`.
    - Capacity: 4.
    - Assign it to `Smoke Main Hall`.
14. Generate QR for the service point.
15. Confirm the QR page shows:
    - branch;
    - current zone;
    - current service point;
    - public URL;
    - QR image;
    - short code.
16. Open the public QR URL.
17. Confirm the URL is `/q/{public_token}` and does not expose organization,
    branch, service point, table ID, table number, or area ID.
18. Confirm the QR landing shows the public restaurant profile or tidy fallback
    text when optional profile details are empty.
19. Confirm the QR landing shows current opening-hours status when a schedule
    is configured.

## Menu Flow

Use `demo.admin@example.com` or `demo.owner@example.com`.

1. Open the branch menu page.
2. Create or confirm an active menu.
3. Create at least one category.
   - Example: `Pizza`.
4. Create at least two dishes.
   - Example: `Margherita`, `Pepperoni`.
5. Assign dishes to departments.
   - Food to kitchen.
   - Drink to bar if testing bar flow.
6. Confirm prices are visible in the branch currency.
7. Confirm unavailable dishes cannot be added by guests.

## Guest Flow

Use two different browser sessions. For example:

- normal browser window for Guest 1;
- private/incognito browser window for Guest 2.

1. Open the QR URL from the setup flow or from the demo seed.
2. Guest 1 enters a name.
   - Example: `Anna`.
3. Submit the guest entry form.
4. Confirm a table session is created.
5. Confirm Guest 1 sees the active table page.
6. Confirm the page shows:
   - venue name;
   - current zone;
   - current service point;
   - current opening-hours status;
   - guest list;
   - menu;
   - shared draft/cart area;
   - table total.
7. Guest 1 clicks `Invite guest`.
8. Copy or share the invite link.
9. Open the invite link in the second browser session.
10. Guest 2 enters a name.
    - Example: `Boris`.
11. Confirm Guest 2 sees a waiting screen.
12. Return to Guest 1.
13. Confirm Guest 1 sees the join request through polling.
14. Guest 1 approves Guest 2.
15. Confirm Guest 2 becomes active without creating a user account.
16. Confirm guests are listed alphabetically.
17. Guest 1 adds a dish.
18. Guest 2 adds a different dish.
19. Confirm both guests see the shared draft.
20. Confirm each guest sees:
    - own positions;
    - the other guest's positions;
    - own total;
    - other guest total;
    - full table total.
21. Confirm Guest 1 cannot edit Guest 2's positions.
22. Mark guests ready if needed.
23. Send the shared draft to the waiter.
24. Confirm guest editing is blocked after sending.
25. Confirm the service point status becomes `has_new_order` or an equivalent
    visible new-order state.

## Waiter Flow

Use `demo.waiter@example.com`.

1. Log in as waiter.
2. Open the waiter dashboard.
3. Confirm the branch is visible.
4. Confirm service points are grouped by zone.
5. Confirm the new draft/order appears through polling.
6. Open the table detail page.
7. Confirm the waiter sees:
   - branch;
   - zone;
   - service point;
   - session status;
   - guests alphabetically;
   - positions grouped by guest;
   - modifiers;
   - comments;
   - guest totals;
   - table total;
   - draft status.
8. Confirm the draft.
9. Confirm a real order is created.
10. Confirm the order status is `confirmed_by_waiter`.
11. Send the order to kitchen/bar.
12. Confirm the order status is `sent_to_kitchen_bar`.
13. Confirm service point status moves to a cooking/in-progress state.

## Kitchen Or Bar Flow

Use the right staff account:

- kitchen: `demo.chef@example.com`;
- bar: `demo.bartender@example.com`.

1. Log in as kitchen or bar staff.
2. Open the kitchen or bar dashboard.
3. Confirm only relevant department tickets are visible.
4. Confirm each ticket shows:
   - service point;
   - zone;
   - item names;
   - modifiers;
   - comments;
   - timer;
   - status.
5. Start an item.
6. Mark the item ready.
7. Confirm the waiter dashboard/table detail sees the ready item through
   polling.

## Served Flow

Use `demo.waiter@example.com`.

1. Open the waiter table detail.
2. Confirm ready items are visible.
3. Mark ready items as served.
4. Confirm guests see an accepted/cooking/ready/served style status update.
5. Confirm service point status updates away from ready-to-serve when all ready
   items are served.

## Bill And Close Flow

Use the guest page and then waiter/cashier.

1. Guest clicks `Request bill`.
2. Confirm guests still see:
   - table total;
   - each guest total.
3. Confirm waiter dashboard shows a bill request through polling.
4. Log in as `demo.cashier@example.com` or a waiter with payment rights.
5. Record manual payment.
   - Method: cash, card terminal, or other.
6. Confirm the table session moves to `paid` after full payment.
7. Close the table session.
8. Confirm:
   - session status is `closed`;
   - service point status is `free`;
   - old guest page cannot add more positions;
   - QR public URL still opens the same permanent QR entry point;
   - a new seating creates a new session for the same service point.

## Pass Criteria

- No technical IDs are exposed in guest QR URLs.
- One service point has only one active permanent QR.
- QR does not change after service point rename or area move.
- Guest tokens do not require user accounts.
- New guests require approval when active guests already exist.
- Shared draft is visible to all active guests.
- Guests can edit only their own draft items.
- Draft does not reach kitchen/bar until waiter confirmation and dispatch.
- Kitchen/bar sees only dispatched department tickets.
- Ready items reach waiter through polling.
- Payment and close are manual/offline only.
- Realtime behavior is Livewire polling only.
- No Redis, WebSockets, S3, Docker, or paid services are required.

## Quick Regression Commands

These are optional command-line checks and do not replace the manual flow:

```bash
php artisan test --compact tests/Feature/AccessControlAuditTest.php
php artisan test --compact --filter=DemoRestaurantSeederTest
php artisan test --compact tests/Feature/VerticalSliceFlowTest.php
php artisan test --compact
```
