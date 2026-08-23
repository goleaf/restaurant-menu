# Known limitations

Only external/environmental evidence limits remain; no unfinished in-scope implementation is hidden here.

| Affected requirement | Evidence gap | User impact | Tracking issue |
|---|---|---|---|
| `ui-accessibility-001` | The available isolated Chrome tooling provides accessibility-tree, keyboard, Lighthouse, reduced-motion/forced-color CSS and focus evidence, but not a physical screen reader, switch device or human assistive-technology session. | Automated and keyboard acceptance is verified; physical AT ergonomics are not independently certified. | [#8](https://github.com/goleaf/restaurant-menu/issues/8) |
| `ui-responsive-001` | Responsive checks used Chrome emulation and desktop Chromium, not physical iOS/Android hardware or non-Chromium browser engines. | Layout/overflow/touch emulation is verified; platform-specific browser chrome, virtual keyboard and native touch behavior may still vary. | [#7](https://github.com/goleaf/restaurant-menu/issues/7) |
| `test-feature-001` | GitHub Actions now provisions Xdebug and enforces `composer test:coverage` at 90%, but the current local PHP 8.5 CLI and Herd coverage proxy do not load Xdebug or PCOV. The last locally verified result was 90.4%. | Pull requests cannot pass CI below the threshold; only fresh local percentage reporting remains unavailable. | [#9](https://github.com/goleaf/restaurant-menu/issues/9) |

There are no unresolved dependency advisories, migration/seed failures, static-analysis findings, test failures, console errors or external service blockers as of the 2026-08-22 final gates. The coverage-driver row is a local evidence limitation; the threshold itself is enforced in CI.
