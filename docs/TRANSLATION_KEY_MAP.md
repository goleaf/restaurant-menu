# Translation Key Map

This map is the canonical namespace list for interface translation keys. Do not invent random top-level namespaces. Pick the namespace that matches the UI meaning, then add a specific semantic suffix.

All keys must follow the project translation standard:

- Use semantic English technical keys.
- Use flat dotted JSON keys.
- Do not use real phrases as keys.
- Do not use Russian or Lithuanian keys.
- Do not use vague keys like `title`, `name`, or `label` without a namespace.
- One meaning gets one key.
- Do not duplicate the same meaning under different keys.

## Namespace Index

<!-- translation-key-namespaces:start -->
- `ui.*` - shared interface elements.
- `ui.confirmations.*` - confirmation copy for dangerous or irreversible actions.
- `auth.*` - authentication UI.
- `navigation.*` - admin and app navigation labels.
- `organizations.*` - organization/company UI.
- `brands.*` - brand UI.
- `branches.*` - branch/location UI.
- `areas.*` - zones, halls, floors, and area hierarchy UI.
- `service_points.*` - tables, seats, service points, and service-point UI.
- `qr.*` - QR code UI and QR-specific errors.
- `guest.*` - public guest interface.
- `menu.*` - menus, categories, dishes, modifiers, and menu-management UI.
- `waiter.*` - waiter interface.
- `departments.*` - kitchen, bar, and department UI.
- `orders.*` - orders, draft orders, order statuses, and order workflows.
- `payments.*` - payment, bill, split, tip, and service-charge UI.
- `reports.*` - reports and analytics UI.
- `staff.*` - staff management UI.
- `permissions.*` - permission labels, groups, and descriptions.
- `statuses.*` - human-readable statuses.
- `validation.*` - labels and validation messages when routed through JSON keys.
- `errors.*` - general controlled errors.
- `notifications.*` - notification UI and notification messages.
- `activity.*` - audit log and activity history UI.
- `superadmin.*` - superadmin platform UI.
<!-- translation-key-namespaces:end -->

## Namespace Details

### `ui.*`

Use for shared interface primitives that mean the same thing across the app.

Examples:

- `ui.actions.save`
- `ui.actions.cancel`
- `ui.actions.delete`
- `ui.actions.open`
- `ui.actions.close`
- `ui.actions.confirm`
- `ui.actions.back`
- `ui.actions.search`
- `ui.actions.filter`
- `ui.actions.export`
- `ui.actions.print`

### `ui.confirmations.*`

Use for shared confirmation copy, especially destructive or irreversible actions.

Examples:

- `ui.confirmations.danger.title`
- `ui.confirmations.danger.description`
- `ui.confirmations.delete.title`
- `ui.confirmations.delete.description`

### `auth.*`

Use for authentication and account access screens.

Examples:

- `auth.login.title`
- `auth.login.email`
- `auth.login.password`
- `auth.logout`

### `navigation.*`

Use for admin menu and app navigation labels.

Examples:

- `navigation.dashboard`
- `navigation.orders`
- `navigation.menu`
- `navigation.staff`
- `navigation.reports`

### `organizations.*`

Use for companies and organization-level settings, lists, forms, and messages.

Examples:

- `organizations.pages.index.title`
- `organizations.forms.name.label`
- `organizations.actions.create`
- `organizations.messages.created`

### `brands.*`

Use for brand-level settings, lists, forms, and messages.

Examples:

- `brands.pages.index.title`
- `brands.forms.name.label`
- `brands.actions.create`
- `brands.messages.updated`

### `branches.*`

Use for branch/location settings, forms, lists, and messages.

Examples:

- `branches.pages.settings.title`
- `branches.forms.default_language.label`
- `branches.forms.default_currency.label`
- `branches.messages.settings_saved`

### `areas.*`

Use for zones, halls, floors, and nested service-area hierarchy.

Examples:

- `areas.labels.zone`
- `areas.labels.unassigned`
- `areas.pages.index.title`
- `areas.actions.create`

### `service_points.*`

Use for tables, places, seats, service points, and their availability.

Examples:

- `service_points.labels.table`
- `service_points.labels.place`
- `service_points.status.free`
- `service_points.status.occupied`

### `qr.*`

Use for QR code management, QR display, QR lookup, and QR-specific errors.

Examples:

- `qr.errors.not_found.title`
- `qr.errors.not_found.description`
- `qr.errors.disabled.title`
- `qr.actions.print`

### `guest.*`

Use for public guest pages, guest forms, table access, guest cart, and guest-facing copy.

Examples:

- `guest.forms.name.label`
- `guest.forms.name.placeholder`
- `guest.table.closed.title`
- `guest.invites.expired.title`

### `menu.*`

Use for menu-management UI, categories, dishes, modifiers, schedules, and menu availability. Restaurant-owned menu content translations stay in the database and are not stored as UI JSON strings.

Examples:

- `menu.pages.index.title`
- `menu.categories.empty`
- `menu.items.status.available`
- `menu.modifiers.required`

### `waiter.*`

Use for waiter dashboard, waiter table detail, waiter draft editing, and waiter handoff workflows.

Examples:

- `waiter.dashboard.title`
- `waiter.tables.detail.title`
- `waiter.actions.confirm_order`
- `waiter.messages.item_served`

### `departments.*`

Use for kitchen, bar, production departments, and department-ticket screens.

Examples:

- `departments.kitchen.title`
- `departments.bar.title`
- `departments.items.ready`
- `departments.actions.mark_ready`

### `orders.*`

Use for orders, draft orders, order review, cancellation, repeat orders, and order workflow messages.

Examples:

- `orders.draft.title`
- `orders.actions.cancel`
- `orders.messages.cancelled`
- `orders.status.pending`

### `payments.*`

Use for manual payments, bills, split payments, tips, service charges, and payment summaries.

Examples:

- `payments.summary.title`
- `payments.forms.method.label`
- `payments.actions.mark_paid`
- `payments.errors.amount_exceeds_remaining`

### `reports.*`

Use for reports, analytics, exports, and report filters.

Examples:

- `reports.dashboard.title`
- `reports.filters.date_from`
- `reports.filters.date_to`
- `reports.actions.export`

### `staff.*`

Use for staff management, invitations, staff forms, and staff assignment UI.

Examples:

- `staff.pages.index.title`
- `staff.forms.email.label`
- `staff.actions.invite`
- `staff.messages.invitation_sent`

### `permissions.*`

Use for permission labels, permission group names, descriptions, and permission override UI.

Examples:

- `permissions.labels.manage_staff`
- `permissions.descriptions.manage_staff`
- `permissions.groups.staff`
- `permissions.messages.override_saved`

### `statuses.*`

Use for shared human-readable statuses when the status appears across multiple domains. Keep domain-specific statuses in the domain namespace when the meaning is not shared.

Examples:

- `statuses.active`
- `statuses.inactive`
- `statuses.pending`
- `statuses.closed`

### `validation.*`

Use only when labels or validation messages are intentionally routed through JSON keys. Do not create PHP validation language files for interface UI.

Examples:

- `validation.attributes.guest_name`
- `validation.attributes.service_point_name`
- `validation.messages.required`
- `validation.messages.max_string`

### `errors.*`

Use for controlled application error pages and general error messages.

Examples:

- `errors.actions.home`
- `errors.actions.dashboard`
- `errors.types.system_error.title`
- `errors.types.validation_error.message`

### `notifications.*`

Use for notification titles, notification body copy, notification action labels, and notification lists.

Examples:

- `notifications.orders.created.title`
- `notifications.orders.created.message`
- `notifications.actions.mark_read`
- `notifications.empty`

### `activity.*`

Use for audit logs, activity history, timeline labels, and human-readable activity events.

Examples:

- `activity.pages.index.title`
- `activity.labels.actor`
- `activity.labels.action`
- `activity.empty`

### `superadmin.*`

Use for superadmin platform UI, platform dashboard, backups, safety checks, and platform-level controls.

Examples:

- `superadmin.dashboard.title`
- `superadmin.backups.title`
- `superadmin.actions.download_backup`
- `superadmin.safety.production`
