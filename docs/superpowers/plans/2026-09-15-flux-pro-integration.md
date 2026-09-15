# Flux Pro Integration — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `executing-plans` to implement this plan task-by-task. Steps use checkbox syntax for tracking. Before execution, reconcile the shared worktree and the current canonical implementation ledger. Delegation is not authorized by this document.

**Goal:** максимально использовать Flux Pro во всех подходящих интерфейсах Restaurant Menu, с явным решением для каждого компонента и варианта, сохранив корректность ресторанных процессов.

**Architecture:** Flux Free + Pro обеспечивают примитивы интерфейса. Существующие Blade-композиции сохраняют предметную семантику; Livewire Forms, Policies, Actions и read services сохраняют валидацию, права, транзакции и подготовку данных. Pro устанавливается как пакет Composer; исходники библиотеки не превращаются в код приложения.

**Tech Stack:** PHP 8.5, Laravel 13.26.1, Livewire 4.4.1, установленный Flux Free 2.17.0, Tailwind 4.3.3, Vite 8.2.2, SQLite, Pest 4; целевая совместимая пара Flux/Pro выбирается в P0.

**Дата анализа:** 2026-09-15. **Статус:** предложенный план, интеграция не выполнена.

**Авторитет документов:** `docs/requirements.md` остаётся единственным каталогом требований; `docs/IMPLEMENTATION_PLAN.md` — текущим журналом исполнения. Этот документ содержит анализ и детализацию будущего этапа. После принятия плана его этапы и фактический статус нужно связать с существующим журналом, не создавать второй каталог требований и не объявлять предложенные функции реализованными.

---

## 1. PROBLEM — текущее состояние и реальные препятствия

### 1.1. Что подтверждено непосредственно

| Объект | Наблюдение | Основание |
| --- | --- | --- |
| Ветка | `main`, при начале анализа HEAD `70fcc3e`; много ранее staged/unstaged изменений | локальные `git status`, `git log`, diff |
| Библиотека | `livewire/flux` установлен как `v2.17.0`; `livewire/flux-pro` не установлен | Boost application-info и `Composer\InstalledVersions` |
| Ограничение приложения | `livewire/flux: ^2.17` | корневой `composer.json` |
| Ограничение локального Pro | `livewire/flux: 2.13.1\|dev-main` | `flux-pro/composer.json` |
| Совместимость | `Semver::satisfies('v2.17.0', '2.13.1\|dev-main') === false` | выполненная локальная проверка Composer Semver |
| Версия архива Pro | Поля `version` нет; точный официальный релиз и происхождение по одному composer-файлу не установлены | нельзя называть архив подтверждённым релизом Pro 2.13.1 только по зависимости |
| PHP/Laravel/Livewire | ограничения Pro допускают PHP 8.5, Laravel 13, Livewire 4 | основной конфликт находится в паре Flux/Pro |
| Pro Blade | 125 файлов, 18 функциональных семейств после объединения `tab`/`tabs` и `file-upload`/`file-item` | полный локальный обход `stubs/resources/views/flux` |
| Free Blade | 459 файлов, включая иконки и внутренние части | локальный vendor; это не 459 пользовательских компонентов |
| Пересечение путей | 0 одинаковых относительных путей шаблонов Free и локального Pro | Pro дополняет Free, в частности добавляет варианты Select |
| Использование Flux | 1 250 открывающих тегов в 90 first-party Blade-файлах, без опубликованных vendor overrides | статический подсчёт; не число элементов после рендера циклов |
| Основные примитивы | 390 Button, 150 Input, 94 Select, 134 Select.Option, 76 Badge, 45 Callout, 31 Switch, 11 Modal | снимок файлов во время анализа |
| Остаточный HTML | 32 Input, 16 Button, 13 Details, 2 Dialog, 2 Select, 3 Table | сюда входят hidden inputs, print/PDF и вспомогательные поля |
| Нативные таблицы | все три находятся в PDF-шаблонах | `resources/views/pdf/reports/branch-report.blade.php`, `resources/views/pdf/qr-labels.blade.php` |
| Дизайн | локальный Noto Sans, Service Clay, semantic tokens, light/dark/system | `PRODUCT.md`, `DESIGN.md`, `resources/css/app.css` |
| Платформа | фактически Blade + Livewire, Filament не является установленным стеком | Composer и архитектурные документы |
| Рабочее окружение | Boost разрешает login как `https://ruflo.test/login`; имя каталога не совпадает с доменом | URL проверен через Boost; браузерный прогон в этом анализе не выполнялся |

Рабочая копия менялась другой работой во время анализа. Существующие изменения не принадлежат этому плану. Количества выше — наблюдаемый снимок, не утверждение о неизменном HEAD или полностью проверенном релизе.

### 1.2. Почему нельзя просто подключить папку

1. **Composer-конфликт.** Корневой `^2.17` не допускает стабильный `2.13.1`, который требует папка. Ветка `dev-main` противоречит stable-only контракту. Подмена version/alias или расширение зависимости в чужом пакете не доказывает совместимость.
2. **Проверка Pro идёт через Composer.** В `vendor/livewire/flux/src/FluxManager.php` используется `InstalledVersions::isInstalled('livewire/flux-pro')`. Регистрация Blade-папки вручную не является полноценной установкой.
3. **Меняется общий JS.** `AssetManager.php` выбирает `vendor/livewire/flux-pro/dist/flux*.js` вместо Free `flux-lite.min.js`. При установке Pro меняется runtime уже существующих модалок, селекторов, sidebar, toast, OTP и других контролов.
4. **Tailwind не видит Pro.** В `app.css` включён `source(none)`, перечислены источники Free, но отсутствует источник Pro. Без него часть стилей новых компонентов не попадёт в production CSS.
5. **Локализация требует адаптации.** В Pro есть фразы `__('Bold')`, `__('Select a date')`, `__('Remove file')` и жёстко заданные `aria-label="Clear date"`, `Clear time`, `Clear selected`, `Clear search input`. `lang="ru"` не переводит такие строки автоматически.
6. **Два действующих override защищены тестами.** `input/viewable` и `toast/index` имеют allowlist и upstream SHA-256 в `FrontendStyleArchitectureTest.php`. Массовая публикация компонентов нарушит эту границу.
7. **Некоторые замены меняют поведение.** Native select → listbox, native dialog → Flux modal, ручные locale tabs → Flux tabs затрагивают focus, transport values, dirty state и Livewire morph.
8. **Editor меняет контракт контента.** Требования `sys-menu-001`, `sys-menu-002`, `sec-output-001` сейчас требуют plain text. Включение HTML без отдельной безопасной границы не допускается.
9. **Доставка ограничена GitHub push-only.** Ранее зафиксированный блокер новых Free-архивов относится к запрещённым GitHub download endpoints. Наличие ссылки в Composer metadata не даёт разрешения скачивать её. Доступность новой совместимой пары через разрешённый источник пока не проверена.

### 1.3. Измеренный вес runtime

| Файл | Bytes | Gzip bytes |
| --- | ---: | ---: |
| Установленный Free `flux-lite.min.js` | 131 877 | 33 046 |
| Локальный Pro `flux.min.js` | 270 454 | 65 474 |
| Локальный Pro `editor.min.js` | 332 263 | 104 009 |
| Локальный Pro `editor.css` | 3 956 | 960 |

Измерено локально Python gzip с одинаковыми параметрами. Разница основного JS — **+32 428 gzip bytes, около +98%** относительно текущего Free. Это сравнение файлов разных версий, а не прогноз окончательной сборки. Editor добавляется отдельно при использовании; в проверенном шаблоне он подключён через `@assets`. Его нельзя заранее включать во все гостевые страницы. Общий размер приложения и реальное HTTP-сжатие здесь не измерялись.

## 2. SOLUTION — целевая интеграция

### 2.1. Рекомендуемый путь установки

| Вариант | Решение | Условия |
| --- | --- | --- |
| Официальная совместимая стабильная пара Free/Pro через Composer | **Предпочтительно** | источник dist допустим по правилам, лицензия настроена в окружении, обновление затрагивает только необходимую часть lock |
| Локальный Composer path package из полной совместимой поставки | **Допустимо** | сначала подтвердить версию/комплектность; mirror в vendor для релиза; воспроизводимо доставлять исходный артефакт на shared hosting |
| Понижение Free до 2.13.1 ради текущей папки | **Не рекомендовано** | потребует осознанного изменения корневого ограничения и полного аудита регрессий существующего Free UI; не решает полноту свежего каталога |

План строится на первом пути либо на втором с обновлённым совместимым архивом. Текущую папку сохранить как исходный материал. Не редактировать её composer-зависимости и не копировать Pro Blade/JS в first-party каталоги ради обхода установки.

Официальная инструкция использует Composer и активацию Pro; `auth.json` содержит секреты и не должен попадать в Git. В репозитории `/auth.json` уже исключён. Для локального варианта Composer поддерживает path repository и mirror вместо symlink. [Flux installation](https://fluxui.dev/docs/installation), [Composer path repositories](https://getcomposer.org/doc/05-repositories.md#path).

### 2.2. Что означает «все функции»

- Все **18 семейств локального Pro** имеют назначение в матрице ниже и обязательный этап внедрения.
- Все **125 шаблонов**, их собственные props и составные части перечислены в приложении A. Это проверяемое покрытие поставки.
- Альтернативные режимы одного контрола — native/listbox/combobox, single/range, horizontal/vertical — не включаются одновременно в одном поле. Выбранный режим работает в продукте; остальные проверяются в тестовых fixtures, если не имеют подходящего продуктового места.
- Тестовая демонстрация сама по себе не считается внедрением в рабочий сценарий. Для каждого семейства требуется хотя бы один целевой пользовательский сценарий либо честный статус блокировки.
- Новые backend-функции, требуемые для Editor, chart series или inline-create, отдельно изменяют канонический контракт и получают свои проверки. Они не скрываются внутри «замены компонента».
- Возможности свежего сайта, отсутствующие в локальной поставке, учтены отдельно в P13. Полнота всегда относится к зафиксированной версии, не к будущим релизам библиотеки.

### 2.3. Полная матрица локального Pro

Обозначения этапов раскрыты ниже. «Регрессия» означает конкретную проверку, необходимую до замены текущего UI.

| № | Семейство / файлы | Где применять | Возможности и пределы | Этап / регрессия |
| --- | --- | --- | --- | --- |
| 1 | **Accordion — 5** | readiness, расширенные фильтры, расписания/категории каталога, onboarding summary | single/multiple disclosure, heading/content/icon, disabled, expanded, transition; ошибки раскрывают нужную секцию | P4; repeated validation, focus, morph, reduced motion |
| 2 | **Autocomplete — 3** | поиск блюда в каталоге с подсказками; подбор текстового названия/поискового запроса | input/items/item, локальный либо ограниченный серверный поиск; вводимый текст сохраняется | P3; не использовать свободный текст как доверенный ID или способ создать сотрудника |
| 3 | **Calendar — 1** | встроенный календарь выбора custom report period на широком экране | single/range, месяцы, week numbers, today, navigation, unavailable, inputs, min/max через фактический API | P5; тот же draft диапазона, timezone ветки, лимит 31 день |
| 4 | **Chart — 24** | ресторанные отчёты: популярные блюда, количество заказов и суммы по валютам | bar, line, area, point, stack; axes/grid/ticks/zero-line, legend, cursor, tooltip, summary, group/viewport | P9; числа из подготовленного payload, доступная таблица, null ≠ zero |
| 5 | **Command — 5** | быстрый переход по разрешённым рабочим разделам в authenticated shell | поиск, keyboard shortcuts, input/items/item/empty; открыть по кнопке и Cmd/Ctrl+K | P7; не перехватывать ввод/IME, не раскрывать недоступные маршруты |
| 6 | **Composer — 1** | комментарий к блюду в guest detail и редакторе черновика | multiline/input variant, header/footer, ведущие/завершающие действия; plain-text note | P8; явная кнопка сохранения, Enter переносит строку; никаких несуществующих chat/AI/attachment действий |
| 7 | **Context — 1** | desktop карточка блюда, сервисная точка, рабочий ticket | быстрые существующие действия через context menu; все те же действия доступны через обычное меню | P7; keyboard Shift+F10/Menu, touch fallback, policy, destructive confirmation |
| 8 | **Date picker — 4** | отчётный диапазон, временное скрытие блюда, закрытие ресторана | button/input/custom trigger, single/range, confirmation, presets, limits, today, clear | P5; Apply атомарно меняет один `period`; очистка не сохраняет невидимо |
| 9 | **Editor — 25** | форматированные описания меню/категорий/блюд в EN/LT/RU | toolbar/content, headings, bold/italic/underline/strike/highlight, lists, quote, link, code, alignment, sub/sup, undo/redo | P11; новый sanitizer/render contract и additive schema, безопасная paste/link обработка |
| 10 | **File upload + File item — 4** | логотипы/обложки, галерея блюда, локальный CSV | single/multiple, dropzone/inline, progress, preview, remove, invalid и disabled; обычный file chooser сохраняется | P6; максимум 8 фото, append, стабильная identity, replay, rollback, separate save |
| 11 | **Kanban — 6** | кухня/бар: позиции в колонках по текущему разрешённому статусу | column/header/cards/footer/card; компактный board + мобильный список; дополнительные drag операции только через существующий Action | P10; статус позиции и статус заказа различаются, stale/replay, 24-ticket boundary |
| 12 | **Pillbox — 13** | мультивыбор аллергенов/диетических меток, фильтры; выбор назначений при сохранении дерева | default/combobox, search/options/empty, selected chips/max/suffix, create slot по полномочиям | P3; allowlist enums/IDs, пустой выбор, удаление chip, screen-reader label |
| 13 | **Popover — 1** | компактные фильтры, подсказка доступа/готовности, вторичные параметры | локальное открытие/закрытие, позиционирование, click/keyboard; на малом экране readable panel | P4; не использовать для обязательного подтверждения или единственного вывода ошибки |
| 14 | **Select Pro — 16** | branch, menu/category, dish, department, staff/role, timezone/country | listbox/custom/combobox, searchable, multiple, selected, clearable, empty/loading, indicators check/checkbox/radio, create | P3; сохранять значения и scope; создание категории не равно произвольному вводу |
| 15 | **Slider — 2** | существующие focal X/Y изображения | 0–100, step=1, ticks, keyboard; paired numeric entry; range-режим проверяется отдельно, пока не нужен предметной форме | P6; integer validation, preview, stale revision, значения не меняют пиксели оригинала |
| 16 | **Tabs — 4** | EN/LT/RU authoring, фото-метаданные, части настроек; server sections только с сохранением lazy mount | group/tab/panel/tabs, selected/name, icons, size, scrollable, поддержанные варианты | P4; hidden validation, dirty/back-forward, одна активная Livewire-секция |
| 17 | **Time picker — 4** | интервалы работы, расписания меню, время закрытия/hidden-until | button/input, clear, dropdown, unavailable; 24-hour UI, каноническая строка времени | P5; overnight, DST, несуществующее/двойное время, серверный overlap guard |
| 18 | **Timeline — 6** | история заказа/стола и платежных событий, onboarding checkpoint summary | item/indicator/content/block/subgrid; vertical/horizontal, status, color, size | P8/P10; только существующие подтверждённые события, никаких выдуманных timestamp |

Date picker поддерживает range и отдельное подтверждение; адаптер должен сохранять предметный `BranchReportPeriod`, а не переносить правила дат в виджет. Kanban даёт композицию доски, а допустимые переходы остаются задачей приложения. [Date picker](https://fluxui.dev/components/date-picker), [Kanban](https://fluxui.dev/components/kanban).

### 2.4. Что делать с уже используемым Free

Pro работает вместе с Free. Уже внедрённые Button/Input/Field/Callout/Badge/Card/Modal/Sidebar не требуется заменять другим названием компонента.

| Группа Free | Целевое действие |
| --- | --- |
| Button, Button.Group, Toggle, Switch, Checkbox, Radio | унифицировать поддержанные props, размеры, ошибки, loading/offline; отличать toggle filter от persistent setting |
| Field, Label, Description, Error, Input, Textarea | общие translated labels/error association; простые текстовые/денежные поля остаются простыми |
| Card, Callout, Skeleton, Progress | сохранить композиции StatePanel/EmptyState и явные состояния; Progress повторно проверить после смены JS |
| Sidebar, Header, Navbar, Navlist, Brand, Profile, Avatar | сохранить shell и permission-aware navigation; добавить Command; не переделывать дизайн в демонстрационный dashboard |
| Dropdown, Menu, Tooltip, Badge, Heading, Text, Separator, Breadcrumbs | использовать для row actions, compact help, heading hierarchy; essential help доступен без hover |
| Table, Pagination | desktop сотрудники/журналы/каталог при подходящей структуре; prepared rows и серверная пагинация; mobile cards или подписанная область прокрутки |
| Modal, Toast | сохранить safe focus, локальное закрытие, один persisted toast group; перевести новые внутренние подписи |
| OTP и security controls | оставить feature flags Fortify; наличие Pro не включает passkeys/2FA |

Три нативных PDF Table остаются HTML для PDF-рендера. Hidden CSRF/transport inputs и clipboard fallback не являются долгом интеграции. Предметные `x-ui.money`, `status-badge`, `plain-text`, `page-header`, `priority-row`, `workspace-split`, `mobile-bottom-actions` сохраняются, пока несут нужную семантику. Возвращать удалённые универсальные Button/Select/Alert wrapper-классы не нужно.

### 2.5. Карта всех продуктовых поверхностей

Пути ниже относительны корню. Сначала читать соседние файлы, соответствующие `.ai/rules` и текущие тесты; не заменять целиком большой файл.

| Поверхность | Основные файлы | Что внедрить / что сохранить |
| --- | --- | --- |
| Общая оболочка | `resources/views/layouts/app/sidebar.blade.php`, `resources/views/partials/head.blade.php`, `app/Actions/Navigation/BuildApplicationNavigationAction.php` | Command, общие всплывающие меню, единый runtime; существующие scoped links |
| Auth/settings/invitation | `resources/views/livewire/auth/`, `resources/views/livewire/settings/`, `resources/views/invitations/show.blade.php` | совместимость общих Free controls/OTP/Toast, locale/focus; без изменения регистрации и Fortify flags |
| Ресторанный dashboard | `resources/views/livewire/restaurant/dashboard.blade.php`, `resources/views/components/dashboard/branch-picker.blade.php`, `report.blade.php`, `readiness.blade.php` | searchable branch select, Date picker/Calendar, Accordion, Chart; explicit all-branch и dirty pause |
| Organization/brand/branch | `resources/views/livewire/organizations/index.blade.php`, `resources/views/livewire/organizations/brands/index.blade.php`, `resources/views/livewire/organizations/brands/branches/index.blade.php` | uploads, searchable controls, row/context actions; soft delete и scoped parent |
| Onboarding | `app/Livewire/Onboarding/RestaurantSetup.php`, `resources/views/livewire/onboarding/restaurant-setup.blade.php` | селекторы, файлы, Accordion/Timeline summary; persistent checkpoint и серверный current step |
| Зоны | `resources/views/livewire/organizations/brands/branches/areas.blade.php`, `area-node-row.blade.php` | parent searchable select и context actions; явное дерево, cycle guard |
| Столы/сервисные точки | `resources/views/livewire/organizations/brands/branches/service-points/index.blade.php` | поиск зоны, формы/row actions, desktop table где полезна; QR identity и mobile board |
| QR/печать | `resources/views/livewire/organizations/brands/branches/qr/`, `resources/views/livewire/organizations/brands/branches/service-points/qr/`, `resources/css/qr-print.css` | controls экранного выбора; точные физические размеры и PDF без JS |
| Настройки филиала | `app/Livewire/Forms/BranchSettingsForm.php`, `resources/views/livewire/organizations/brands/branches/settings.blade.php` | Tabs/Accordion, Time/Date, searchable timezone/country, uploads; один atomic save |
| Команда/доступ | `resources/views/livewire/organizations/staff/index.blade.php`, `permissions.blade.php`, `resources/views/components/staff/editor.blade.php` | Tabs, searchable selectors, Pillbox summary, menus; отдельные страницы employees/invitations и area tree |
| Каталог | `resources/views/livewire/organizations/brands/branches/menu/catalog.blade.php`, `resources/views/components/menu/item-editor.blade.php` | поиск/Autocomplete, Pro Select, Accordion, context actions, rich authoring на P11 |
| Menu workspace | `app/Livewire/Organizations/Brands/Branches/Menu/Index.php`, `resources/views/livewire/organizations/brands/branches/menu/index.blade.php` | navigation tabs с существующим server allowlist и одним активным child |
| Переводы | `resources/views/components/menu/translation-fields.blade.php`, `resources/js/menu-translations.js` | Flux Tabs; completeness/error/copy/compare остаются приложением |
| Варианты/добавки/цеха | `resources/views/livewire/organizations/brands/branches/menu/variants.blade.php`, `modifiers.blade.php`, `kitchen-departments.blade.php` | searchable dish/department и Pillbox там, где связь множественная; точные цены/limits |
| Медиа | `resources/views/components/menu/item-images.blade.php`, `resources/js/menu-image-picker.js` | File upload/File item, Slider, Tabs; append/replay/focal revision |
| CSV | `resources/views/livewire/organizations/brands/branches/menu/catalog-transfer.blade.php` | File upload, Accordion, Table preview; 100 строк/1 MiB, preview и atomic apply |
| Гостевое меню | `resources/views/livewire/public-qr/guest-menu.blade.php`, `resources/views/components/menu/item-label-fields.blade.php` | Pillbox filters, Composer note, controls/gallery; guest/session identity, availability retry |
| Гостевой стол | `resources/views/livewire/public-qr/draft-order.blade.php`, `order-statuses.blade.php`, `draft-totals.blade.php`, `table-guests.blade.php` | Composer/Timeline/согласованные selections; чужие позиции read-only, precise polling |
| Waiter | `resources/views/livewire/waiter/dashboard.blade.php`, `resources/views/livewire/waiter/table-detail/` | scoped Select, Timeline, menus, review controls; внимание/branch/zone/page scope и normal mobile links |
| Kitchen/bar | `app/Livewire/Departments/Dashboard.php`, `resources/views/livewire/departments/dashboard.blade.php` | Kanban и status action UI; visible polling, oldest-first, immutable allergens/comments |
| Audit/exports | `resources/views/livewire/audit-logs/index.blade.php`, `resources/views/livewire/exports/index.blade.php` | Table, раскрываемый Timeline, date selectors только при соответствующем фильтре; redaction и CSV safety |
| Superadmin/recovery | `resources/views/livewire/superadmin/dashboard.blade.php`, `resources/views/superadmin/backups/restore-sqlite.blade.php` | согласованные controls/status/details; private backup, reauth, reason, typed confirmation |
| Error/offline/public landing | `resources/views/errors/`, `resources/views/welcome.blade.php`, общий OfflineIndicator | проверить совместимость новой базы, оставить self-contained emergency rendering |

### 2.6. Этапы исполнения

Каждый этап — отдельная проверяемая поставка. Изменения PHP проходят Pint и Larastan; каждое изменение поведения получает RED/GREEN регрессию. Полный общий gate выполняется на устойчивом объединённом состоянии, а не параллельно с чужими правками тех же файлов.

#### P0. Согласовать поставку и воспроизводимость — блокирует интеграцию

**Файлы:** `composer.json`, `composer.lock`, при локальном способе `.gitignore`; документация `docs/CURRENT_VERSION.md`, `docs/deployment.md` — только после фактического изменения.

- [ ] Повторно снять branch/status/index/diff и определить владельца незавершённой Flux Free миграции. Сохранить её итоговый baseline и observed gates.
- [ ] Проверить свободное место и активные процессы перед source mirror/coverage. Во время анализа свободно около 3.5 GiB; это не даёт запаса для нескольких одновременно работающих полных копий и отчётов.
- [ ] Установить точную версию и provenance локальной поставки по официальным метаданным/артефакту. Фиксировать SHA-256 всего артефакта, не только composer.json.
- [ ] Получить разрешённым способом совместимую stable пару Free/Pro, желательно без понижения текущей Free. Проверить зависимости, dist hosts и redirects до сетевой установки. Не обращаться к GitHub даже косвенно через Composer.
- [ ] Для private repository использовать штатные Composer credentials, не читать/печатать ключи и не добавлять их в код/документы. Для path repository использовать подтверждённую версию и `symlink: false`; исходная папка должна входить в закрытый reproducible release artifact.
- [ ] На отдельной disposable копии выполнить dependency resolution и install; проверить минимальность lock diff, обнаружение provider, `Flux::pro()`, boot и asset endpoints.
- [ ] Проверить deployment install без доступа к пользовательской рабочей папке; при path-варианте одной отправки tracked app files недостаточно.

**Приёмка:** согласованные stable constraints, воспроизводимый lock, существующий runtime `vendor/livewire/flux-pro`, доступные JS/editor assets, ни одного обхода license/version checks. Если допустимый архив недоступен — статус P0 blocked; весь последующий план остаётся конкретным, но не выполненным.

#### P1. Runtime, Tailwind, архитектурная граница

**Файлы:** `resources/css/app.css`, layouts с `@fluxScripts`, `tests/Feature/FrontendStyleArchitectureTest.php`; новый `tests/Feature/FluxProInstallationTest.php`.

- [ ] Добавить failing test на установленный Pro, поддержку Blade-компонента и Pro asset response.
- [ ] Добавить Pro `@source` рядом с Free, сохранив `source(none)` и локальные шрифты.
- [ ] Использовать ровно один `@fluxScripts` в активном layout; не импортировать `dist/flux.js` в `resources/js/app.js` и не добавлять отдельный Alpine.
- [ ] Убедиться, что routes `/flux/flux.min.js`, `/flux/editor.min.js`, `/flux/editor.css` проходят shared-hosting rewrite, возвращают правильный MIME и не требуют staff authentication.
- [ ] Editor assets грузятся только на страницах editor и корректно появляются после `wire:navigate`; повторный переход не создаёт дубликатов listeners/runtime.
- [ ] Пересмотреть существующие два override по новым upstream-файлам, сохранить минимальные diff и реальные regression tests; не обновлять hash без анализа.
- [ ] Проверить first-party scanners: при path-пакете корневой `flux-pro/` — third-party dependency, а не разрешение ослабить Blade/PHP правила во всём `resources/views`.

**Приёмка:** production build и render smoke всех 18 семейств; CSS содержит реальные применённые стили; existing Button/Modal/Sidebar/Toast/Progress/OTP работают на новом JS. В отчёте указать веса main CSS, Free/Pro runtime и editor отдельно.

#### P2. EN/LT/RU и доступность внутренних контролов

**Файлы:** `lang/en.json`, `lang/lt.json`, `lang/ru.json`, при необходимости узкие `resources/views/flux/**`; `tests/Feature/FrontendStyleArchitectureTest.php`; новый `tests/Browser/FluxProControlsTest.php`.

- [ ] Составить allowlist пользовательских внутренних строк выбранной версии: clear/search/remove, date/time navigation, presets, empty/loading, toolbar, chips и range labels.
- [ ] Сначала передавать переведённые props/slots и явно заданные accessible names через официальный API.
- [ ] Для фраз vendor без такого API сделать узкую документированную адаптацию. Не добавлять phrase-keys в основной semantic JSON и не менять глобальный Translator ради Pro. Если нужен published override, заменить только недоступные подписи/слоты, перечислить его в allowlist и связать с upstream hash и поведенческой регрессией.
- [ ] Не копировать 125 шаблонов. Для Editor использовать собственную toolbar-композицию над публичными editor primitives, когда этого достаточно; итоговый набор overrides определяется реальными пробелами выбранной версии.
- [ ] Проверять rendered accessibility tree, включая внутренние clear/remove кнопки, disabled state, selected values, announcements, helper/error associations.
- [ ] Протестировать light/dark/system, forced colors, reduced motion, 200% zoom, long strings; translations audit/scan должны видеть все новые semantic keys.

**Приёмка:** нет английских fallback в RU/LT, нет необозначенных icon buttons, keyboard доступен, существующие semantic-key ограничения сохранены. Смена локали не уничтожает draft и не меняет identity.

#### P3. Select, Pillbox, Autocomplete — формы и фильтры

**Файлы:** branch-picker; management/menu/staff/guest views из матрицы; соответствующие `app/Livewire/Forms/**`, `app/Services/Menus/CatalogData.php`, `app/Services/Staff/StaffQueryService.php` только при необходимости новых bounded reads.

- [ ] Разделить текущие 94 Select по типу: короткий фиксированный enum, длинный справочник, tenant resource, multi-selection. Зафиксировать выбранный режим каждого места в review diff.
- [ ] Начать с branch-picker и селектора блюда для variants/modifiers/waiter: сохранять строковый transport ID и текущий server scope, explicit all-branches и dirty pause guard.
- [ ] Для больших наборов использовать ограниченный серверный поиск с debounce и явными loading/empty; текущий выбранный элемент должен отображаться и вне страницы результатов. Не выводить всё дерево моделей в DOM.
- [ ] Подключить Pillbox для dietary/allergen filters и связанных multi-selection; labels из подготовленных enum options. Удаление chip не снимает не связанные фильтры.
- [ ] Для staff areas оставить видимое дерево и смысл empty=all-active-areas. Pillbox показывает выбранное/поиск, но не превращает дочерние зоны в неявно выбранные.
- [ ] Подключить Autocomplete к текстовому поиску каталога: suggestion меняет поисковую строку, выбранный текст не становится authority для ID.
- [ ] Inline create применять только для существующей разрешённой операции создания category/department: открыть её форму, сохранить через тот же Action, после успеха выбрать новый ID. Не создавать из произвольного текста staff account, role, аллерген или permission.
- [ ] Проверить malformed array/null/bool/foreign ID и валидные numeric strings через настоящий Livewire POST; не «исправлять» транспортные значения ранним cast.

**Приёмка:** те же права/результаты/pagination, отсутствие N+1, рабочая очистка и восстановление после validation/offline. Тесты: `BranchControlDashboardTest`, `MenuEditorTransportTest`, `MenuInputTransportTest`, `GuestMenuFilterTransportTest`, `TeamAreaAssignmentTest` и соответствующие browser journeys.

#### P4. Tabs, Accordion, Popover — навигация и раскрытие

**Файлы:** `resources/views/components/menu/translation-fields.blade.php`, `item-images.blade.php`, dashboard readiness, staff/menu workspace, `resources/js/menu-translations.js`, `menu-workspace.js`, `staff-workspace.js`.

- [ ] Написать regression на вторую подряд ошибку в скрытом locale panel и переход к первому invalid field.
- [ ] Перенести локальные EN/LT/RU панели на Flux Tabs с устойчивыми именами и keyboard semantics; сохранить compare/copy/completeness и primary EN mapping.
- [ ] Связать programmatic activation с текущим состоянием, не создавать две конкурирующие переменные Alpine/Flux для одной вкладки.
- [ ] Для server menu/staff sections сохранить валидированный URL и mount только активного child. Не заменять lazy mount на CSS-hidden рендер всех разделов.
- [ ] Заменить подходящие `details` на Accordion: schedule/category, readiness, extended filters, created-resource summary. При ошибке соответствующая секция открывается; polling не сбрасывает ручное состояние без причины.
- [ ] Добавить Popover для вторичных фильтров/объяснения доступа. Важная ошибка формы остаётся видимой вне закрытой панели.
- [ ] Удалить только те JS-фрагменты, обязанности которых реально перенял Flux; оставить domain dirty guard, server validation reveal, EN sync и navigation teardown.

**Приёмка:** ProductMenuWorkflowTest, TeamAdministrationWorkflowTest и onboarding/browser history проходят; скрытые child компоненты не гидратируются; Esc/focus/morph не теряют черновик.

#### P5. Date picker, Calendar, Time picker

**Файлы:** dashboard report, `app/Livewire/Restaurant/Dashboard.php`, `app/Support/Reports/BranchReportPeriod.php`, BranchSettingsForm/view, menu schedule concern/view, item editor.

- [ ] Создать адаптацию диапазона из Flux `{start, end}` в существующие draft fields либо использовать два single date pickers на первом безопасном шаге. Новый combined draft валидировать как mixed array с точными разрешёнными ключами.
- [ ] Сохранить единственную операцию `applyPeriod` и один URL `period`; предварительный выбор дат, Cancel и search не применяют диапазон.
- [ ] Сохранить today/yesterday/last7/custom и семантику last7 «включая сегодня». Не включать `allTime`, yearToDate и произвольные 365-дневные интервалы из demos.
- [ ] В desktop custom-period presentation использовать inline Calendar, в compact/mobile — Date picker; не держать два независимо активных редактора диапазона.
- [ ] Заменить time controls расписаний с сохранением HH:mm и 24h отображения. Локаль форматирует UI; branch timezone определяет бизнес-время.
- [ ] Для существующего datetime-local использовать явную пару date/time с одной validated сериализацией. Не конвертировать в browser timezone через `toISOString()`.
- [ ] Проверить overnight intervals, overlap, границу недели, DST, clear/null, недопустимую дату, повторный save и сохранение draft при отказе.

**Приёмка:** `BranchReportingTest`, `BranchControlDashboardTest`, `BranchControlResilienceTest`, `BranchSettingsTest`, `MenuScheduleTest`, `BranchControlWorkflowTest`; ranges 1/31 дней принимаются, 32 отклоняется; Back/Forward восстанавливает целую пару дат.

#### P6. File upload, File item и Slider

**Файлы:** `resources/views/components/ui/image-upload-input.blade.php`, `resources/views/components/menu/item-images.blade.php`, `resources/js/menu-image-picker.js`, menu `Concerns/ManagesItemImages.php`, `app/Livewire/Forms/MenuImagePresentationForm.php`, management/onboarding/CSV views.

- [ ] Сначала заменить одиночную загрузку логотипа/обложки: label, accepted MIME, helper, progress, disabled, error. Существующий image Action остаётся точкой persistence.
- [ ] Перенести галерею на File upload с сохранением append и stable preview keys. Проверить, что встроенный Flux upload и существующий JS не запускают два upload для одного выбора.
- [ ] Использовать File item для pending/saved rows и per-file errors; pending remove не вызывает permanent delete, progress 100% означает завершение upload, а не успешный save.
- [ ] Сохранить maximum=8 с учётом уже записанных фотографий, explicit primary, keyboard reorder, отдельный photo save, request UUID и receipt recovery после lost response.
- [ ] Перевести focal X/Y на Slider + числовое поле с общим источником значения; min=0/max=100/step=1 и существующая revision validation. Preview меняет object-position, исходные файлы не перекодируются от движения ползунка.
- [ ] CSV использует тот же visual uploader, но собственный MIME/size/row contract, preview/discard/apply и доступ к import Action.
- [ ] Backup restore оставить native multipart upload, пока не доказан совместимый transport без дополнительного временного публичного хранения. Внешний вид можно унифицировать Free controls; замена на Livewire upload для БД не обязательна ради названия компонента.

**Приёмка:** `MenuItemImageGalleryTest`, `MenuUploadReplayTest`, `MenuImageMutationReplayTest`, `MenuImagePresentationTest`, `MenuMediaTransactionTest`, `CatalogCsvTest`, `ProductMenuWorkflowTest`; 2 последовательных выбора, failed temporary batch, failed permanent save, rollback и повторный запрос не теряют данные.

#### P7. Command palette и Context actions

**Файлы:** существующий `app/Actions/Navigation/BuildApplicationNavigationAction.php`; новая предметная композиция `resources/views/components/navigation/command-palette.blade.php`; shell; row actions каталога/столов; при необходимости маленький navigation lifecycle module в `resources/js/`.

- [ ] Использовать подготовленные разрешённые navigation entries; не собирать список всех routes в Blade и не выполнять глобальный поиск по всем tenants.
- [ ] Первая palette версия работает локально по уже разрешённым links: раздел, label, secondary scope, icon и href. Дополнительный глобальный поиск сущностей — отдельная задача, не скрытый запрос на каждый keypress.
- [ ] Кнопка открытия и Cmd/Ctrl+K доступны только staff shell; не перехватывать shortcut внутри editor/link dialog, textarea, contenteditable и IME composition.
- [ ] Enter переходит по обычному разрешённому href, Escape закрывает и возвращает focus; wire:navigate освобождает listeners.
- [ ] Context открывает существующие row actions; destructive действие вызывает текущую confirmation, не выполняется на right-click.
- [ ] Проверить тот же результат через dropdown/keyboard/touch, включая действие после revocation и stale resource.

**Приёмка:** новый `tests/Feature/CommandPaletteTest.php` и browser cases: forbidden links отсутствуют; data/query delta для локального filtering равен нулю; ни один guest token/invitation URL не попадает в индекс палитры.

#### P8. Guest Composer, Timeline и общие dialogs

**Файлы:** guest-menu, draft-order, order-statuses, waiter table detail; существующие native guest/staff dialog compositions.

- [ ] Composer оборачивает существующий plain-text comment; limits, ownership, error names и request UUID сохраняются. Enter — новая строка, отправка/сохранение — явное действие. Не создавать чат между гостем и кухней.
- [ ] Timeline отображает реальные канонические статусы заказа и разрешённые события стола. Если дата перехода неизвестна, показывать только статус; не использовать текущее время как «историю».
- [ ] В гостевой истории не показывать staff audit metadata, причины с внутренними данными или личности, не разрешённые существующим guest payload.
- [ ] При попытке заменить native guest dialog на Flux Modal доказать immediate offline close, rapid close без delayed focus theft, сохранение draft и fallback focus при исчезнувшей карточке. До такой регрессии рабочий native dialog остаётся обоснованной границей.
- [ ] Staff editor имеет разные desktop nonmodal/mobile modal режимы. Не заменять его универсальным modal, теряя соседнюю рабочую панель; адаптировать Flux только после доказательства обоих режимов.

**Приёмка:** `GuestMenuProductTest`, `GuestRecoveryRegressionTest`, `DraftOrderFunctionalTest`, `WaiterTableDetailTest`, ProductMenu/Team browser journeys; чужая позиция read-only, offline close работает, reconnect не открывает закрытую панель.

#### P9. Chart — отчёты с корректной семантикой

**Файлы:** `resources/views/components/dashboard/report.blade.php`; новый `resources/views/components/dashboard/report-chart.blade.php`; существующие `app/Actions/Dashboard/BuildRestaurantDashboardAction.php`, `app/Actions/Analytics/BuildBasicAnalyticsDashboardAction.php`, `app/Services/Reports/BranchReportQuery.php`.

- [ ] Начать с bar chart популярных блюд и уже готовых агрегатов: без дополнительного SQL, с видимой подписью периода и количеством.
- [ ] Currency amounts строить по отдельным валютам; количество заказов и платежи не представлять как одну денежную series. Базовые значения — integer cents, форматированные labels готовит сервер.
- [ ] Добавить chart legend/tooltip/summary/axes/grid/zero line с той же семантикой; доступная таблица содержит те же данные и remains usable без интерактивного tooltip.
- [ ] Для line/area/point/stack ввести реальный контракт временных series: что считается в каждом bucket, timezone, bounds, missing/stale semantics. Не рисовать выдуманные промежуточные точки по одному итоговому числу.
- [ ] Первый вариант series ограничить выбранным филиалом и <=31 днём. Если существующий SQL shape не может дать bounded daily aggregates без raw SQL/queries-in-loop, добавить отдельную Eloquent summary model и additive daily-summary таблицу, обновляемую разрешёнными mutation boundaries и bounded rebuild-командой. Перед реализацией — schema MCP, index review, отдельное решение в architecture/performance и тест backfill/invalidation. Не делать 31 запрос в цикле ради графика.
- [ ] Для all-branch режима сохранить существующие totals; временную series объединять только после определения local-calendar semantics по филиалам. «Нет доступной series» не обозначать нулём.
- [ ] Не polling весь dashboard. Chart получает готовую версионированную snapshot; failed refresh сохраняет корректно помеченный stale результат.

**Приёмка:** `BranchReportingTest`, `BranchReportCacheVersionTest`, `BasicAnalyticsTest`, новый `tests/Feature/ReportChartTest.php`; отдельные cases DST, две валюты, payment-only period, no permission, empty и stale; query budget и hydration не зависят линейно от числа заказов.

#### P10. Kanban — кухня/бар, затем история исполнения

**Файлы:** `app/Actions/Departments/BuildDepartmentDashboardAction.php`, `app/Livewire/Departments/Dashboard.php`, `resources/views/livewire/departments/dashboard.blade.php`; новые предметные `resources/views/components/departments/kanban-board.blade.php`, `ticket-card.blade.php` при подтверждённом повторении.

- [ ] Сначала определить карточку как ticket item: один parent ticket может содержать разные статусы; перенос целого заказа не эквивалентен готовности одной позиции.
- [ ] Построить board из уже bounded payload, оставив ticket/table/guest context и grouping. Указать, что board показывает текущую страницу; counters всей очереди не выдавать за число видимых карточек.
- [ ] Сохранить current filter и pagination, oldest-first active, newest-first history. Для независимой pagination по колонкам нужен отдельный bounded read contract и измеренный query budget.
- [ ] Колонки используют канонические states. `completed` остаётся read state ready+served_at, cancellation не становится свободной drag destination.
- [ ] Первое внедрение использует явные существующие кнопки перехода. Drag-and-drop — дополнительный путь к тому же `UpdateDepartmentTicketItemStatusAction`, с ожидаемым исходным состоянием, policy, rollback UI и keyboard action.
- [ ] Poll.visible не перетаскивает focus/scroll, не перезаписывает локальный interaction; drag на stale item отклоняется с объяснением. В фоне не добавляются WebSockets/worker.
- [ ] На телефоне сохранить список либо одну выбранную status column, а не широкую доску за пределами viewport. Историю позиции/заказа показывать через Timeline.

**Приёмка:** `KitchenDepartmentTest`, `BarDepartmentScreenTest`, `DepartmentAccessActionTest`, `ReadyItemsToWaiterTest`, lifecycle/concurrency tests, новый `tests/Browser/DepartmentKanbanWorkflowTest.php`; один transition → одна запись истории, чужой department denied, устаревшая карточка не регрессирует статус.

#### P11. Editor — полноценный форматированный контент

**Предлагаемые изменения контракта:** `sys-menu-001`, `sys-menu-002`, `sec-output-001`, `sec-input-001`, `sys-menu-005` в существующем `docs/requirements.md`; затем compliance/architecture/security/data/localization/testing. Это отдельная часть принятия плана, не разрешение произвольного HTML в остальных полях.

**Файлы:** меню translation models и Actions синхронизации; новая additive migration для nullable rich-description в таблицах translations тех сущностей, у которых уже есть description; новый `app/Support/SanitizedMenuDescription.php`, focused sanitizer в `app/Services/Menus/`, `resources/views/components/menu/rich-description.blade.php`; shared translation editor и guest prepared payload.

- [ ] Зафиксировать HTML subset: paragraphs, безопасные headings, emphasis/strong/u/s/mark, ordered/unordered lists, blockquote, code, sub/sup и ограниченные alignment values; no arbitrary style, script, iframe, event attributes, embeds и remote images.
- [ ] Выбрать поддерживаемый sanitizer с безопасным allowlist, проверить уже доступный dependency graph и официальную документацию. Если нужен новый production пакет — добавить его как явную зависимость этого этапа; regex sanitizer не использовать.
- [ ] Сохранять canonical sanitized rich content плюс обычное текстовое представление для совместимости CSV/search/экспортов. Имена, аллергены, комментарии гостей, alt/caption и order snapshots продолжают быть plain text.
- [ ] Старые строки с null rich content отображаются как прежде. Не переписывать существующие значения в массовой миграции и не менять order snapshots.
- [ ] Точно определить CSV semantics: импорт plain-description заменяет plain projection и очищает/обновляет rich representation по явному правилу; иначе старый HTML может скрыть импортированный текст. Preview обязан показать это последствие.
- [ ] Только named tested renderer получает безопасный value object и может использовать raw output. Blade не вызывает sanitizer и не принимает произвольную HTML-строку под видом trusted.
- [ ] Toolbar покрывает все локальные инструменты. Для Heading 1 не ломать единственный page h1: content sanitizer/renderer нормализует уровни внутри контента. Alignment принимает только allowlisted классы/значения; hyperlink протоколы проверяются, переходы наружу безопасны.
- [ ] Проверить EN/LT/RU tabs, repeated invalid state, paste из офисных документов, empty document, max text/HTML length, undo/redo, source conflict и navigation dirty guard.
- [ ] Включать editor assets только в authoring. Опубликованный sanitized content не требует загрузки editor.js у гостя.

**Приёмка:** новые `tests/Unit/MenuDescriptionSanitizerTest.php`, `tests/Feature/MenuRichDescriptionTest.php`, `tests/Browser/MenuRichEditorWorkflowTest.php`; XSS URLs/attributes/malformed markup проходят отрицательные cases, CSV и legacy null roundtrip корректны, cache invalidation охватывает все локали. До этого этапа Editor нельзя объявить интегрированным.

#### P12. Завершить все оставшиеся поверхности и правила

**Файлы:** management/staff/audit/export/superadmin views; `AGENTS.md`, `.ai/rules/flux.md`, `.ai/rules/ui.md`, `.agents/skills/fluxui-development/SKILL.md`, canonical docs.

- [ ] Пересмотреть remaining native controls по месту: migrate только пользовательские control primitives; PDF, hidden/CSRF fields и оправданные native dialogs оставить с причиной.
- [ ] Встроить Table/selected rows/row menus в tabular desktop данные при сохранении mobile cards и independent paginator. Не писать клиентскую сортировку одной страницы как сортировку всей базы.
- [ ] Прогнать auth/invite/reset/security, onboarding, QR printing, notifications, exports и backup smoke после глобальной замены JS.
- [ ] Обновить локальное правило Free-only на реально установленный Pro baseline, list of supported components и override policy; durable team rules записывать через Boost `record-rule`.
- [ ] Уточнить конфликт документации размеров operational controls: active requirement для department содержит 64px, дизайн/текущая реализация описывают 56px. Для новой доски использовать минимум 64px, пока canonical requirement не изменён явно; не выбирать меньший размер молча.
- [ ] Обновить current execution ledger/evidence после каждого завершённого этапа. Новые proposed features не получают статус verified из старых full-suite результатов.

**Приёмка:** ни одной необъяснённой product surface вне migration matrix, ни одного устаревшего запрета Pro в активных правилах; все исключения имеют конкретную причину и тестируемый контракт.

#### P13. Возможности текущего сайта, отсутствующие в локальном архиве

Официальный каталог на дату анализа дополнительно перечисляет **Carousel, Color picker, Flag, Phone**. В локальном Pro и установленном Free соответствующих root templates нет. Нельзя обещать их доступность из нынешней папки. После P0 переснять каталог выбранной версии и уточнить принадлежность Free/Pro. [Текущий каталог Flux](https://fluxui.dev/docs/installation).

| Возможность | Предложенное место | Необходимая граница |
| --- | --- | --- |
| Carousel | guest dish gallery, изображения в редакторе | максимум 8, lazy selected gallery, alt/caption, без autoplay, keyboard/touch и reduced motion |
| Color picker | настройка существующих QR print accent/presets | сначала расширить canonical print contract и server color allowlist, проверить контраст/scanability и PDF; не перекрашивать весь tenant UI без требования |
| Flag | визуальное дополнение country selector | только страна с текстовой подписью; флаг не обозначает язык интерфейса |
| Phone | контактный телефон филиала | server normalization/validation и обратная совместимость сохранённых номеров, локальная библиотека без внешнего lookup |

- [ ] Подтвердить точный template/API каждого компонента в выбранном release.
- [ ] Добавить подходящие продуктовые cases и отрицательную validation; не подменять отсутствующий Pro самодельным одноимённым компонентом.
- [ ] Выполнить отдельный browser acceptance и обновить inventory denominator: 125 локальных файлов не означают полноту более новой поставки.

#### P14. Финальные gates и доставка

- [ ] Завершить scoped tests каждого этапа; затем полные backend, browser и coverage на одном source manifest.
- [ ] Выполнить dependency audits, production build, cache compilation, translations и scoped diff review. Выполнять только допустимые сетевые запросы.
- [ ] Повторить весь путь invite/onboarding → menu → guest draft → waiter confirmation → kitchen/bar → serving → offline payment → closure в disposable SQLite.
- [ ] Проверить compiled CSS/runtime/editor bytes и guest payload; новые charts/boards не превышают согласованных query/row budgets.
- [ ] Проверить shared-hosting release artifact с `composer install --no-dev`, production assets и asset routes без ссылок на локальные path sources.
- [ ] Подготовить rollback на предыдущий code+lock+assets artifact. Для rich schema использовать expand-first: старый код продолжает читать plain projection, новые столбцы не удалять при срочном откате.
- [ ] После зелёных gates и обычного review локально коммитить только свои согласованные файлы. GitHub остаётся только возможной целью обычного push; не создавать PR/Actions и не проверять remote дополнительным запросом.

### 2.7. Порядок зависимостей

```text
P0 compatible package
  -> P1 runtime/CSS -> P2 i18n/a11y
      -> P3 selectors -> P4 tabs/disclosures -> P5 dates
      -> P6 media/sliders
      -> P7 command/context
      -> P8 guest/timeline
      -> P9 charts
      -> P10 department kanban
      -> P11 rich descriptions
  -> P12 remaining surfaces/rules
  -> P13 expanded selected-release catalogue
  -> P14 complete acceptance/release
```

P9/P10/P11 требуют наиболее сильной проверки data/security semantics. Начинать с них до завершения P1/P2 нецелесообразно. Каждый этап сохраняет рабочий продукт, а не оставляет смешанные несовместимые половины нового контрола.

## 3. QUERY DELTA — нагрузка и данные

Это **проектные ограничения**, а не результаты нового профилирования. В рамках анализа запросы production DB и EXPLAIN не выполнялись.

| Замена | Ожидаемый delta | Обязательное доказательство |
| --- | --- | --- |
| Tabs/Accordion/Popover/Slider/Composer | 0 дополнительных запросов при том же действии | сравнение focused existing tests + bounded Livewire request count |
| Select по уже готовым options | 0 | те же подготовленные selected/scoped данные |
| Server searchable Select/Autocomplete | один ограниченный цикл поиска; точный budget установить по текущему service | один debounce, limit, current selection; никаких N+1 и unbounded options |
| Command по navigation payload | 0 | не строить второй navigation query graph |
| Chart из популярных блюд/готовых totals | 0 | передавать тот же prepared payload |
| Новая daily chart series | отдельный bounded read/summary contract | schema/index/EXPLAIN и cache invalidation до объявления производительности |
| Kanban из существующей страницы | 0 дополнительных reads, при сохранении payload | grouping в Action/presenter, не Blade; не размножать mounted queues |
| Independent Kanban columns | возможен измеренный рост | max pages/rows/queries документируются, counts не внутри loop |
| Timeline | 0 при существующей history в payload; иначе один bounded scoped history read | actor redaction, ordering, eager loading; история не загружается по запросу на каждый row |
| Editor | schema/content processing change, запросы измерить | сохранять прежний transaction/cache/locale owner boundary |
| Upload | прежние Actions/receipts | новый внешний вид не создаёт дублирующую запись/загрузку |

Старые test budgets, такие как guest cold/warm и department query counts, берутся из текущих regression tests и перепроверяются на целевом source. Цель — не увеличить их случайно, а не переписать числа в тесте до зелёного цвета.

## 4. REUSABLE SNIPPET — минимальные границы интеграции

Ниже целевые фрагменты, **ещё не применённые к приложению**. Их API необходимо повторно сверить с версией, выбранной в P0.

### 4.1. Tailwind source

```css
@import 'tailwindcss' source(none);
@import '../../vendor/livewire/flux/dist/flux.css';

@source '../../vendor/livewire/flux/stubs/**/*.blade.php';
@source '../../vendor/livewire/flux-pro/stubs/**/*.blade.php';
```

Это дополнение существующих источников, остальные first-party/Laravel источники не удаляются. У локального Pro нет отдельного `dist/flux.css`: базовый CSS остаётся в Free, editor CSS обслуживается отдельно.

### 4.2. Проверка факта установки

Файл при исполнении: новый `tests/Feature/FluxProInstallationTest.php`.

```php
<?php

declare(strict_types=1);

use Composer\InstalledVersions;
use Flux\Flux;

test('Flux Pro is installed through Composer and serves its runtime', function (): void {
    expect(InstalledVersions::isInstalled('livewire/flux-pro'))->toBeTrue()
        ->and(Flux::pro())->toBeTrue();

    $this->get('/flux/flux.min.js')->assertOk();
    $this->get('/flux/editor.min.js')->assertOk();
    $this->get('/flux/editor.css')->assertOk();
});
```

Этот test подтверждает bootstrap, но не заменяет реальный browser smoke: JS может вернуть 200 и не согласовываться с Blade/CSS.

### 4.3. Time picker поверх существующей формы

```blade
<flux:time-picker
    type="input"
    wire:model="form.openingHours.{{ $dayIndex }}.intervals.{{ $intervalIndex }}.opens_at"
    :label="__('ui.organizations.brands.branches.settings.opens')"
    wire:offline.attr="disabled"
/>
```

Данные, имена полей и shared rules остаются прежними. Locale/24-hour props выбранного release и внутренние labels добавляются в P2/P5 после подтверждения API. Клиентский picker не заменяет NonOverlappingOpeningHours.

### 4.4. Предметные композиции

Рекомендуемые новые композиции имеют реальную предметную ответственность: `navigation/command-palette`, `dashboard/report-chart`, `departments/kanban-board`, `menu/rich-description`. Они получают prepared presentation data. Не добавлять новую параллельную библиотеку `x-pro.button`, `x-pro.input`, `x-pro.select` и не переносить vendor-код внутрь неё.

## 5. BLADE USAGE — поток данных и состояния

```text
Scope + Policy
  -> существующий Read service / Action
  -> bounded prepared options, rows, chart points, event labels
  -> class-based Livewire + отдельный Blade
  -> Flux component
  -> untrusted browser state
  -> Livewire Form validation + Action authorization
  -> transaction / existing receipt
  -> translated success, conflict or retry state
```

- В Blade нет `@php`, queries, service calls, sanitizer calls, денежных вычислений или группировки chart/kanban data.
- Сортировки, filters, ranges и ID из Flux повторно валидируются сервером. `#[Locked]`, disabled option и hidden button не являются проверкой доступа.
- Числа для charts и отформатированные labels готовятся в read boundary. Structured payload остаётся bounded и не включает секреты/модели/builders.
- Local tabs, раскрытие и закрытие overlay не требуют server request. Persisted mutations и их результат принадлежат Livewire/Action.
- `.live` остаётся только там, где нужна немедленная реакция; drag/slider keystrokes не создают запрос на каждое пиксельное движение без необходимости.
- Все изменённые компоненты сохраняют loading/empty/filtered-empty/error/offline/disabled/success/stale states и стабильные durable keys.

## 6. FILAMENT INTEGRATION

**Не применяется.** Фактический проект использует class-based Livewire и Flux, установленного Filament нет. Внедрение Pro не требует добавления Filament Resources, Panels, Form/Table builders или второго admin framework. Общие ранние инструкции про Filament 3 не меняют проверенный стек репозитория.

## 7. TESTS — проверка плана и будущей реализации

### 7.1. Что выполнено сейчас

- Прочитаны canonical requirements, архитектурный и frontend контекст, локальные правила и relevant source/tests.
- Через Boost подтверждены установленные версии и Herd URL; документационный поиск Boost по Pro installation вернул отсутствие результатов, поэтому использованы официальные страницы Flux и локальные файлы.
- Выполнены полный inventory Pro Blade, счётчик использования Flux/остаточного HTML, локальная проверка Composer Semver и измерение веса runtime-файлов.
- Проверены состав `composer.json`, asset selection и publisher/override boundary.
- Программная проверка самого документа подтвердила точное покрытие 125/125 Pro-шаблонов и 90/90 текущих файлов с Flux, последовательность 15 этапов, восемь обязательных разделов, парность code fences и отсутствие неожиданных несуществующих путей. Будущие новые файлы и ещё не установленный vendor Pro отмечены отдельно; trailing whitespace не обнаружен.
- **Не выполнялись:** установка/активация Pro, изменение dependencies/schema/данных, application tests, build, runtime browser QA, deployment/commit/push. Старые результаты в `docs/testing.md` не выдаются за проверку этого плана.

### 7.2. Матрица регрессий

| Область | Обязательные cases |
| --- | --- |
| Runtime | component registration, Free+Pro coexistence, 200/MIME assets, no duplicate Alpine, hard refresh/navigate/back-forward |
| Select/Pillbox/Autocomplete | empty/current/cleared/multiple, missing selected option, debounce, malformed/foreign ID, permission revoked, keyboard and labels |
| Tabs/Accordion | repeated identical errors, invalid hidden locale, copy-to-empty, dirty state, server section allowlist, local close offline |
| Dates/time | null/partial/invalid/reversed, 1/31/32 days, today/yesterday/last7 incl today, two branch timezones, DST, overnight overlap, history |
| Files | content MIME mismatch, over limit, append, remove/reselect, per-file errors, failed save/retry, same UUID replay, rollback vs post-commit failure |
| Slider | min/max/step, keyboard, numeric input parity, invalid transport, stale revision, reduced motion |
| Command/Context | allowed entries only, browser shortcut conflicts, touch fallback, dangerous confirmation, no token leakage |
| Composer | multiline/IME, max length, ownership, failed save retention, exact-once submit, guest offline state |
| Timeline | actual history ordering/timezone, empty/missing dates, no fabricated events, guest redaction, stale refresh |
| Chart | numeric exactness, currency separation, accessible data table, empty/unavailable/stale, cache scope, bounded series |
| Kanban | forward-only item transitions, cancelled/served guard, stale/drop replay, one audit event, no whole-order accidental transition, mobile pagination |
| Editor | sanitizer allowlist, javascript/data URLs, attributes/styles, nested malformed paste, headings, text projection, legacy null, CSV replacement, locale preservation |
| Cross-product | tenant isolation, invite lifecycle, onboarding resume, guest add/send, waiter confirm, kitchen serve, manual payment, table closure, QR permanence |

### 7.3. Команды и ожидаемый результат при исполнении

Сначала убедиться, что testing использует SQLite `:memory:` либо принадлежащий проверке temporary file; общий application DB не использовать. Browser runner уже создаёт disposable runtime. Полные gates не запускать одновременно с чужим тестовым/coverage процессом при недостатке места.

```bash
# После P1:
php artisan test --compact tests/Feature/FluxProInstallationTest.php tests/Feature/FrontendStyleArchitectureTest.php tests/Feature/DesignSystemTest.php

# После P3–P6, с соответствующими добавленными регрессиями:
php artisan test --compact tests/Feature/MenuEditorTransportTest.php tests/Feature/BranchSettingsTest.php tests/Feature/MenuScheduleTest.php tests/Feature/MenuImagePresentationTest.php

# Общие gates:
composer validate --strict
composer check-platform-reqs --lock
composer audit --locked
php artisan translations:scan --no-interaction
php artisan translations:audit --no-interaction
vendor/bin/pint --dirty --format agent
composer analyse
composer test:backend -- --parallel --processes=4
composer test:browser -- --browser safari
composer test:browser -- --browser chrome
composer test:browser -- --browser firefox
composer test:coverage -- --parallel --processes=4
npm audit
npm run build
git diff --check
```

Ожидается exit 0 без новых skipped/failing regressions, coverage >=90%, compiled assets без missing utilities, отсутствие ошибок консоли и Livewire requests. Pint `--dirty` запускать только после reconciliation владения: он может менять и чужие dirty PHP-файлы; при совместной работе formatter выполняется согласованным владельцем интеграции.

Перед коммитом также выполнить требуемый репозиторием `php artisan test`; bounded browser coordinator и parallel backend дают дополнительное доказательство, а не разрешение назвать непройденную команду успешной. Для миграций P11/возможных summary tables P9 отдельно fresh/upgrade/rollback/idempotent seeds на owned temporary SQLite, затем route/config/view/event cache checks в isolated runtime.

### 7.4. Browser acceptance

- Chrome DevTools MCP/Playwright MCP, disposable profiles, штатный sandbox, URL через Boost; не запускать новый dev server поверх Herd.
- 320/360/390/430/768/1024/1440/1920 CSS px; light/dark/system; EN/LT/RU. Плотная exhaustive матрица — для новых базовых контролов; все critical journeys — на mobile и desktop.
- Keyboard Tab/Shift+Tab/Enter/Space/arrows/Escape, focus restoration, no clipped controls, page h1/landmarks, 200% zoom, reduced motion/forced colors.
- Настоящий offline для local close/reconnect, slow network и lost response для retry; mock event нельзя называть полноценным network outage.
- Physical VoiceOver/NVDA/touch/печать фиксируются отдельным evidence. Эмуляция Chromium/WebKit не является физической сертификацией.

### 7.5. Definition of done для «максимально интегрировано»

- [ ] Все 18 локальных семейств имеют работающий продуктовый сценарий; оставшийся blocker явно указан, если такой этап ещё не выполнен.
- [ ] Все 125 шаблонов/составных частей сопоставлены inventory и выбранной версии; новые файлы целевого release добавлены в inventory.
- [ ] У альтернативных вариантов есть product use либо конкретная QA/неприменимость; компонентная галерея не подменяет внедрение.
- [ ] Все строки и accessible names EN/LT/RU, mobile/keyboard/forced colors проходят.
- [ ] Actions/Policies/Forms, guest identity, permanent QR, immutable snapshots и manual settlement сохранены.
- [ ] Нет зависимости от WebSockets, S3, Redis, paid runtime или постоянного worker.
- [ ] Лицензионная поставка и release artifact воспроизводимы; рабочие secrets не закоммичены.
- [ ] Full gates привязаны к одному source snapshot, docs/compliance соответствуют факту, rollback проверен.

## 8. CAVEATS — ограничения, решения и источники

### 8.1. Решения, которые нельзя скрыть при реализации

1. **Версия:** сначала совместимая пара; не начинать массовую замену на несовместимой папке.
2. **Новые функции:** sanitized rich descriptions и daily series требуют собственных data contracts; UI kit не реализует их автоматически.
3. **Локализация vendor:** поддержанные slots/props в приоритете; узкие overrides возможны только с обоснованием и тестами. Не ослаблять scanner глобально.
4. **Native boundaries:** PDF, secure backup upload, offline guest dialog и desktop staff panel нельзя ухудшать ради процента тегов Flux.
5. **Состояние репозитория:** текущая Flux Free работа продолжалась во время анализа. Перед исполнением обновить baseline, не перетирать её unstaged/staged результат.
6. **Диск:** свободное место проверять заново; read-only анализ не выполнял cleanup.
7. **Лицензия:** локальный `LICENSE.md` и авторизованный канал доставки сохраняются; этот анализ не устанавливает entitlement аккаунта и не требует раскрытия ключа.
8. **Свежесть:** текущая web-документация содержит API более новых релизов. Истина для кода — выбранная совместимая версия и её реальные templates/runtime.

### 8.2. Источники

**Локальные:** `flux-pro/composer.json`, `flux-pro/LICENSE.md`, весь `flux-pro/stubs/resources/views/flux`, `flux-pro/src`, `flux-pro/dist/manifest.json`; `vendor/livewire/flux/src/FluxManager.php`, `AssetManager.php`; `composer.json`, `composer.lock`; `resources/css/app.css`, `resources/js/*`; перечисленные views/classes/tests; канонические документы и `.ai/rules`.

**Официальные web-источники, проверенные 2026-09-15:**

- [Flux installation](https://fluxui.dev/docs/installation) — установка, assets и текущий каталог.
- [Flux Select](https://fluxui.dev/components/select), [Pillbox](https://fluxui.dev/components/pillbox) — расширенные варианты выбора.
- [Flux Date picker](https://fluxui.dev/components/date-picker) — диапазоны/подтверждение; domain presets приложения остаются собственными.
- [Flux File upload](https://fluxui.dev/components/file-upload) — uploader/dropzone/file item; transaction и receipt остаются в приложении.
- [Flux Chart](https://fluxui.dev/components/chart), [Kanban](https://fluxui.dev/components/kanban) — визуальные примитивы, не backend аналитики/переходов.
- [Flux Editor](https://fluxui.dev/components/editor) — rich authoring и отдельные editor assets.
- [Composer path repositories](https://getcomposer.org/doc/05-repositories.md#path) — локальная пакетная установка и mirror.

### 8.3. Первый практический результат после принятия плана

Первый milestone: **совместимая Pro-поставка + runtime/CSS + EN/LT/RU + branch/dish selectors + locale tabs + file upload**. Он даёт большой охват существующего UI и проверяет самые важные интеграционные границы до charts, kanban и rich text. Полная программа заканчивается P14, а не этим milestone.

## Приложение A. Полный реестр 125 локальных шаблонов Pro

Реестр ниже генерируется из фактических файлов. `Props` показывает имена, объявленные самим шаблоном; forwarded HTML attributes, Livewire directives, slots, inherited `@aware` и JS-only API дополнительно проверяются по целевому release. Внутренние варианты не следует вызывать как самостоятельные продуктовые компоненты только ради покрытия.

| № | Файл относительно `flux-pro/stubs/resources/views/flux/` | Props шаблона | Этап |
| --- | --- | --- | --- |
| 1 | `accordion/content.blade.php` | `transition`, `expanded` | P4 |
| 2 | `accordion/heading.blade.php` | `disabled`, `variant` | P4 |
| 3 | `accordion/icon.blade.php` | `pointing`, `disabled` | P4 |
| 4 | `accordion/index.blade.php` | `variant` | P4 |
| 5 | `accordion/item.blade.php` | `transition`, `disabled`, `expanded`, `heading` | P4 |
| 6 | `autocomplete/index.blade.php` | `filter`, `disabled` | P3 |
| 7 | `autocomplete/item.blade.php` | — | P3 |
| 8 | `autocomplete/items.blade.php` | — | P3 |
| 9 | `calendar/index.blade.php` | `selectableHeader`, `weekNumbers`, `unavailable`, `withInputs`, `navigation`, `withToday`, `months`, `value`, `mode`, `size`, `name` | P5 |
| 10 | `chart/area.blade.php` | `field` | P9 |
| 11 | `chart/axis/grid.blade.php` | — | P9 |
| 12 | `chart/axis/index.blade.php` | `axis`, `format`, `field`, `position`, `tickValues` | P9 |
| 13 | `chart/axis/line.blade.php` | — | P9 |
| 14 | `chart/axis/mark.blade.php` | — | P9 |
| 15 | `chart/axis/tick.blade.php` | `format` | P9 |
| 16 | `chart/bar.blade.php` | `minHeight`, `field`, `radius`, `width` | P9 |
| 17 | `chart/cursor.blade.php` | — | P9 |
| 18 | `chart/group.blade.php` | — | P9 |
| 19 | `chart/index.blade.php` | `tooltip`, `summary`, `value`, `svg` | P9 |
| 20 | `chart/legend/index.blade.php` | `label`, `field`, `format` | P9 |
| 21 | `chart/legend/indicator.blade.php` | — | P9 |
| 22 | `chart/line.blade.php` | `field` | P9 |
| 23 | `chart/point.blade.php` | `field` | P9 |
| 24 | `chart/stack.blade.php` | — | P9 |
| 25 | `chart/summary/index.blade.php` | — | P9 |
| 26 | `chart/summary/value.blade.php` | `field`, `format`, `fallback` | P9 |
| 27 | `chart/svg.blade.php` | `gutter` | P9 |
| 28 | `chart/tooltip/heading.blade.php` | `field`, `format` | P9 |
| 29 | `chart/tooltip/index.blade.php` | `field`, `format` | P9 |
| 30 | `chart/tooltip/value.blade.php` | `label`, `field`, `format`, `prefix`, `suffix` | P9 |
| 31 | `chart/value.blade.php` | `field`, `format` | P9 |
| 32 | `chart/viewport.blade.php` | — | P9 |
| 33 | `chart/zero-line.blade.php` | — | P9 |
| 34 | `command/empty.blade.php` | — | P7 |
| 35 | `command/index.blade.php` | `placeholder` | P7 |
| 36 | `command/input.blade.php` | `clearable`, `closable`, `icon` | P7 |
| 37 | `command/item.blade.php` | `iconVariant`, `icon`, `kbd` | P7 |
| 38 | `command/items.blade.php` | — | P7 |
| 39 | `composer/index.blade.php` | `name`, `actionsTrailing`, `actionsLeading`, `variant`, `invalid`, `footer`, `header`, `input` | P8 |
| 40 | `context.blade.php` | `position` | P7 |
| 41 | `date-picker/button.blade.php` | `placeholder`, `clearable`, `invalid`, `size` | P5 |
| 42 | `date-picker/index.blade.php` | `selectableHeader`, `withConfirmation`, `weekNumbers`, `placeholder`, `withPresets`, `unavailable`, `withInputs`, `clearable`, `withToday`, `type`, `presets`, `trigger`, `invalid`, `months`, `value`, `size`, `name`, `mode` | P5 |
| 43 | `date-picker/input.blade.php` | `placeholder`, `clearable`, `invalid`, `size` | P5 |
| 44 | `date-picker/selected.blade.php` | `placeholder` | P5 |
| 45 | `editor/align.blade.php` | `kbd` | P11 |
| 46 | `editor/blockquote.blade.php` | `kbd` | P11 |
| 47 | `editor/bold.blade.php` | `kbd` | P11 |
| 48 | `editor/bullet.blade.php` | `kbd` | P11 |
| 49 | `editor/button.blade.php` | `iconVariant`, `icon` | P11 |
| 50 | `editor/code.blade.php` | `kbd` | P11 |
| 51 | `editor/content.blade.php` | — | P11 |
| 52 | `editor/heading.blade.php` | `kbd` | P11 |
| 53 | `editor/highlight.blade.php` | `kbd` | P11 |
| 54 | `editor/index.blade.php` | `toolbar`, `invalid`, `variant`, `name` | P11 |
| 55 | `editor/italic.blade.php` | `kbd` | P11 |
| 56 | `editor/link.blade.php` | `kbd` | P11 |
| 57 | `editor/option.blade.php` | — | P11 |
| 58 | `editor/ordered.blade.php` | `kbd` | P11 |
| 59 | `editor/redo.blade.php` | `kbd` | P11 |
| 60 | `editor/scripts.blade.php` | — | P11 |
| 61 | `editor/separator.blade.php` | — | P11 |
| 62 | `editor/spacer.blade.php` | — | P11 |
| 63 | `editor/strike.blade.php` | `kbd` | P11 |
| 64 | `editor/styles.blade.php` | — | P11 |
| 65 | `editor/subscript.blade.php` | `kbd` | P11 |
| 66 | `editor/superscript.blade.php` | `kbd` | P11 |
| 67 | `editor/toolbar.blade.php` | `items`, `variant` | P11 |
| 68 | `editor/underline.blade.php` | `kbd` | P11 |
| 69 | `editor/undo.blade.php` | `kbd` | P11 |
| 70 | `file-item/index.blade.php` | `icon`, `invalid`, `actions`, `heading`, `inline`, `image`, `text`, `size` | P6 |
| 71 | `file-item/remove.blade.php` | — | P6 |
| 72 | `file-upload/dropzone/index.blade.php` | `icon`, `withProgress`, `inline`, `heading`, `text`, `size` | P6 |
| 73 | `file-upload/index.blade.php` | `name` | P6 |
| 74 | `kanban/card.blade.php` | `heading`, `as`, `header`, `footer` | P10 |
| 75 | `kanban/column/cards.blade.php` | — | P10 |
| 76 | `kanban/column/footer.blade.php` | — | P10 |
| 77 | `kanban/column/header.blade.php` | `heading`, `subheading`, `count`, `badge` | P10 |
| 78 | `kanban/column/index.blade.php` | — | P10 |
| 79 | `kanban/index.blade.php` | — | P10 |
| 80 | `pillbox/empty.blade.php` | — | P3 |
| 81 | `pillbox/index.blade.php` | `variant` | P3 |
| 82 | `pillbox/indicator.blade.php` | — | P3 |
| 83 | `pillbox/input.blade.php` | `placeholder`, `invalid` | P3 |
| 84 | `pillbox/option/create.blade.php` | `modal` | P3 |
| 85 | `pillbox/option/empty.blade.php` | — | P3 |
| 86 | `pillbox/option.blade.php` | `filterable`, `loading`, `label`, `value` | P3 |
| 87 | `pillbox/options.blade.php` | `searchPlaceholder`, `searchable`, `search`, `empty`, `indicator` | P3 |
| 88 | `pillbox/search.blade.php` | `placeholder`, `clearable`, `closable`, `icon` | P3 |
| 89 | `pillbox/selected.blade.php` | `placeholder`, `suffix`, `size`, `max`, `input` | P3 |
| 90 | `pillbox/trigger.blade.php` | `placeholder`, `clearable`, `invalid`, `suffix`, `size`, `max` | P3 |
| 91 | `pillbox/variants/combobox.blade.php` | `selectedSuffix`, `placeholder`, `searchable`, `clearable`, `invalid`, `trigger`, `empty`, `clear`, `close`, `name`, `size`, `input` | P3 |
| 92 | `pillbox/variants/default.blade.php` | `selectedSuffix`, `placeholder`, `searchable`, `clearable`, `invalid`, `trigger`, `search`, `empty`, `clear`, `close`, `name`, `size` | P3 |
| 93 | `popover/index.blade.php` | — | P4 |
| 94 | `select/button.blade.php` | `placeholder`, `clearable`, `invalid`, `suffix`, `size`, `max` | P3 |
| 95 | `select/empty.blade.php` | — | P3 |
| 96 | `select/indicator/index.blade.php` | `variant` | P3 |
| 97 | `select/indicator/variants/check.blade.php` | — | P3 |
| 98 | `select/indicator/variants/checkbox.blade.php` | — | P3 |
| 99 | `select/indicator/variants/radio.blade.php` | — | P3 |
| 100 | `select/input.blade.php` | `placeholder`, `clearable`, `size` | P3 |
| 101 | `select/option/create.blade.php` | `modal` | P3 |
| 102 | `select/option/empty.blade.php` | — | P3 |
| 103 | `select/option/variants/custom.blade.php` | `filterable`, `indicator`, `loading`, `label`, `value` | P3 |
| 104 | `select/options.blade.php` | `searchable`, `search`, `empty` | P3 |
| 105 | `select/search.blade.php` | `clearable`, `closable`, `icon` | P3 |
| 106 | `select/selected.blade.php` | `placeholder`, `suffix`, `max` | P3 |
| 107 | `select/variants/combobox.blade.php` | `placeholder`, `searchable`, `clearable`, `multiple`, `invalid`, `empty`, `input`, `size`, `name` | P3 |
| 108 | `select/variants/custom.blade.php` | `invalid`, `clear`, `close`, `size`, `name` | P3 |
| 109 | `select/variants/listbox.blade.php` | `selectedSuffix`, `placeholder`, `searchable`, `clearable`, `invalid`, `button`, `trigger`, `search`, `empty`, `clear`, `close`, `name`, `size` | P3 |
| 110 | `slider/index.blade.php` | `range` | P6 |
| 111 | `slider/tick.blade.php` | `value (required)` | P6 |
| 112 | `tab/group.blade.php` | — | P4 |
| 113 | `tab/index.blade.php` | `iconTrailing`, `iconVariant`, `selected`, `variant`, `accent`, `name`, `icon`, `size` | P4 |
| 114 | `tab/panel.blade.php` | `selected`, `name` | P4 |
| 115 | `tabs.blade.php` | `size`, `variant`, `scrollable` | P4 |
| 116 | `time-picker/button.blade.php` | `placeholder`, `clearable`, `invalid`, `size` | P5 |
| 117 | `time-picker/index.blade.php` | `placeholder`, `unavailable`, `clearable`, `dropdown`, `type`, `invalid`, `value`, `name`, `size` | P5 |
| 118 | `time-picker/input.blade.php` | `variant`, `clearable`, `dropdown`, `invalid`, `size` | P5 |
| 119 | `time-picker/selected.blade.php` | `placeholder` | P5 |
| 120 | `timeline/block.blade.php` | — | P8/P10 |
| 121 | `timeline/content.blade.php` | — | P8/P10 |
| 122 | `timeline/index.blade.php` | `horizontal`, `align`, `size` | P8/P10 |
| 123 | `timeline/indicator.blade.php` | `variant`, `color` | P8/P10 |
| 124 | `timeline/item.blade.php` | `status`, `align`, `size` | P8/P10 |
| 125 | `timeline/subgrid.blade.php` | — | P8/P10 |

**SHA-256 исходного composer.json:** `c07f944664216e94e0eb7ac1d11f69330c188b51792e01445ce0446934e09b45`. Идентификатор прочитанного файла не подтверждает подлинность поставки.

## Приложение B. Все текущие first-party места использования Flux

Снимок переснят при сохранении плана. Число означает открывающие теги в исходнике, без разворачивания циклов. Файлы без Flux охвачены картой поверхностей и нативных исключений.

| Файл | Тегов Flux | Семейства в файле |
| --- | ---: | --- |
| `resources/views/auth/demo-login.blade.php` | 4 | button, icon |
| `resources/views/components/app-logo.blade.php` | 2 | brand, sidebar |
| `resources/views/components/auth-header.blade.php` | 2 | heading, subheading |
| `resources/views/components/dangerous-action-confirmation.blade.php` | 18 | button, description, error, field, heading, input, label, modal, text, textarea |
| `resources/views/components/dashboard/branch-picker.blade.php` | 3 | error, icon |
| `resources/views/components/dashboard/operation-card.blade.php` | 3 | button, icon |
| `resources/views/components/dashboard/readiness.blade.php` | 2 | button, icon |
| `resources/views/components/dashboard/report.blade.php` | 4 | button, input, select |
| `resources/views/components/desktop-user-menu.blade.php` | 10 | avatar, dropdown, heading, menu, sidebar, text |
| `resources/views/components/menu/item-editor.blade.php` | 16 | button, input, select, switch |
| `resources/views/components/menu/item-images.blade.php` | 12 | button, input, textarea |
| `resources/views/components/menu/translation-fields.blade.php` | 6 | button, error, input, textarea |
| `resources/views/components/modal-close-button.blade.php` | 2 | button, modal |
| `resources/views/components/passkey-registration.blade.php` | 6 | button, input, text |
| `resources/views/components/passkey-verify.blade.php` | 1 | button |
| `resources/views/components/settings/layout.blade.php` | 7 | heading, navlist, separator, subheading |
| `resources/views/components/staff/editor.blade.php` | 1 | button |
| `resources/views/components/staff/invitation-link.blade.php` | 4 | button, description, field, label |
| `resources/views/components/staff/unsaved-dialog.blade.php` | 5 | button, heading, modal, text |
| `resources/views/components/ui/area-icon.blade.php` | 1 | icon |
| `resources/views/components/ui/card.blade.php` | 3 | card, heading, text |
| `resources/views/components/ui/empty-state.blade.php` | 4 | card, heading, icon, text |
| `resources/views/components/ui/page-header.blade.php` | 1 | icon |
| `resources/views/components/ui/service-point-icon.blade.php` | 1 | icon |
| `resources/views/components/ui/state-panel.blade.php` | 4 | callout, skeleton |
| `resources/views/components/ui/status-badge.blade.php` | 1 | icon |
| `resources/views/dashboard.blade.php` | 1 | icon |
| `resources/views/invitations/show.blade.php` | 13 | button, heading, input, link, text |
| `resources/views/invitations/status.blade.php` | 2 | button |
| `resources/views/layouts/app/sidebar.blade.php` | 37 | avatar, dropdown, header, heading, menu, profile, sidebar, spacer, text, toast |
| `resources/views/layouts/auth/card.blade.php` | 2 | toast |
| `resources/views/layouts/auth/simple.blade.php` | 2 | toast |
| `resources/views/layouts/auth/split.blade.php` | 4 | heading, text, toast |
| `resources/views/livewire/audit-logs/index.blade.php` | 3 | badge, button |
| `resources/views/livewire/auth/confirm-password.blade.php` | 2 | button, input |
| `resources/views/livewire/auth/forgot-password.blade.php` | 3 | button, input, link |
| `resources/views/livewire/auth/login.blade.php` | 5 | button, checkbox, input, link |
| `resources/views/livewire/auth/reset-password.blade.php` | 4 | button, input |
| `resources/views/livewire/auth/two-factor-challenge.blade.php` | 4 | button, input, otp, text |
| `resources/views/livewire/auth/verify-email.blade.php` | 4 | button, text |
| `resources/views/livewire/departments/dashboard.blade.php` | 17 | badge, button, callout, select |
| `resources/views/livewire/departments/ticket-print.blade.php` | 2 | button |
| `resources/views/livewire/exports/index.blade.php` | 3 | button |
| `resources/views/livewire/notifications/unread-count.blade.php` | 2 | icon |
| `resources/views/livewire/onboarding/restaurant-setup.blade.php` | 54 | button, callout, error, heading, icon, input, progress, select |
| `resources/views/livewire/organizations/brands/branches/area-node-row.blade.php` | 20 | button, callout, input, select, switch |
| `resources/views/livewire/organizations/brands/branches/areas.blade.php` | 31 | button, card, heading, input, select, switch |
| `resources/views/livewire/organizations/brands/branches/index.blade.php` | 62 | badge, button, error, field, heading, input, label, select, switch |
| `resources/views/livewire/organizations/brands/branches/menu/availability.blade.php` | 9 | badge, button, heading |
| `resources/views/livewire/organizations/brands/branches/menu/catalog-transfer.blade.php` | 14 | button, heading, input, select |
| `resources/views/livewire/organizations/brands/branches/menu/catalog.blade.php` | 122 | badge, button, checkbox, heading, icon, input, modal, select, switch |
| `resources/views/livewire/organizations/brands/branches/menu/index.blade.php` | 7 | button, heading, icon, modal, text |
| `resources/views/livewire/organizations/brands/branches/menu/kitchen-departments.blade.php` | 23 | badge, button, heading, input, select, switch |
| `resources/views/livewire/organizations/brands/branches/menu/modifiers.blade.php` | 47 | badge, button, heading, input, select, switch |
| `resources/views/livewire/organizations/brands/branches/menu/variants.blade.php` | 35 | badge, button, heading, input, select, switch, text |
| `resources/views/livewire/organizations/brands/branches/qr/bulk-print.blade.php` | 14 | badge, button, select, switch |
| `resources/views/livewire/organizations/brands/branches/service-points/index.blade.php` | 87 | button, callout, card, heading, input, select, switch |
| `resources/views/livewire/organizations/brands/branches/service-points/qr/print-template.blade.php` | 6 | button, select, switch |
| `resources/views/livewire/organizations/brands/branches/service-points/qr/show.blade.php` | 7 | badge, button |
| `resources/views/livewire/organizations/brands/branches/settings.blade.php` | 46 | button, callout, error, field, input, label, select, switch, textarea |
| `resources/views/livewire/organizations/brands/index.blade.php` | 26 | badge, button, heading, input, select |
| `resources/views/livewire/organizations/index.blade.php` | 29 | badge, button, heading, input, select |
| `resources/views/livewire/organizations/staff/index.blade.php` | 39 | button, input, select |
| `resources/views/livewire/organizations/staff/permissions.blade.php` | 13 | badge, button, heading, radio |
| `resources/views/livewire/public-qr/draft-order.blade.php` | 16 | button, callout, modal, textarea |
| `resources/views/livewire/public-qr/draft-totals.blade.php` | 19 | button, callout |
| `resources/views/livewire/public-qr/guest-actions.blade.php` | 18 | button, callout, card, icon |
| `resources/views/livewire/public-qr/guest-entry.blade.php` | 14 | button, callout, description, error, field, input, label |
| `resources/views/livewire/public-qr/guest-menu.blade.php` | 30 | button, callout, icon, select, textarea |
| `resources/views/livewire/public-qr/join-requests.blade.php` | 2 | button |
| `resources/views/livewire/public-qr/notifications.blade.php` | 2 | button |
| `resources/views/livewire/public-qr/order-statuses.blade.php` | 2 | callout |
| `resources/views/livewire/public-qr/table-guests.blade.php` | 2 | callout |
| `resources/views/livewire/qr-codes/short-code-lookup.blade.php` | 8 | badge, button, input |
| `resources/views/livewire/restaurant/dashboard.blade.php` | 12 | button, checkbox, error, icon, input |
| `resources/views/livewire/settings/appearance.blade.php` | 5 | heading, radio |
| `resources/views/livewire/settings/delete-user-form.blade.php` | 11 | button, heading, input, modal, subheading |
| `resources/views/livewire/settings/profile.blade.php` | 11 | button, error, field, heading, input, label, link, select, text |
| `resources/views/livewire/settings/security.blade.php` | 36 | badge, button, callout, heading, icon, input, modal, otp, skeleton, subheading, text |
| `resources/views/livewire/settings/two-factor/recovery-codes.blade.php` | 8 | button, callout, heading, icon, text |
| `resources/views/livewire/superadmin/dashboard.blade.php` | 24 | badge, button, callout, heading |
| `resources/views/livewire/waiter/dashboard.blade.php` | 34 | button, callout, input, select, toggle |
| `resources/views/livewire/waiter/table-detail/draft-review.blade.php` | 30 | badge, button, input, select, textarea |
| `resources/views/livewire/waiter/table-detail/order-fulfilment.blade.php` | 6 | badge, button |
| `resources/views/livewire/waiter/table-detail/overview.blade.php` | 16 | badge, button, callout, select |
| `resources/views/livewire/waiter/table-detail/payment.blade.php` | 9 | badge, button, input, select, textarea |
| `resources/views/livewire/waiter/table-session-history.blade.php` | 1 | button |
| `resources/views/partials/settings-heading.blade.php` | 3 | heading, separator, subheading |
| `resources/views/superadmin/backups/restore-sqlite.blade.php` | 4 | button, callout |
| `resources/views/welcome.blade.php` | 3 | button |

Итого при сохранении: **1250 тегов / 90 файлов**.

## Приложение C. Альтернативные режимы и границы полного покрытия

| Возможность | Решение для продукта | Что дополнительно проверить |
| --- | --- | --- |
| Select native/listbox/combobox | короткие enum — native; resource lookup — listbox/combobox | single/multiple, cleared/current/missing/disabled option; пользователь не получает два независимых редактора одного поля |
| Select/Pillbox create | существующее создание category/department по полномочиям | отмена modal, чужой parent, отсутствие права, duplicate/replay; enum/role/permission не создаются из текста |
| Calendar multiple/week numbers/navigation | single/range для существующих дат; остальные режимы доступны в fixtures | multiple dates не объявлять системой бронирования или holiday schedule без предметного требования |
| Date picker presets | today/yesterday/last7/custom приложения | альтернативные библиотечные presets не снимают лимит 31 дня |
| Chart bar/line/area/point/stack/group | только реальные именованные series с единицами и accessible table | все типы/axes/ticks/zero-line/cursor/legend/tooltip/summary проверяются на bounded fixture; fixture не становится выдуманным report |
| Editor toolbar/content/borderless | полноценные разрешённые форматы описания; plain-text поля сохраняют прежний контракт | любой выключенный формат указан как ограничение; paste/link/undo/redo и serializer проверены отдельно |
| Kanban drag/sort | дополнительный путь к тому же разрешённому item transition | keyboard equivalent; свободная ручная сортировка не отменяет oldest-first queue |
| Slider single/range/ticks | single X/Y в image presentation | range остаётся протестированной возможностью библиотеки, пока нет предметного двухстороннего фильтра |
| Timeline horizontal/vertical/subgrid | vertical history на mobile; horizontal короткий summary | длинные локализованные подписи, нет даты, cancelled/error/current, no overflow |
| Composer input/multiline/slots | комментарий, явный save и plain-text payload | без фиктивных AI/voice/attachments; IME/Enter, disabled/error и сохранение draft |
| Context mouse/keyboard/touch | те же разрешённые действия строки | обычное dropdown доступно всегда; right-click не запускает mutation |
| File upload inline/dropzone/custom | inline в тесной форме, dropzone в media/CSV | native chooser и keyboard, разные стадии upload/save, pending vs permanent removal |
| Popover/Accordion/Tabs | disclosure и local state, server sections сохраняют allowlist | вложенные overlays, Escape, focus restore, validation reveal и destroy/re-init |
| Размеры, стили, иконки и slots | конкретный вариант выбирается по дизайну и месту | не требуется показывать все комбинации цветов/размеров на рабочем экране; stylesheet содержит используемые классы |

**Четыре отдельных состояния при исполнении:** пакет доступен → компонент проверен в fixture → работает реальный продуктовый сценарий → пройдены общие gates. Только последние два подтверждают продуктовую интеграцию. Полный каталог API не означает, что все взаимоисключающие варианты должны одновременно отображаться пользователю.

## Приложение D. Приоритеты и риски

| Приоритет | Этапы | Польза | Основной риск |
| --- | --- | --- | --- |
| P0 foundation | P0–P2 | совместимая поставка, работающий общий runtime, три языка | несовместимые версии, asset 404, untranslated nested controls |
| P1 ежедневные формы | P3–P6 | быстрый выбор, меньше самописной интерактивности, удобные загрузки и даты | потеря dirty/input/selection state, отличие transport shape |
| P1 навигация/гости | P7–P8 | быстрые переходы, понятные комментарии и история | потеря focus, разрешённый URL вне scope, утечка истории |
| P2 операционные экраны | P9–P10 | читаемые отчёты и производство по статусам | ложные суммы/series, рост запросов, неверный transition |
| P2 форматированный контент | P11 | полноценный authoring описаний | XSS, рассогласование HTML/plain/CSV, миграция и rollback |
| Завершение | P12–P14 | полнота поверхностей, актуальный каталог, воспроизводимый релиз | непроверенный совместный source, недоставленный local package |

Оценивать календарный срок стоит после P0 и первого вертикального сценария P3: неизвестны доступная совместимая поставка и число необходимых translation overrides. Фиксированное обещание срока до этих проверок было бы ненадёжным. Этапы с расширением данных оцениваются отдельно от замены визуальных компонентов.
