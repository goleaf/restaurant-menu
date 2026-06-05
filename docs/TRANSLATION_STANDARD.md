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

Existing phrase keys are marked as legacy migration debt below. Do not delete them immediately. Migrate them only when the code usage is changed to semantic keys and the old phrase key is confirmed unused.

Do not add new phrase keys. Any new interface copy must use a semantic dotted key.

## Legacy Phrase Keys Snapshot

The following keys existed before the Prompt 381 migration foundation. They are temporarily allowed only as legacy keys while the project migrates to semantic keys.

<!-- legacy-translation-keys:start -->
- `Interface language`
- `Default language`
- `Profile settings`
- `Profile`
- `Update your name and email address`
- `Name`
- `Email`
- `Save`
- `Profile updated.`
- `Branch settings`
- `Branches`
- `Settings saved.`
- `Require waiter confirmation for orders`
- `Guest join requires approval`
- `Allow guest-created sessions`
- `Allow waiter-opened sessions`
- `Allow guest invite links`
- `Service charge enabled`
- `Tips enabled`
- `Polling interval, seconds`
- `Default currency`
- `Order flow mode`
- `Enter your name to continue.`
- `QR code not found`
- `Please ask the staff for a fresh QR code.`
- `QR code is temporarily disabled`
- `Please ask the staff to help you with this place.`
- `QR code is no longer active`
- `This QR code has been replaced. Please ask the staff for the current code.`
- `This place is temporarily unavailable`
- `Please ask the staff before ordering from this place.`
- `Зона`
- `Зона не назначена`
- `Место`
- `Филиал`
- `Ваше имя`
- `Войти за стол`
- `Вход сохранён`
- `Запрос отправлен`
- `Запрос закрыт`
- `Введите имя, чтобы войти за стол.`
- `Имя должно содержать минимум 2 символа.`
- `Имя должно быть не длиннее 80 символов.`
- `Введите имя, чтобы попроситься к этому столу.`
- `Добро пожаловать, :name.`
- `Статус`
- `Заказ`
- `Черновик`
- `Помощь`
- `Позвать официанта`
- `Отправляем вызов`
- `Гости`
- `Пригласить гостя`
- `Готовим ссылку`
- `Скопировать ссылку`
- `Ссылка скопирована.`
- `Меню`
- `Выбор блюд`
- `Язык меню`
- `Меню пока недоступно`
- `Фото`
- `г`
- `л`
- `ккал`
- `Доступно`
- `Добавить`
- `Недоступно`
- `Нет в наличии`
- `Добавлено`
- `В этой категории пока нет блюд`
- `Категории меню пока не настроены`
- `Ваш выбор`
- `Закрыть`
- `Обязательно`
- `По желанию`
- `Можно выбрать`
- `Нет доступных вариантов`
- `Для этого блюда нет дополнительных настроек`
- `Комментарий`
- `Добавляем`
- `Позиция добавлена в общий заказ.`
- `Restaurant is temporarily unavailable`
- `Please ask the staff when service will resume.`
- `Open start page`
- `Try again`
- `Return to QR page`
- `QR access`
- `QR access paused`
- `Place unavailable`
- `Restaurant unavailable`
- `Guest access`
- `Show this screen to the staff so they can give you the correct QR.`
- `Show this screen to the staff. They can reopen access when the place is ready.`
- `Show this screen to the staff if you need help.`
- `Table closed`
- `Invite link`
- `Ask the staff`
- `This table session is closed`
- `Guest access was not approved`
- `Guest access was removed`
- `You have left this table`
- `Invite link has expired`
- `Please ask a waiter to open this table`
- `No active guests can approve this invite`
- `Guest access is unavailable`
- `A closed table keeps its old orders, but it cannot accept new guest actions.`
- `You cannot add items from this guest entry. Please ask the table or staff for help.`
- `Ask an active guest to share a new invite link, or scan the QR code at the table.`
- `Ordering from this place is paused until staff reopens it.`
- `A staff member can help you continue from this table.`
- `Нельзя выполнить действие для закрытого стола.`
- `Черновик сейчас нельзя изменить.`
- `Выберите активного гостя за этим столом.`
- `Гость ещё не подтверждён для этого стола.`
- `Заказ уже отменён.`
- `Позиция уже отмечена готовой.`
- `Сумма оплаты превышает остаток к оплате.`
- `QR-код отключён.`
- `У вас нет доступа к этому филиалу.`
- `Эта позиция сейчас недоступна.`
- `Выберите обязательный вариант.`
- `Черновик больше не связан с открытым столом.`
- `У вас нет права редактировать черновик заказа в этом филиале.`
- `Нельзя редактировать заказ для закрытого стола.`
- `Редактировать можно только черновик, отправленный официанту.`
- `Это блюдо сейчас недоступно для этого филиала.`
- `Выберите вариант.`
- `Выбранный вариант недоступен.`
- `Эта позиция уже подана официантом.`
- `Заказ отменён. Кухня и бар больше не работают по нему.`
- `Заказ отменён. Позиции больше нельзя подавать.`
- `У вас нет доступа к подаче позиций в этом филиале.`
- `У вас нет права отмечать оплату для этого стола.`
- `Это место сейчас недоступно. Оплату нельзя отметить.`
- `Эта сессия уже оплачена.`
- `Эта сессия уже закрыта или отменена.`
- `Сначала завершите текущий черновик заказа: подтвердите, отклоните или верните его гостям.`
- `У этого стола пока нет подтверждённых заказов для оплаты.`
- `Выберите гостя для отметки оплаты.`
- `Гость не найден в этой сессии.`
- `Для этой оплаты нет остатка к оплате.`
- `No permissions configured.`
- `Allowed formats: :formats. Max size: :size.`
- `Upload a :formats image.`
- `Image must be :size or smaller.`
<!-- legacy-translation-keys:end -->
