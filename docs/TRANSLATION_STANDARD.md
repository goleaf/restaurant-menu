# Translation Standard

This project uses one interface translation standard: flat JSON files with semantic English technical keys.

Interface translations live only in:

- `lang/en.json`
- `lang/lt.json`
- `lang/ru.json`

Menu content translations are not interface translations. Restaurant menu names, category names, dish descriptions, modifier names, and other customer-editable menu content stay in the database translation tables.

## Key Format

Translation keys must be semantic English technical keys:

- Use lowercase ASCII words.
- Separate namespace parts with dots.
- Use underscores inside one namespace part when needed.
- Name the UI domain first, then the element, then the role or state.
- Keep the same keys in `lang/en.json`, `lang/lt.json`, and `lang/ru.json`.
- Keep JSON flat. Do not use nested objects.

Good:

```json
{
  "qr.errors.not_found.title": "QR code not found",
  "qr.errors.not_found.description": "Please ask the staff for a fresh QR code.",
  "areas.labels.zone": "Zone",
  "guest.forms.name": "Your name",
  "ui.actions.save": "Save"
}
```

Language values must be translated per file:

```json
{
  "ui.actions.save": "Save"
}
```

```json
{
  "ui.actions.save": "Išsaugoti"
}
```

```json
{
  "ui.actions.save": "Сохранить"
}
```

## Naming Convention

Use stable namespaces that describe where the text belongs. The canonical namespace list lives in `docs/TRANSLATION_KEY_MAP.md`; use that map before creating any new translation key.

Use suffixes consistently:

- `.title` for page, modal, card, or error titles.
- `.description` for supporting text.
- `.label` for field, row, or compact UI labels.
- `.placeholder` for input placeholders.
- `.hint` for short helper text.
- `.message` for user-facing feedback.

Examples:

```json
{
  "guest.forms.name.label": "Your name",
  "guest.forms.name.placeholder": "Enter your name",
  "qr.errors.not_found.title": "QR code not found",
  "qr.errors.not_found.description": "Please ask the staff for a fresh QR code.",
  "areas.labels.zone": "Zone",
  "orders.status.pending": "Pending",
  "payments.actions.mark_paid": "Mark paid"
}
```

## Forbidden Old Style

Do not use phrase-as-key translations:

```json
{
  "QR code not found": "QR kodas nerastas",
  "Please ask the staff for a fresh QR code.": "Paprašykite personalo naujo QR kodo.",
  "Зона": "Zona",
  "Ваше имя": "Jūsų vardas"
}
```

Forbidden:

- English sentences as keys.
- Russian text as keys.
- Lithuanian text as keys.
- Any other human phrase as a key.
- Nested JSON objects.
- PHP translation files for interface UI, such as `lang/en/ui.php`.
- Mixing PHP array translation files with JSON for interface UI.
- Hardcoded user-facing UI text in Blade, Livewire components, controllers, services, or actions.

All user-facing interface text must be called with semantic keys:

```php
__('qr.errors.not_found.title')
__('guest.forms.name.label')
__('ui.actions.save')
```

## Storage Boundary

Interface UI translations:

- Stored in `lang/en.json`, `lang/lt.json`, and `lang/ru.json`.
- Called with `__('semantic.dot.key')`.
- Must use identical key sets across all three files.

Menu content translations:

- Stored in database translation tables.
- Managed as restaurant-owned content.
- Not duplicated into JSON language files.

## Legacy Migration Rule

Prompt 381 is a foundation step. It does not migrate the whole project.

Legacy phrase keys are no longer allowed. Any interface copy must use a semantic dotted key.

## Legacy Phrase Keys Snapshot

The legacy phrase-key allowance is empty. Keep this block empty unless an intentional temporary migration exception is approved.

<!-- legacy-translation-keys:start -->
<!-- legacy-translation-keys:end -->
