# Tailwind CSS 4

Tailwind 4.3.3 is integrated directly through `@tailwindcss/vite` 4.3.3. [`resources/css/app.css`](../resources/css/app.css) is CSS-first: `@import 'tailwindcss'`, explicit `@source` paths for first-party PHP/Blade, Laravel pagination and installed Flux Free stubs, `@custom-variant dark`, an OKLCH `@theme` token system and a small `@utility touch-target`. No Tailwind 3 JavaScript/PostCSS configuration, Sass/Less, Flux Pro source or unsafe runtime class construction remains.

## Design tokens

The theme defines brand scale, canvas/surface/border/text, success/warning/danger/information/focus colors, font stack, touch target, content/reading containers, extra-small breakpoint, control/card/dialog radii, elevation shadows and product easing. Critical controls have visible focus rings; status includes text/icon; reduced-motion and forced-colors rules are explicit. Repeated QR print values remain domain-specific semantic CSS because printer labels require exact colors/aspect ratios.

## Feature applicability

| Feature | Decision and location | Responsive/accessibility effect | Verification |
|---|---|---|---|
| CSS-first `@theme`, `@source`, custom dark variant | used in `app.css` | coherent sources/tokens, no purged production utilities | architecture tests and build |
| OKLCH semantic colors | used for application tokens | maintainable contrast roles; status never color-only | design tests and Lighthouse |
| Logical utilities/properties | used in navigation, dialogs and component spacing | direction-independent start/end layout | long-text/locale review |
| Reduced motion / forced colors | explicit media rules in `app.css` | motion/high-contrast preferences retained | CSS/design tests |
| Dynamic viewport units | used for mobile/print shells where needed | avoids browser-chrome clipping | responsive browser checks |
| Data/ARIA/group/peer variants | used where component state benefits | state remains semantic with minimal custom JS | markup/browser tests |
| Container queries | not applicable: current reusable panes respond correctly to viewport/grid and have no independent container-width contract | avoids needless complexity | layout review |
| Text shadows, masks, zoom, tab-size | not applicable to product workflows | avoids decorative/maintenance cost | design review |
| View transitions | not added: normal `wire:navigate` orientation and focus behavior is sufficient | avoids decorative motion | browser navigation review |

## Final responsive/build evidence

- Production build: CSS 303.36 kB / 39.72 kB gzip; application JS 4.56 kB / 1.73 kB gzip; Vite 8.2.2 completed in 658 ms on the final run.
- Public entry has no horizontal overflow at 360/390/430/768/1024/1440/1920 CSS px. Waiter checks covered 390/768/1024/1440; service points covered 390/768/1440; menu covered 390/1440 in light and dark modes.
- Operational metrics use two columns on small touch screens and six on desktop. Checked waiter and service-point controls meet the practical touch-target contract at 390 CSS px.
- Public, waiter, service-point and menu mobile Lighthouse samples scored 100 in every reported category; final console inspection found no errors or issues.

Component principles and token roles are in [`design-system.md`](design-system.md). Physical-device and non-Chromium evidence limits are recorded in [`known-limitations.md`](known-limitations.md).
