# Browser smoke checklist

Use a disposable isolated Chrome profile. Record URL, viewport, actor/data fixture, console/network result, keyboard/focus result and screenshot or DOM evidence where useful.

- Authentication: invite registration/public-registration rejection/login/invalid login/logout/reset/2FA/passkey/settings.
- Tenant navigation: organization, brand, branch, staff/permissions, wrong-parent URL rejection.
- Setup: onboarding, areas, service points, settings, QR generation/print/short lookup.
- Menu: categories/items/modifiers/schedules/image upload, EN/LT/RU and long text.
- Guest: QR entry/restore/join, menu, draft edit/send, waiter call, status and bill request.
- Waiter: dashboard/table/open/review/edit/confirm/reject/transfer/merge/payment/closure.
- Kitchen/bar: scoped tickets, state transitions, print and polling.
- Governance: analytics, audit, export, subscription, superadmin and backup authorization.
- Accessibility: keyboard-only, focus visibility/order/restoration, names/errors/announcements, reduced motion, forced colors where available.
- Responsive: 360, 430, 768, 1024, 1280 and 1536 CSS pixels; no page-level horizontal overflow.
- Lifecycle: repeated `wire:navigate`, no duplicated listeners/timers, precise loading/offline/dirty states, no browser console errors.

Historical prompt-by-prompt smoke notes were consolidated into the root [`CHANGELOG.md`](../CHANGELOG.md). Current automated gates are in [`testing.md`](testing.md).

## 2026-08-22 executed browser evidence

Using disposable isolated Chrome context `restaurant-menu-modernization-20260822`: public/login/authenticated dashboard Lighthouse samples scored 100 in every reported category; 360/430 touch emulation and 500/768/1024/1280/1440/1536 representative checks showed no page-level overflow on sampled public/authenticated layouts; registration, password confirmation, EN→RU persistence, logout/login, modal focus/name, account deletion and both guest/auth client-only and authenticated `wire:offline` status regions succeeded; preserved console inspection contained no warnings or errors. Physical screen-reader/device and non-Chromium coverage remains in [`known-limitations.md`](known-limitations.md).
