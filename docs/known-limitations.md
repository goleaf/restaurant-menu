# Known limitations

Only external/environmental evidence limits remain; no unfinished in-scope implementation is hidden here.

| Affected requirement | Evidence gap | User impact | Tracking issue |
|---|---|---|---|
| `ui-accessibility-001` | The available isolated Chrome tooling provides accessibility-tree, keyboard, Lighthouse, reduced-motion/forced-color CSS and focus evidence, but not a physical screen reader, switch device or human assistive-technology session. | Automated and keyboard acceptance is verified; physical AT ergonomics are not independently certified. | [#8](https://github.com/goleaf/restaurant-menu/issues/8) |
| `ui-responsive-001` | Responsive checks used Chrome emulation and desktop Chromium, and the complete Pest Browser suite passes in Playwright WebKit. Physical iOS/Android hardware and current Firefox remain unverified; Playwright Firefox 153 cannot start on the local macOS 27 host because of the [upstream sandbox failure](https://github.com/microsoft/playwright/issues/42082). | Layout/overflow/touch emulation and the WebKit application flows are verified; physical browser chrome, virtual keyboard, native touch behavior, actual Safari, and Firefox may still vary. | [#7](https://github.com/goleaf/restaurant-menu/issues/7) |

There are no unresolved dependency advisories, migration/seed failures, static-analysis findings or local application-test failures as of the 2026-08-23 gates. The local PHP 8.5.8 CLI now loads Xdebug 3.5.0 with normal execution disabled by default; `composer test:coverage` explicitly enabled coverage and passed at 93.3%. Physical device, actual Safari/Firefox, non-headless 200% zoom, and assistive-technology evidence remains open above.
