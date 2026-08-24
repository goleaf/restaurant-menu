---
paths:
  - 'lang/*.json'
---

# Lang

## Keep EN LT RU catalogues exact and fully used
All three flat JSON catalogues must contain the same semantic dot keys with non-empty locale-specific values and placeholder/plural parity. Do not keep unused keys or use English as a missing LT/RU fallback. Run translations:scan and translations:audit after catalogue or call-site changes.
