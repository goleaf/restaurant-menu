# Known limitations

Only external/environmental evidence limits remain; no unfinished in-scope implementation is hidden here.

| Affected requirement | Evidence gap | User impact | Unblock condition |
|---|---|---|---|
| `ui-accessibility-001` | The available isolated Chrome tooling provides accessibility-tree, keyboard, Lighthouse, reduced-motion/forced-color CSS and focus evidence, but not a physical screen reader, switch device or human assistive-technology session. | Automated and keyboard acceptance is verified; physical AT ergonomics are not independently certified. | Run the documented critical workflows with VoiceOver/NVDA and representative switch/zoom users on supported hardware. |
| `ui-responsive-001` | Responsive checks used Chrome emulation and desktop Chromium, not physical iOS/Android hardware or non-Chromium browser engines. | Layout/overflow/touch emulation is verified; platform-specific browser chrome, virtual keyboard and native touch behavior may still vary. | Execute `TEST_CHECKLIST.md` on supported physical iOS/Android devices and current Safari/Firefox releases. |

There are no unresolved dependency advisories, migration/seed failures, static-analysis findings, test failures, console errors or external service blockers as of the 2026-08-22 final gates.
