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

- Production build: CSS 291.67 kB / 37.83 kB gzip; application JS 0.00 kB; Vite 8.2.2 completed in 529 ms on the final run.
- Public page has no horizontal overflow at emulated touch widths 360 and 430 CSS px or desktop 768/1024/1280/1536 checks.
- Authenticated dashboard has no overflow at 500/768/1024/1440 CSS px; mobile and desktop landmarks/navigation remain keyboard accessible.
- Public, login and authenticated dashboard Lighthouse samples scored 100 in all reported categories; no browser console warnings/errors remained.

Component principles and token roles are in [`design-system.md`](design-system.md). Physical-device and non-Chromium evidence limits are recorded in [`known-limitations.md`](known-limitations.md).
