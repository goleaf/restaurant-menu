---
paths:
  - 'database/factories/**'
---

# Factories

## Complete supplied model graphs and derived order totals
Factory states accepting persisted variants must loadMissing('item.menu.branch') before traversing/recycling the branch, preserving already loaded relations. Test retrieved collections and partially eager-loaded graphs under strict lazy-loading prevention, including zero reads for a complete graph. Optional order graph helpers must synchronize total_price_cents from active integer-cent line totals after creating items; preserve explicit compatible parents and existing item prices.
