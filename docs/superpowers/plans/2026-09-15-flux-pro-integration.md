<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Flux Pro Integration — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `executing-plans` to implement this plan task-by-task. Steps use checkbox syntax for tracking. Before execution, reconcile the shared worktree and the current canonical implementation ledger. Delegation is not authorized by this document.

**Goal:** максимально использовать Flux Pro во всех подходящих интерфейсах Restaurant Menu, объединить повторяющийся код вокруг существующих предметных сценариев, перенести всю принятую поставку в отслеживаемый внутренний пакет `packages/livewire/flux-pro/` и после доказанной независимости приложения удалить исходную корневую папку `flux-pro/`. Для каждого файла, компонента и варианта требуется явное решение; корректность ресторанных процессов сохраняется.

**Architecture:** Flux Free + Pro обеспечивают примитивы интерфейса. Существующие Blade-композиции сохраняют предметную семантику; Livewire Forms, Policies, Actions и read services сохраняют валидацию, права, транзакции и подготовку данных. Канонический Pro-код находится внутри репозитория в `packages/livewire/flux-pro/`, устанавливается Composer под настоящим именем `livewire/flux-pro` физической копией в `vendor/livewire/flux-pro/`. Это часть поставки проекта с явно обозначенной upstream-лицензией и правилами обновления; first-party адаптация остаётся в `app/` и `resources/`.

**Tech Stack:** PHP 8.5, Laravel 13.26.1, Livewire 4.4.1, установленный Flux Free 2.17.0, Tailwind 4.3.3, Vite 8.2.2, SQLite, Pest 4; целевая совместимая пара Flux/Pro выбирается в P0.

**Дата анализа:** 2026-09-15. **Текущий checkpoint 2026-09-16:** локальная адаптация Pro `0.1.0` установлена offline с Free 2.17.0; целостность, SSR 18 семейств и пять asset endpoints проверены. JavaScript, локализация и продуктовая миграция продолжаются; оригинал сохранён. Журнал статуса — `docs/IMPLEMENTATION_PLAN.md`, требование — `ui-flux-pro-001`.

**Дополнение пользователя:** перенос и удаление входят в конечный результат, а не остаются необязательной уборкой. Исполнение состоит из 19 этапов P0–P18. P0–P13 внедряют совместимую поставку и функции; P14–P15 завершают внутренний пакет и консолидацию; P16 проверяет результат, P17 удаляет старую папку, P18 закрепляет порядок обновления. Полная карта всех файлов — приложение F; дополнительные сценарии и риски — E, G–J.

**Авторитет документов:** `docs/requirements.md` остаётся единственным каталогом требований; `docs/IMPLEMENTATION_PLAN.md` — текущим журналом исполнения. Этот документ содержит анализ и детализацию принятого требования `ui-flux-pro-001`. Этапы связаны с текущим журналом; новые функции нельзя объявлять реализованными до их проверки.

## Изменение способа интеграции пользователем — 2026-09-16

Пользователь выбрал **только существующий локальный код**, без официального Composer-доступа и нового архива. Это заменяет прежнее требование получить upstream release: описанный ниже HTTP 401 остаётся историей попытки получения, а не блокером текущего способа.

Целевая поставка теперь — явно обозначенная **локальная адаптация `0.1.0`** исходных 139 файлов к установленному Free 2.17.0. Это собственный номер адаптации, не версия upstream. `upstream_version` остаётся неизвестной. Оригинальная лицензия, исходный manifest и hashes сохраняются; каждый изменённый файл получает original/result hash и причину patch. Принимается только точно проверенная Free-версия, не произвольный диапазон.

**Новый P0:** offline Composer path resolution/install с реальным package identity и `symlink:false`; минимальные исправления metadata/provider; проверка фасадов, Free/Pro templates и asset endpoints. Изменение dependency pin разрешено как документированная совместимая адаптация, а не как утверждение о поддержке со стороны upstream. Licensing features/edition detection не меняются. Порядок P1–P18, требования к предметным сценариям, полному переносу и удалению оригинала сохраняются. Пока runtime-проверки не пройдены, установка не считается принятой.

В дальнейшем указания этого документа «получить совместимый официальный release», «не менять pin» и ожидание credentials применяются только к историческому варианту получения. Текущий вариант выполняется по этому дополнению и свежему `docs/IMPLEMENTATION_PLAN.md`.

## Исполнение по разрешению пользователя — 2026-09-15–16

Пользователь разрешил выполнение плана, необходимые зависимости/ПО и улучшения без дополнительных согласований. Разрешение не создаёт credentials для закрытого Composer repository и не отменяет конкретное ограничение GitHub push-only. Текущая ветка `main`, HEAD `eb3fa3d`; незавершённые сторонние правки unified workspace сохраняются.

| Этап | Текущий результат | Следующее условие |
| --- | --- | --- |
| P0 | compatibility/source preflight выполнен; установка блокирована входной поставкой | нужен совместимый Pro release через настроенный официальный доступ либо разрешённый локальный артефакт |
| P1 tooling | anchored `notPath` защищает два distribution-каталога; реальный Pint regression сохраняет 4 vendor fixtures и форматирует 5 first-party fixtures | first-party scope сохраняется; runtime-часть P1 не выполнена |
| P14 source preparation | все 139 файлов / 3 291 708 bytes скопированы побайтово; provenance, стандартный SHA-256 manifest и integrity tests работают | это ещё не принятие совместимого release, не Composer activation и не завершение P14 |
| P1 runtime, P2–P13, P15–P18 | не выполнены; действующий runtime остаётся Free | сначала снять P0 blocker, затем идти по зависимостям |

**Свежие доказательства:** Composer Semver подтвердил `compatible=false` для installed `v2.17.0` и ограничения `2.13.1|dev-main`; Pro не установлен. `https://composer.fluxui.dev/packages.json` и `/p2/livewire/flux-pro.json` вернули HTTP 401, Flux credentials в стандартных local/global Composer auth locations не настроены (проверено только наличие, значения не выводились). Локальный Composer cache содержит только нынешний Free 2.17.0 и не содержит Pro.

Найденный локальный Flux ZIP содержит те же 139 путей. 136 файлов, включая все templates/dist/composer/license/helpers, совпадают с проектом; три PHP-файла отличаются нормализацией imports/style. ZIP не содержит version, lock или проверяемый upstream tag; имя архива не доказывает номер релиза. В ZIP/RAR нет совместимого Free. Пользовательский snapshot сохраняется побайтово; он не маркируется как подтверждённо неизменённый upstream.

**Выполнено независимо от установки:** source inventory, internal storage, integrity regressions, canonical requirement/traceability и текущая документация. Не расширять upstream dependency pin, не выдумывать version, не spoof Composer registration и не удалять исходную папку, пока установка/совместимость и P16/P17 не доказаны. Остановка зависимых UI-этапов отражает конкретный внешний вход, а не ожидание повторного разрешения пользователя.

**Проверка исходников:** отдельные specification и quality reviews приняли импорт; 139/139 стандартных checksums совпали. Тест traceability после добавления 54-го требования прошёл с 1 082 assertions. Полный перечень наблюдаемых focused gates — `docs/testing.md`; они не являются проверкой Pro UI. Все исходные 139 файлов остались неизменными, четыре `.DS_Store` не переносились и не удалялись.

**Итоговые focused gates:** 14 Unit tests / 44 assertions и 48 architecture/traceability tests / 1 359 assertions прошли; scoped Pint и strict Composer validation прошли. Долговечная граница сохранения пакета записана через Boost в `.ai/rules/flux-pro.md`. Режим Pint stdin/editor не использует те же regex-фильтры: distribution-файлы через него не форматировать; обычные repo lint/dirty scans покрыты регрессией.

---

## 1. PROBLEM — текущее состояние и реальные препятствия

### 1.1. Что подтверждено непосредственно

| Объект | Наблюдение | Основание |
| --- | --- | --- |
| Ветка | `main`; первоначальный анализ начался на `70fcc3e`, повторный — на `9db2848`; во время повторного анализа появился `eb3fa3d` | локальные `git status`, `git log`, diff; это совместная изменяющаяся рабочая копия |
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

Рабочая копия менялась другой работой во время анализа. Существующие изменения не принадлежат этому плану. Числа 1 250 тегов / 90 файлов и приложение B фиксируют первоначальную инвентаризацию. Во время расширенного анализа новые navigation/dashboard/reference правки уже меняли эти числа; перед исполнением переснять их на одном согласованном source digest. Коммит `eb3fa3d` добавил план и 139 содержательных файлов `flux-pro/` в Git, но наличие файлов и название коммита не доказывают установку Pro: `vendor/livewire/flux-pro` при повторной проверке отсутствует. Активную работу из `docs/IMPLEMENTATION_PLAN.md` следует принять как вход следующего этапа, не переделывать параллельно.

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

### 2.1. Рекомендуемый путь получения и окончательного размещения

| Вариант | Решение | Условия |
| --- | --- | --- |
| Получить официальную совместимую стабильную пару Free/Pro | **Обязательный вход** | источник dist допустим по правилам, версия подтверждена, обновление затрагивает только необходимую часть lock; способ получения не определяет постоянное расположение кода |
| Внутренний Composer path package `packages/livewire/flux-pro` | **Конечная архитектура по запросу пользователя** | полная принятая поставка отслеживается вместе с проектом; настоящий package identity; `symlink: false`; production использует установленную физическую копию |
| Понижение Free до 2.13.1 ради текущей папки | **Не рекомендовано** | потребует осознанного изменения корневого ограничения и полного аудита регрессий существующего Free UI; не решает полноту свежего каталога |

Получение совместимого release и хранение его внутри проекта — последовательные задачи. До P17 исходную папку сохранить как проверяемый источник. В P14 перенести все содержательные файлы принятой поставки, не раскладывать 125 vendor templates по `resources/views/flux` и не заменять Composer ручным autoload. Если P0 выбрал более свежий release, каждому из исходных 139 файлов назначить результат сравнения: unchanged, updated, renamed либо explicitly superseded. Нельзя смешивать старый `dist` с новыми Blade или переносить заведомо несовместимую старую копию в рабочий пакет ради совпадения числа файлов. Исходный snapshot сохраняется в существующей истории Git; новые файлы выбранного release также входят в манифест.

Официальная инструкция использует Composer и активацию Pro; `auth.json` содержит секреты и не должен попадать в Git. В репозитории `/auth.json` уже исключён. Composer поддерживает path repository и mirror вместо symlink. Внутреннее размещение сохраняет лицензию и штатное определение Pro; ключи в поставку не переносятся. [Flux installation](https://fluxui.dev/docs/installation), [Composer path repositories](https://getcomposer.org/doc/05-repositories.md#path).

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

**Checkpoint:** source/compatibility preflight выполнен, P0 в целом **blocked**. На старте исполнения свободно около 1.5 GiB, поэтому полные временные vendor/node_modules копии не создавались. Подтверждён только пользовательский snapshot, а не upstream release. Дальнейшие пункты принятия совместимой поставки остаются открытыми.

- [ ] Повторно снять branch/status/index/diff и принять завершённые результаты Flux Free, navigation, notifications и local reference из текущего журнала. Согласовать владение незавершёнными файлами; сохранить baseline и observed gates, не приписывать чужие результаты этому этапу.
- [ ] Проверить свободное место и активные процессы перед source mirror/coverage. При повторном анализе доступно около 4.5 GiB; запускать временные полные копии последовательно и отдельно измерять их размер.
- [ ] Установить точную версию и provenance локальной поставки по официальным метаданным/артефакту. Фиксировать SHA-256 всего артефакта, не только composer.json.
- [ ] Получить разрешённым способом совместимую stable пару Free/Pro, желательно без понижения текущей Free. Проверить зависимости, dist hosts и redirects до сетевой установки. Не обращаться к GitHub даже косвенно через Composer.
- [ ] Для private repository использовать штатные Composer credentials, не читать/печатать ключи и не добавлять их в код/документы. Зафиксировать конечное размещение `packages/livewire/flux-pro`, подтверждённую версию и `symlink: false`. Если стартовая интеграция использует временный источник, P14 обязан переключить её на внутренний пакет до release.
- [ ] На отдельной disposable копии выполнить dependency resolution и install; проверить минимальность lock diff, обнаружение provider, `Flux::pro()`, boot и asset endpoints.
- [ ] Разделить proof локальной установки Pro и cold install всего проекта. В проверенном lock все 178 dist URL ведут на `api.github.com`; при запрете GitHub полный fresh install возможен только с достаточным проверенным локальным cache/архивами либо согласованным vendor artifact. При отсутствии входного артефакта записать конкретный blocker; не разрешать сетевой fallback.
- [ ] Проверить discovery и facade aliases выбранной пары. В нынешнем `FluxProServiceProvider` импортирован `Flux\FluxPro`, тогда как локальная facade объявлена в `FluxPro\FluxPro`, а metadata содержит alias `Flux`. Это повод для совместимого boot/render теста, не разрешение на неподтверждённую массовую правку upstream.

**Приёмка:** согласованные stable constraints, воспроизводимый lock, существующий runtime `vendor/livewire/flux-pro`, доступные JS/editor assets, ни одного обхода license/version checks. Если допустимый архив недоступен — статус P0 blocked; весь последующий план остаётся конкретным, но не выполненным.

#### P1. Runtime, Tailwind, архитектурная граница

**Файлы:** `resources/css/app.css`, layouts с `@fluxScripts`, `tests/Feature/FrontendStyleArchitectureTest.php`; новый `tests/Feature/FluxProInstallationTest.php`.

**Ранний tooling prerequisite:** до root Pint/lint исключить узко исходный upstream `flux-pro/`, уже tracked в `eb3fa3d`; при создании внутреннего package добавить точное исключение нового upstream-пути. После P17 удалить obsolete исключение старого пути. Не ждать P14, иначе ранние root gates могут переписать исходный vendor. First-party адаптации из app/resources по-прежнему форматируются.

- [ ] Добавить failing test на установленный Pro, поддержку Blade-компонента и Pro asset response.
- [ ] Добавить Pro `@source` рядом с Free, сохранив `source(none)` и локальные шрифты.
- [ ] Использовать ровно один `@fluxScripts` в активном layout; не импортировать `dist/flux.js` в `resources/js/app.js` и не добавлять отдельный Alpine.
- [ ] Убедиться, что routes `/flux/flux.min.js`, `/flux/editor.min.js`, `/flux/editor.css` проходят shared-hosting rewrite, возвращают правильный MIME и не требуют staff authentication.
- [ ] Editor assets грузятся только на страницах editor и корректно появляются после `wire:navigate`; повторный переход не создаёт дубликатов listeners/runtime.
- [ ] Пересмотреть существующие два override по новым upstream-файлам, сохранить минимальные diff и реальные regression tests; не обновлять hash без анализа.
- [ ] Проверить first-party scanners: внутренний `packages/livewire/flux-pro/` — upstream dependency; исключение его vendor idioms не ослабляет Blade/PHP правила `app/` и `resources/views/`. До P14 исходная корневая папка имеет тот же upstream статус.

**Приёмка:** production build и render smoke всех 18 семейств; CSS содержит реальные применённые стили; existing Button/Modal/Sidebar/Toast/Progress/OTP работают на новом JS. В отчёте указать веса main CSS, Free/Pro runtime и editor отдельно.

#### P2. EN/LT/RU и доступность внутренних контролов

**Файлы:** `lang/en.json`, `lang/lt.json`, `lang/ru.json`, при необходимости узкие `resources/views/flux/**`; `tests/Feature/FrontendStyleArchitectureTest.php`; новый `tests/Browser/FluxProControlsTest.php`.

- [ ] Составить allowlist пользовательских внутренних строк выбранной версии: clear/search/remove, date/time navigation, presets, empty/loading, toolbar, chips и range labels.
- [ ] Сначала передавать переведённые props/slots и явно заданные accessible names через официальный API.
- [ ] Для фраз vendor без такого API сделать узкую документированную адаптацию. Не добавлять phrase-keys в основной semantic JSON и не менять глобальный Translator ради Pro. Если нужен published override, заменить только недоступные подписи/слоты, перечислить его в allowlist и связать с upstream hash и поведенческой регрессией.
- [ ] Не публиковать 125 шаблонов как first-party overrides. Полная копия поставки в внутреннем package предусмотрена P14; это другая задача. Для Editor использовать собственную toolbar-композицию над публичными editor primitives, когда этого достаточно; итоговый набор overrides определяется реальными пробелами выбранной версии.
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

#### P14. Полный перенос поставки внутрь проекта

**Зависимости:** совместимый P0, работающие P1–P13; внутреннее размещение можно подготовить раньше, но исходную папку пока не удалять. **Файлы:** новый `packages/livewire/flux-pro/**`, новые `packages/livewire/flux-pro.provenance.json` и `packages/livewire/flux-pro.sha256`, root `composer.json`/`composer.lock`, `pint.json`, существующие installation/style tests; точная карта — приложения E/F.

**Ранняя подготовка, выполненная 2026-09-15–16 (не итоговая приёмка P14):**

- [x] Сохранить все 139 содержательных файлов текущего пользовательского snapshot во внутреннем каталоге без изменения bytes, путей и лицензии.
- [x] Добавить provenance с `upstream_version: null`, `upstream_pristine_verified: false`, `imported-uninstalled` и unresolved compatibility; записать оба формата общего digest.
- [x] Добавить полный sorted checksum manifest и независимый от исходной папки integrity Unit test; подтвердить точное совпадение исходника и копии, включая file modes.

Ниже — оставшиеся требования к **принятой совместимой** поставке. Нынешняя копия не подтверждает ни её version, ни runtime:

- [ ] Переснять рекурсивный inventory исходной и принятой совместимой поставки, включая скрытые файлы и symlinks. Сопоставить все 139 файлов приложения F; 4 `.DS_Store` классифицировать отдельно. Новый неизвестный файл требует разбора до удаления источника.
- [ ] Скопировать содержательные файлы с сохранением относительных путей, лицензии, PHP namespace, helpers, discovery metadata, восьми dist assets и 125 templates исходного snapshot. Для более нового release сохранить его полный согласованный набор; разницу со старым явно перечислить в provenance.
- [ ] Сохранить raw upstream и installed/result checksum как разные факты. Если адаптация всё-таки изменяет пакет, документировать каждый минимальный patch и его regression test; не объявлять изменённый файл byte-identical upstream.
- [ ] Добавить явный path repository только для этого пакета, с canonical source и mirror. Root действительно требует `livewire/flux-pro`; `replace`, `provide`, class aliases или ручное заполнение InstalledVersions не имитируют установку.
- [ ] Версия поставки должна быть подтверждена независимо. При отсутствии `version` использовать Composer `options.versions` с реальной установленной provenance-версией; не наследовать `dev-main` общего репозитория и не выдумывать stable release. Корневой stable-only режим сохраняется.
- [ ] Обновить только необходимую часть lock разрешённым способом. Проверить относительный `dist.url`, discovery ровно один раз, совместимость Free/Pro и неизменность unrelated dependencies. Не переносить `require-dev`, workbench или `serve` scripts Pro в root.
- [ ] Добавить узкое Pint-исключение для неизменяемого upstream-каталога. Сохранить Larastan/coverage scope приложения и все ограничения first-party Blade/translations. Integrity/compatibility проверки покрывают upstream отдельно.
- [ ] Проверить source→vendor mirror до Vite: при `symlink:false` обычное редактирование package-файла не обновляет существующую installed copy. При необходимости выполнять целевой `composer reinstall livewire/flux-pro` в изолированном окружении и проверять hashes; один `composer install` на неизменившемся lock не является доказательством синхронизации.
- [ ] Production Tailwind сканирует installed Pro `vendor/.../stubs`; не сканировать одновременно две расходящиеся копии для маскировки stale mirror. Core CSS по-прежнему приходит из Free.
- [ ] Установка и сборка в новой release-директории, где root `flux-pro` никогда не существовал, проходят из разрешённых локальных входов. Исходная рабочая папка всё ещё сохраняется до P17.

**Приёмка:** внутренний tracked package — единственный канонический источник Pro; Composer metadata и vendor physical copy соответствуют ему; весь принятый release учтён; root `flux-pro/` больше не нужен runtime, build или install. Не считать Composer reference достаточным content hash: `reference: config` не хеширует все Blade/JS.

#### P15. Консолидация сценариев и удаление заменённого кода

**Файлы:** существующие `resources/js/{menu-translations,menu-image-picker,menu-workspace,staff-workspace,kitchen-delay-timers,waiter-sounds,app}.js`, menu/dashboard/staff композиции, notifications view/component, navigation Action и текущие тесты. Новые first-party модули создавать только при подтверждённом общем поведении; точная матрица — G/H.

- [ ] Повторно сверить текущую Free navigation/notification/reference работу. Sidebar, Command и mobile navigation используют один подготовленный navigation payload, а не три реестра с разными правами.
- [ ] Один notification host и один bounded poller; Timeline использует текущую lazy modal/flyout панель. Popover допустим только при доказанной эквивалентности её recovery/accessibility. Open не означает mark-read, unread count не выводится из ограниченного списка. При повторном чтении текущая параллельная работа уже использует `PANEL_LIMIT = 20` последних read+unread уведомлений, отдельный count и destination reauthorization.
- [ ] Передать Flux собственно tabs/roving-focus/disclosure/dropzone поведение и удалить заменённые обработчики в том же change. Сохранить copy-empty/completeness/error reveal, image batches/receipts, lazy mounting и offline/dirty recovery.
- [ ] Реализовать gallery-specific Adapter событий Pro upload; один владелец file/drop событий, независимые uploading и saving/removing/offline. Проверить append, cancel, повторный файл и очистку object URLs.
- [ ] Проверить реальные focusable nodes Listbox/Editor и уникальные tab/panel keys всех повторяемых locale editors. Два независимых механизма active-tab не остаются.
- [ ] Объединять clipboard transport только с truthful success/failure, selectable fallback и таймер cleanup. Reveal/retention гостевого invite и 2FA остаются в своих предметных сценариях.
- [ ] Сохранить разные menu/staff history стратегии до доказанного общего контракта. Таймеры кухни, waiter audio и passkeys не удалять как якобы дубли Flux.
- [ ] Удалять first-party CSS/JS только после поиска всех потребителей и regression proof; не удалять неиспользованные сегодня upstream dist/module/template файлы. Проверить отсутствие импорта уже удалённого модуля.
- [ ] Не возвращать generic UiButton/UiSelect/UiTabs wrappers. Предметные composition modules обязаны скрывать реальную повторяемую сложность, а не переименовывать тег Flux.

**Приёмка:** один владелец каждого локального состояния и события, один источник каждого navigation/report/options payload, сохранённые product Actions; нет параллельных старых и новых handlers. Финальный browser проходит совместные сценарии из приложения I.

#### P16. Финальные gates и подготовка доставки

- [ ] Завершить scoped tests каждого этапа; затем полные backend, browser и coverage на одном source manifest.
- [ ] Выполнить dependency audits, production build, cache compilation, translations и scoped diff review. Выполнять только допустимые сетевые запросы.
- [ ] Повторить весь путь invite/onboarding → menu → guest draft → waiter confirmation → kitchen/bar → serving → offline payment → closure в disposable SQLite.
- [ ] Проверить compiled CSS/runtime/editor bytes и guest payload; новые charts/boards не превышают согласованных query/row budgets.
- [ ] Проверить shared-hosting release artifact с `composer install --no-dev` из допустимых локальных источников, production assets и asset routes без ссылок на старую папку или абсолютный путь компьютера. Внутренний относительный path source в lock является ожидаемым.
- [ ] Подготовить rollback на предыдущий code+lock+assets artifact. Для rich schema использовать expand-first: старый код продолжает читать plain projection, новые столбцы не удалять при срочном откате.
- [ ] Перед заключительным удалением провести review и зафиксировать проверяемый release snapshot. Локальные коммиты включают только свои согласованные файлы. GitHub остаётся только возможной целью обычного push; не создавать PR/Actions и не проверять remote дополнительным запросом.

#### P17. Удаление исходной корневой папки и доказательство независимости

**Точный объект:** `/Users/andrejprus/Herd/restaurant-menu/flux-pro`. **Предусловия:** P14–P16 приняты, rollback artifact проверен; пользователь включил удаление в конечную задачу. Этот документ не выполняет удаление.

- [ ] Непосредственно перед удалением проверить real path, отсутствие неожиданных symlinks, inventory и чужих новых изменений. Остановить только этот шаг, если обнаружены неучтённые файлы; остальная интеграция остаётся доступной для проверки.
- [ ] Каждый исходный содержательный файл имеет target либо документированный replacement выбранного совместимого release. Старый отслеживаемый snapshot доступен локально; новых source-only правок нет. Четыре `.DS_Store` явно исключены как метаданные Finder.
- [ ] Найти зависимости именно от старого пути: repository URL `flux-pro`, `base_path('flux-pro/...')`, относительные импорты, `@source`, deploy/copy scripts, source maps, cached view/provider paths и symlinks. Слово `flux-pro` в законном package name/новом пути и исторической документации не является ошибкой.
- [ ] Убедиться, что clean-release drill использовал отдельные caches/storage/SQLite и уже прошёл с отсутствующим корневым каталогом. Проверить production/debug assets и navigation в этой копии.
- [ ] Удалить только указанный исходный каталог после перечисленных доказательств, без следования symlinks и без очистки родительского проекта. Не использовать общий `git clean`, reset или широкое удаление.
- [ ] Зафиксировать в diff удаление старых tracked файлов и наличие полной принятой поставки в `packages/`. Проверить отсутствие каталога и отсутствие runtime/build ссылок на него.
- [ ] Повторить package integrity, focused installation/assets tests, production build и isolated view/config/route caches; короткий browser smoke существующего Free UI, Pro selector, gallery и lazy editor. Если изменились executable bytes, расширить повторные gates по изменениям, а не ссылаться на прежний pass.

**Приёмка:** корневого `flux-pro/` нет, приложение устанавливается и работает на коде внутреннего пакета; release и rollback не требуют восстановления старой папки. Если любой gate не пройден, удаление не считается выполненным.

#### P18. Обновление внутреннего пакета и окончательная фиксация

- [ ] В существующих `docs/frontend.md`, `docs/deployment.md`, `docs/DECISIONS.md`, `docs/testing.md`, `docs/CURRENT_VERSION.md` и canonical ledger описать фактический источник, версию, manifest digest, допустимые patches, команды mirror/update и проверенный rollback. Канонические требования/compliance обновлять только по реально внедрённым контрактам.
- [ ] Разделить upstream upgrade и продуктовые изменения: принять новый полный release во временной папке, проверить provenance/Free constraint, сравнить templates/runtime/labels, повторно применить минимальные patches, обновить content manifest, mirror и lock, затем gates. Не редактировать установленный vendor как источник.
- [ ] При security fixes проверять в том числе embedded JavaScript dependencies редактора; `npm audit` root-пакетов не подтверждает безопасность всего готового `dist` Pro.
- [ ] Отмечать точный coverage denominator выбранной версии: distribution files, rendered family/variant fixtures и настоящие продуктовые сценарии — три отдельных результата.
- [ ] Закрепить новые durable `.ai/rules` через штатный `record-rule` при исполнении, когда решение подтверждено кодом. В этом планировании rules не объявляются выполненной миграцией.
- [ ] Итоговый отчёт содержит transfer/deletion evidence, проверенные команды и exit codes, ограничения и непройденные gates. Ни один блокер не скрывается под формулировкой «максимально интегрировано».

**Приёмка:** следующий разработчик может воспроизвести установку/обновление/откат без исходной папки и без личных файлов автора.

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
  -> P14 internal package and complete transfer
  -> P15 cross-surface consolidation
  -> P16 complete acceptance/release drill
  -> P17 remove original root directory and verify
  -> P18 update procedure and final evidence
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

### 7.1. Доказательства первоначального анализа (до исполнения)

- Прочитаны canonical requirements, архитектурный и frontend контекст, локальные правила и relevant source/tests.
- Через Boost подтверждены установленные версии и Herd URL; документационный поиск Boost по Pro installation вернул отсутствие результатов, поэтому использованы официальные страницы Flux и локальные файлы.
- Выполнены полный inventory Pro Blade, счётчик использования Flux/остаточного HTML, локальная проверка Composer Semver и измерение веса runtime-файлов.
- Проверены состав `composer.json`, asset selection и publisher/override boundary.
- Первоначальная программная проверка подтвердила 125/125 Pro-шаблонов и 90/90 файлов исходного UI snapshot. Повторная проверка расширенной редакции подтвердила 139/139 paths/bytes/SHA-256, общий digest, 19 последовательных этапов P0–P18, восемь обязательных разделов, приложения A–J, парные code fences и отсутствие trailing whitespace. Scoped `git diff --check` завершился с exit 0. Новые будущие файлы, ещё не установленный vendor Pro и исторический удаляемый desktop-user-menu отделены от существующих ссылок.
- **Не выполнялись:** установка/активация Pro, изменение dependencies/schema/данных, application tests, build, runtime browser QA, deployment/commit/push. Старые результаты в `docs/testing.md` не выдаются за проверку этого плана.

**После разрешения на исполнение:** исходники скопированы в internal package, добавлен integrity Unit test и обновлена traceability-проверка 54 требований; результаты находятся в `docs/testing.md`. Это отдельный checkpoint source preparation, а не изменение исторического proof анализа выше. Установка, Pro browser/build и удаление остаются невыполненными.

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
- [ ] Полный принятый Pro package находится в `packages/livewire/flux-pro`, source/vendor hashes согласованы; root `flux-pro/` удалён только после P16/P17 proof, новые файлы и replacements учтены.
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

Первый milestone: **совместимая Pro-поставка + runtime/CSS + EN/LT/RU + branch/dish selectors + locale tabs + file upload**. Он даёт большой охват существующего UI и проверяет самые важные интеграционные границы до charts, kanban и rich text. Полная программа заканчивается P18: внутренним пакетом, удалённой исходной папкой и воспроизводимым обновлением.

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

## Приложение B. Все first-party места использования Flux исходного UI snapshot

Реестр 90 файлов / 1 250 тегов сохранён как baseline первоначального анализа. Во время расширенного анализа параллельная работа добавила account-menu, workspace navigation и local reference, а `desktop-user-menu.blade.php` удаляется. При исполнении использовать итог этой работы и переснять inventory; историческая строка ниже не означает задачу восстановить удалённый файл.

Снимок переснят при первоначальном сохранении плана; расширенная редакция сохраняет его историческим baseline. Число означает открывающие теги в исходнике, без разворачивания циклов. Файлы без Flux охвачены картой поверхностей и нативных исключений.

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
| Завершение | P12–P18 | полнота поверхностей, внутренний пакет, консолидация, удаление источника и воспроизводимый релиз | непроверенный совместный source, потерянные файлы, stale mirror, непроверенный rollback |

Оценивать календарный срок стоит после P0 и первого вертикального сценария P3: неизвестны доступная совместимая поставка и число необходимых translation overrides. Фиксированное обещание срока до этих проверок было бы ненадёжным. Этапы с расширением данных оцениваются отдельно от замены визуальных компонентов.

## Приложение E. Устройство внутреннего пакета и полная поставка

### E.1. Точная граница исходников

Повторный обход нашёл **143 физических файла / 3 316 300 bytes**, без symlinks. Содержательная поставка — **139 файлов / 3 291 708 bytes**: 125 Blade, 8 dist, 4 PHP, composer metadata и лицензия. Четыре файла Finder занимают ещё 24 592 bytes и не являются исходным кодом.

~~~text
restaurant-menu/
  packages/livewire/
    flux-pro/                       canonical tracked distribution
      composer.json                 original package name + runtime requirements
      LICENSE.md                    original proprietary notice
      src/                          facade, manager, provider, helpers
      stubs/resources/views/flux/   entire accepted template tree
      dist/                         entire accepted assets (8 in original snapshot)
    flux-pro.provenance.json        accepted release + original/current digests
    flux-pro.sha256                 full accepted file inventory
  vendor/livewire/
    flux/                           compatible Free; generated dependency
    flux-pro/                       Composer physical mirror; generated dependency
  resources/views/components/       product compositions
  resources/views/flux/              minimal audited overrides only
  resources/js/                     domain interaction adapters
  resources/css/app.css              Free CSS + installed Free/Pro sources
~~~

**Поток исполнения:** tracked package → Composer mirror/discovery → vendor sibling assets и Blade namespace → Flux-композиции приложения → Livewire Form/Action. Ни root `flux-pro/`, ни копия dist в `public/` не являются дополнительным runtime-источником.

**Значение «все файлы»:** сохранить полный поставляемый набор, включая не используемые сейчас module/debug builds и пустой helpers, с их исходной ролью. Для byte-identical исходной поставки действует карта F. Для обновлённой совместимой поставки нужны old→new disposition и новый manifest; старые несовместимые файлы не исполняются рядом с новыми. Исторические файлы можно восстановить из локального tracked snapshot `eb3fa3d`, без обращения к GitHub.

### E.2. Восемь dist assets и PHP metadata

| Файл | Роль | Обязательная проверка |
| --- | --- | --- |
| `dist/flux.js` | основной debug runtime, включая Free и Pro primitives | debug endpoint, старые Free controls + новые Pro |
| `dist/flux.min.js` | основной production runtime | min endpoint, один script, manifest cache key |
| `dist/flux.module.js` | поставляемая альтернативная module-сборка | сохранность; не подключать одновременно с main |
| `dist/editor.js` | debug editor | lazy mount, navigation, повторное открытие |
| `dist/editor.min.js` | production editor | загрузка только при editor, отсутствие дубликатов |
| `dist/editor.module.js` | альтернативная module-сборка editor | сохранность; не считать набором исходных ES modules |
| `dist/editor.css` | оформление editor | 200 CSS MIME, toolbar/content в темах |
| `dist/manifest.json` | version tokens трёх семейств assets | ключи `/flux.js`, `/editor.js`, `/editor.css` соответствуют принятой поставке |
| `src/FluxPro.php` | facade декларация | boot/autoload/alias contract выбранной пары |
| `src/FluxProManager.php` | контейнерный manager | singleton и реальный accessor |
| `src/FluxProServiceProvider.php` | registration/discovery/template path | одна регистрация; корректный namespace resolver |
| `src/helpers.php` | autoload files, сейчас только namespace | файл остаётся доступен после install |
| `composer.json` | identity, dependencies, autoload/discovery | version provenance и реальные constraints |
| `LICENSE.md` | upstream notice/условия поставки | сохранить неизменным; root MIT не заменяет его |

В нынешнем архиве **нет** package.json, исходного дерева JavaScript, npm lock, source maps, workbench и воспроизводимого upstream build pipeline. Debug и module JS — готовые bundled artifacts. Прямой просмотр показал отсутствие внешних import/fetch/XHR в двух основных читаемых bundles; это статическое наблюдение, а не полный сетевой аудит браузера.

Внутри main bundle встречаются Floating UI и popover polyfill; editor содержит Tiptap/ProseMirror/linkifyjs и другие встроенные библиотеки. Их точные версии из этого набора не установлены. Поэтому перенос даёт автономное хранение и использование поставки, **не доказывает возможность пересобрать её исходный JavaScript с нуля**. Не реконструировать версии зависимостей по догадке и не добавлять второй Tiptap в приложение. При необходимости менять runtime сначала получить полный официальный source/build комплект либо выбрать новый готовый release. Root npm audit этих embedded зависимостей не охватывает.

Pro templates зависят от Free `Flux::classes`, `attributesAfter`, `componentExists`, `flux:with-field`, `delegate-component`, icons, button, input, dropdown, tooltip и compiler macros. В 116 шаблонах найден `@blaze`, в 80 — `@php`. Установленный Free регистрирует fallback Blaze directives; наличие директивы не требует автоматически добавлять отдельный Blaze пакет. Эти зависимости объясняют, почему нельзя переместить только Pro Blade под новое имя и удалить Free.

### E.3. Composer: предлагаемый фрагмент конфигурации

Следующий JSON — **часть будущего root composer.json**, не выполненная правка и не полная команда установки:

~~~json
{
  "repositories": [
    {
      "type": "path",
      "url": "packages/livewire/flux-pro",
      "canonical": true,
      "only": ["livewire/flux-pro"],
      "options": {
        "symlink": false,
        "reference": "config"
      }
    }
  ]
}
~~~

Перед resolution добавить root require и, если версия не содержится в поставке, `options.versions["livewire/flux-pro"]` с **подтверждённой** stable версией. В примере намеренно не выдуман её номер. Существующие repositories объединяются осознанно; явный canonical path предотвращает случайный выбор другого источника этого имени. `reference: config` фиксирует конфигурацию, но не содержимое всех assets: полноту обеспечивает отдельный SHA-256 manifest. [Composer path repositories](https://getcomposer.org/doc/05-repositories.md#path), [Repository priorities](https://getcomposer.org/doc/articles/repository-priorities.md).

При mirror-установке код редактируется в `packages/`, затем установленная копия целенаправленно обновляется и сравнивается перед сборкой. `composer reinstall livewire/flux-pro` — допустимый инструмент восстановления копии при уже согласованном lock; перед выполнением подтвердить локальный path target и изолировать install scripts. Команда не решает исходный version conflict. [Composer reinstall](https://getcomposer.org/doc/03-cli.md#reinstall).

**Provenance без секретов:** package name; подтверждённая версия либо честный unknown до P0; требование к Free; hash composer/лицензии и общего manifest; источник разрешённого локального артефакта без signed/private URL; дата принятия; базовый локальный commit; список approved patches; old→new файл dispositions; final installed digest. Не включать содержимое auth.json, environment, bearer tokens или пути к личным данным.

### E.4. Что не нужно объединять

- Не заменять `livewire/flux` на выдуманный combined package и не менять `Flux::pro()`: реальная Free/Pro Composer identity сохраняет compiler, CSS и asset routing.
- Не создавать global wrapper-классы для каждой Pro кнопки/вкладки; использовать публичные Flux primitives непосредственно внутри существующих предметных композиций.
- Не дублировать vendor под public assets вручную: текущий AssetManager уже обслуживает нужные endpoints. Webroot остаётся `public`; PHP, metadata и весь `packages/` не публикуются.
- Не включать upstream phrase translations в semantic-only каталоги автоматически. Продуктовые строки и узкие адаптации должны оставаться отслеживаемыми scanner.
- Не переносить dev-only Volt/Testbench или package serve scripts в root. Их наличие в исходном composer не меняет class-based Blade/Livewire baseline приложения.
- Не обещать полностью offline установку всех 178 зависимостей из одного Pro path. Требуется отдельный dependency artifact/cache drill.

### E.5. Где меняются правила и инструменты

| Существующий файл | Будущее изменение | Сохраняемая граница |
| --- | --- | --- |
| `pint.json` | P1: исключить исходный upstream `flux-pro`; P14: добавить ровно `packages/livewire/flux-pro`; P17: убрать старое исключение | форматировать first-party adapters, не скрывать их внутри exclude |
| `phpstan.neon` | оставить app/bootstrap/database/routes scope; проверить автозагрузку Pro | application errors не подавляются общей baseline |
| `phpunit.xml` | coverage приложения остаётся application-only | порог не ниже 90%; vendor не увеличивает denominator |
| `tests/Feature/FrontendStyleArchitectureTest.php` | Pro source, current hashes, package boundary, narrow overrides | запрет generic wrappers и first-party PHP-in-Blade сохраняется |
| `tests/Feature/ProjectCleanupConsistencyTest.php` | сверить scope после появления packages | vendor idioms не разрешаются всему resources/views |
| `app/Console/Commands/ScanTranslationsCommand.php` | не включать весь upstream в поиск semantic keys; проверить новые first-party вызовы | exact EN/LT/RU parity и отсутствие unused keys |
| `.gitignore` | canonical package/manifest отслеживаются; vendor/build/auth остаются generated/private | не добавлять исключение, скрывающее исходники пакета |
| `docs/deployment.md` | packages до install; install до CSS build; согласованный artifact/rollback | shared SQLite/media не заменяются release-архивом |
| `AGENTS.md`, `.ai/rules/flux.md`, `.ai/rules/ui.md` | после внедрения уточнить Free+Pro и upstream path | не объявлять весь upstream first-party и не ослаблять архитектуру |

При анализе в `docs/deployment.md` была ссылка на отсутствующий `DEPLOY_SHARED_HOSTING.md`. В source-preparation checkpoint она исправлена на существующий `operations.md`; deployment остаётся каноническим документом.

## Приложение F. Манифест переноса всех содержательных файлов

Карта ниже относится к исходному snapshot. Для **каждой** строки: источник = `flux-pro/<relative path>`, цель = `packages/livewire/flux-pro/<relative path>`. Первичная копия сохраняет bytes/hash; если P0 принимает другой release, provenance отдельно объясняет изменение/переименование/замену этой строки. Текущие hashes не являются утверждением о совместимости.

139 файлов, 3 291 708 bytes. Общий digest: `b64a3c813819e85a414a010f68bb13d23ed7dcda76668fb5e52cf0388330ad9f`.

Алгоритм исходного общего digest: отсортировать relative paths лексикографически **по компонентам пути** (как Python `sorted(Path)`), для каждой строки соединить `path + TAB + decimal_bytes + TAB + sha256 + LF`, затем SHA-256 UTF-8 результата. Для тех же 139 файлов стандартная побайтовая сортировка полного относительного пути даёт `da0fcdf6baf21b0f747cae8fa804cfe45bcc7e1ac9e6bb101c9448695b5790c5`; именно этот порядок используется в новом `.sha256` и canonical inventory. Оба формата записаны в provenance. Это content inventory, не Git tree hash и не Composer lock reference.

**Исключённые метаданные Finder:** `flux-pro/.DS_Store`, `flux-pro/stubs/.DS_Store`, `flux-pro/stubs/resources/.DS_Store`, `flux-pro/stubs/resources/views/.DS_Store`; каждый 6 148 bytes. Их не переносить как код. Проверить этот список заново в P17.

| Relative path: source → target | Bytes | SHA-256 source |
| --- | ---: | --- |
| `LICENSE.md` | 2253 | `db157f8fe2a5f9dc30ce4104b1caffe619920c4a81038a585d1e1ff0df648870` |
| `composer.json` | 1559 | `c07f944664216e94e0eb7ac1d11f69330c188b51792e01445ce0446934e09b45` |
| `dist/editor.css` | 3956 | `a89988e3b53ed0e615378a7e35381921acd03c64ba10bcf0a64893e08f6e40fc` |
| `dist/editor.js` | 739849 | `1fab1255e8aa6916fd8bda351c56af4b54b8e068a9bc8ef174bf9f94e27df9fa` |
| `dist/editor.min.js` | 332263 | `9481441fa814cf2b25722217258b7f27ac43f9b23ba3fbe528ed8ea09e675575` |
| `dist/editor.module.js` | 698834 | `16c6c21060354eaf3f60edafdae70d6860cab84690262bf2c32276ca234f41ea` |
| `dist/flux.js` | 544207 | `17f622d575f62c94dfdbd7f20a7be2d7c1fe608b22315d48a825d2a03ffe7b1e` |
| `dist/flux.min.js` | 270454 | `2508851ceedcc0ed073816dd6a08fa6de9357ba84c3129bc5548d744136c67aa` |
| `dist/flux.module.js` | 515888 | `ecbcda8c721770df1a75d3d0b916dfd770d0dc42740d8f7724e6af6c6cfc4553` |
| `dist/manifest.json` | 85 | `6da15c5506cfef16728eead298622780e4e565a338e3bdc73c1d3ea4cb95d34c` |
| `src/FluxPro.php` | 240 | `a5b78a89573a9a5468124e70e73968ae60bd58626941cecb7f92749322046fe3` |
| `src/FluxProManager.php` | 59 | `8ead5ece561ecd62d50f40735aed6c74b81b03fb67bd86b4cb4afee885fa888c` |
| `src/FluxProServiceProvider.php` | 708 | `e61b7889e1c4bcb9416da23de17a1a4da7cf31056c0228fc24cf4c5ce10ff053` |
| `src/helpers.php` | 26 | `92bc0cad8130840365a7f1d279393b762d8847fbf1289b7f4160ee0805cbbc96` |
| `stubs/resources/views/flux/accordion/content.blade.php` | 446 | `58f7191c76cdb95b86d3a357de101cd7223cfce454c2d8d1cbbce485c9c72e81` |
| `stubs/resources/views/flux/accordion/heading.blade.php` | 1293 | `0b23c9f1d3c59c6b78c1a7454448ff97159f44c853409e509bde570163ddb484` |
| `stubs/resources/views/flux/accordion/icon.blade.php` | 453 | `f82817e1cc07d121ac3b0c4d4a5f35a70766ed2e97574388a6849cce11a168c0` |
| `stubs/resources/views/flux/accordion/index.blade.php` | 179 | `8bcc97eb34edf72a98a66d06b009169faef24f6f6508d93e07ace3410a84deb0` |
| `stubs/resources/views/flux/accordion/item.blade.php` | 1260 | `0f6c2555f5ba2e2b89977e757836dd8ad9a4add00cc20eaa125f430edc024af4` |
| `stubs/resources/views/flux/autocomplete/index.blade.php` | 462 | `cfd84443afe09a59490162f1df0eee36c7e47968ac347d66a754ddad0776ddbf` |
| `stubs/resources/views/flux/autocomplete/item.blade.php` | 551 | `f988276048b560e33f86b77b5cc37857994b41fe0b64f54033eafe4a3bcfdaa5` |
| `stubs/resources/views/flux/autocomplete/items.blade.php` | 602 | `6d9b5ccf6ca817acea154dfd6c224000a674db109c381623c730000e949187a2` |
| `stubs/resources/views/flux/calendar/index.blade.php` | 20153 | `2dfcc13e6fd0cbda91b278741f84ce42795f14ac77733855c3455d798ba1184e` |
| `stubs/resources/views/flux/chart/area.blade.php` | 272 | `dce7620439f9fcab50e4bb93fcaecd4c0a762f2e91b87de27fc2d4e9587c022f` |
| `stubs/resources/views/flux/chart/axis/grid.blade.php` | 638 | `9898da3c5c66064135f49d009f243d94c2c2c88489dfeab7aece14165916b8c2` |
| `stubs/resources/views/flux/chart/axis/index.blade.php` | 580 | `17789342a47e220104f1e53e41610b0721f87f354409253a5a13a2b3faa5fa4b` |
| `stubs/resources/views/flux/chart/axis/line.blade.php` | 745 | `ba32c3e019efeeb00ec3cc99a24fa6a410157d02c2574b006ec4bd27a955ec97` |
| `stubs/resources/views/flux/chart/axis/mark.blade.php` | 1030 | `6e6e2d8ee156a8167da1e39ca09729028c70a4415a72365ee63f940474aadc18` |
| `stubs/resources/views/flux/chart/axis/tick.blade.php` | 1342 | `b4197859d4a21d318d0841393b64cc7b84d5ac2e5f617ae8226615cb00baed70` |
| `stubs/resources/views/flux/chart/bar.blade.php` | 446 | `285c0d13aecc154babf1ee4f080176519d20f36357d45a4075dba04de72995a2` |
| `stubs/resources/views/flux/chart/cursor.blade.php` | 320 | `2fddae1ca0f5c108385ce78b4f1eb0b54439c0198ae5b65de813755e912370b4` |
| `stubs/resources/views/flux/chart/group.blade.php` | 69 | `6f3b983d39aff617b504fe38f9d1504b3a9fee0cb67495290170bfb2cb172aad` |
| `stubs/resources/views/flux/chart/index.blade.php` | 403 | `b703e60267e999a9c6538a5bd85cc67c7f4ad93b78e49af9a6c5798ed2c71b95` |
| `stubs/resources/views/flux/chart/legend/index.blade.php` | 458 | `9f9481938ea1468f4771197e6c01bb2a211c46494f8a549cabfe2bb58fd1f55a` |
| `stubs/resources/views/flux/chart/legend/indicator.blade.php` | 82 | `da6edc94763917dc9024fc9ee33bbfc5a7fbf05b962b777c215b054bf3398552` |
| `stubs/resources/views/flux/chart/line.blade.php` | 421 | `98c2279004b3a07f4c29c0b0a49fe4baef6a52969b55fdd434d34f03f412deaa` |
| `stubs/resources/views/flux/chart/point.blade.php` | 397 | `b649cdeaeef439560ed197ab431cc2b8ebe726c8336f4f45c03a78840b74e45d` |
| `stubs/resources/views/flux/chart/stack.blade.php` | 69 | `67b98c0e4a16e3a3b106c0a7c97aff870bb066e9f5dcf7b2531d3f2390b332fb` |
| `stubs/resources/views/flux/chart/summary/index.blade.php` | 117 | `7e3e5e90ad56e49ca07e7f59ae6c9b513b84a0062847a9b9b1b80e0bc9824bdc` |
| `stubs/resources/views/flux/chart/summary/value.blade.php` | 381 | `6f2db55b972376fd693e0895fd2c3576695e0f86a5c5e3db35f3ef92a078243c` |
| `stubs/resources/views/flux/chart/svg.blade.php` | 274 | `f14466a01bc9e976aef3cef0b00a5a0620e7a3cfbc421a7351ccbd4f47cdd8ed` |
| `stubs/resources/views/flux/chart/tooltip/heading.blade.php` | 522 | `0a325fb6a8c5518d574b8c62ae4a6bb97d0ce03ed8bf89af56bb99be1052c123` |
| `stubs/resources/views/flux/chart/tooltip/index.blade.php` | 489 | `82f9e94e06fdcbf9d2f8936cc013c8909b5721135872cca3c05b83878c18633f` |
| `stubs/resources/views/flux/chart/tooltip/value.blade.php` | 757 | `bcc2cbd6ccfcf75cc0f5e4db4f450e227ee8b48b73aa704e310a4341ec512e40` |
| `stubs/resources/views/flux/chart/value.blade.php` | 307 | `dccdead589f2983da4c6a56a992ee465e34beedb8be316d1eafc13d0b99f40cf` |
| `stubs/resources/views/flux/chart/viewport.blade.php` | 97 | `219462edb1b86664415de4cfd23c9bb12b7de0bdee3112f273d27ed7faf3066e` |
| `stubs/resources/views/flux/chart/zero-line.blade.php` | 394 | `bdc8ab860feb1b73f9a6095e66c0fbef290388f88be6536cc02e8bfe8431cd75` |
| `stubs/resources/views/flux/command/empty.blade.php` | 265 | `4bb520ac4b4ad9ddca138597c6cf176a0896166575fa65935bc1d9e887e595a5` |
| `stubs/resources/views/flux/command/index.blade.php` | 363 | `ee9ab1312cbf3a1182235586be4e4b16df0371fcb2e7031f652945aa141ce00e` |
| `stubs/resources/views/flux/command/input.blade.php` | 2112 | `1ca0afc3591bda30df5da5d863b7d348eeeb47d78927c286ad96a1408bd9da49` |
| `stubs/resources/views/flux/command/item.blade.php` | 1298 | `882b58bf6322369f50e3c3d38bc5995020ef5906d8a9ff0a3d4249dff2e62e41` |
| `stubs/resources/views/flux/command/items.blade.php` | 340 | `a69b74ec53d1e841c0c010df7852ec22671cdffd15679f06281fb8501f98e39a` |
| `stubs/resources/views/flux/composer/index.blade.php` | 3451 | `e53bbc383450626f1b0246eed3aec56b595efb28f391d8a65875c8bcf4e01e5b` |
| `stubs/resources/views/flux/context.blade.php` | 557 | `1936322cc7416dc1f01e3bf1805688d28c554cf27ceb040fa8a0e2772b64b139` |
| `stubs/resources/views/flux/date-picker/button.blade.php` | 2385 | `09e9095b43a6d6dbb8e1b547ff17354db1c6dbd432b6fa3bd263b98d49256cf9` |
| `stubs/resources/views/flux/date-picker/index.blade.php` | 25935 | `f7511e8d501068a5390d87acb09a86597805049de13a7c73b50486f6521f6dcf` |
| `stubs/resources/views/flux/date-picker/input.blade.php` | 1660 | `efe7bb9c29433b38e5302f4708ccb4760de93e65176bb58114b7f21695de652c` |
| `stubs/resources/views/flux/date-picker/selected.blade.php` | 656 | `9d293a597e06d78e65460c3de0ffb464f518d4d43d4772bbf4af806d87ab6af5` |
| `stubs/resources/views/flux/editor/align.blade.php` | 3060 | `1c28832988cd38684749159b1b8719e3d56a5ac1191af2b5057c21a0e63e4377` |
| `stubs/resources/views/flux/editor/blockquote.blade.php` | 829 | `d5e9d5e7a4e98c94399aee8a2a75559b18ef876ed3bcb4a719a6dc5f1ee310f8` |
| `stubs/resources/views/flux/editor/bold.blade.php` | 569 | `d7af7c1cbcf2120c2079acb84b9f61be261defd8518656e5bf33a9a98327c061` |
| `stubs/resources/views/flux/editor/bullet.blade.php` | 887 | `84be3eab3fa0813a66e62a3ed907377b77564b2387dd58697debd5ef4302c918` |
| `stubs/resources/views/flux/editor/button.blade.php` | 1418 | `7f64385a29f6359c096791e6d364a9085c7d3ca29794de1d51fc4e1d60b616b9` |
| `stubs/resources/views/flux/editor/code.blade.php` | 770 | `e6580014a0ddfc86a347cd02893ceb15082511a355de3572c4cb0527dc554f2e` |
| `stubs/resources/views/flux/editor/content.blade.php` | 101 | `dbc4eb545995ab0d96de0018f84430ac6727159bff199a0328f779bc6b31d73d` |
| `stubs/resources/views/flux/editor/heading.blade.php` | 4678 | `7b27ba7b6bde3d3a6b5a9716bffe158fd2a524e8c24ecfc30c72593375b98c56` |
| `stubs/resources/views/flux/editor/highlight.blade.php` | 546 | `6719f9bcc0d79196b5d1fb55e21cbea995334320832f5e0e0534b92cd9ae71a3` |
| `stubs/resources/views/flux/editor/index.blade.php` | 1942 | `4136e0882799ee699f1d1c3e9d7e0649a9a61433ce4930b86f3d792323f2b54e` |
| `stubs/resources/views/flux/editor/italic.blade.php` | 565 | `7af34625d4d710769b808003d04e287c766e2ff30395ca4cfbf726c0385e0b2a` |
| `stubs/resources/views/flux/editor/link.blade.php` | 5244 | `56e7dd87a4ce234762fde658758f129222a0dd222a34a89641ab2c85a8b866be` |
| `stubs/resources/views/flux/editor/option.blade.php` | 327 | `f1d949a49f87b40ec45c31658407ae2df6bea89ff7b1de594a9aacdd6a590cae` |
| `stubs/resources/views/flux/editor/ordered.blade.php` | 1181 | `747e07152ec287afdfc814494f5c50697c95a61bcecbc2abe63d3407193d35de` |
| `stubs/resources/views/flux/editor/redo.blade.php` | 519 | `4712c9f7b42cd70a41bca72cafc105a92d4e4cfd976b7ad33a0b91584648f54d` |
| `stubs/resources/views/flux/editor/scripts.blade.php` | 45 | `e5ee69cb06814a08cc3038ab487bedc244755d58d145b5331e2365d9f2a006b2` |
| `stubs/resources/views/flux/editor/separator.blade.php` | 127 | `9b6378c4f68d90cfa60501ed4c62b9e5af80bceb4aba2d835ea70a0931888b1c` |
| `stubs/resources/views/flux/editor/spacer.blade.php` | 59 | `7c611d91849c1f1794dccbeda9f72997f064e092f62b3de1ddcad8792a35c182` |
| `stubs/resources/views/flux/editor/strike.blade.php` | 1142 | `f45c8cbaf4125c9bd56ec17a9d598f2c0d039fea525fc58a6226a6b44968a9d5` |
| `stubs/resources/views/flux/editor/styles.blade.php` | 44 | `4533bd6e7d3ec508378fadbcd41ba01fbe4493d42858065b0b12cbb68c88d45f` |
| `stubs/resources/views/flux/editor/subscript.blade.php` | 612 | `43e765949bcf1f320aa428d0f0b4da3fb3c1f5fad925ae3cf20c047b027d6362` |
| `stubs/resources/views/flux/editor/superscript.blade.php` | 633 | `360b739efbb6e2209cdadfcd604b8ef6a618dad23195da274915c6dd657cd102` |
| `stubs/resources/views/flux/editor/toolbar.blade.php` | 1849 | `3e18afdc0064875416a260759f732201029a1d61a845f5bf4575076766439671` |
| `stubs/resources/views/flux/editor/underline.blade.php` | 607 | `6db849eeb8f52a709977e63e54673e2eec073079d42ee745b617f090f442e2a2` |
| `stubs/resources/views/flux/editor/undo.blade.php` | 510 | `32d37d3dde515acd9e3b6fea7a2779182640317dd2ad5d95a47d376b01ed7642` |
| `stubs/resources/views/flux/file-item/index.blade.php` | 2880 | `234f1dae1275feb4387e978944703fb423bbb9875fa8133dd3bdfc8ee9b74fa4` |
| `stubs/resources/views/flux/file-item/remove.blade.php` | 299 | `75fd10f68e8d81c5769f20d070e21e1e5051bb595a40bda9e47138dd588da99a` |
| `stubs/resources/views/flux/file-upload/dropzone/index.blade.php` | 3146 | `141f942494d824be03fc64a9fa5d2fb0ba730743444d71bbfb35eae16f815f77` |
| `stubs/resources/views/flux/file-upload/index.blade.php` | 1071 | `8dbe871da09debee0d7c6078dab36cb3d79280359dfcdcb156aca05d43802b01` |
| `stubs/resources/views/flux/kanban/card.blade.php` | 1583 | `852fa3df0d52948e4a0e74cf127dd667b0484a15a3150056757e36484e2f10d8` |
| `stubs/resources/views/flux/kanban/column/cards.blade.php` | 232 | `0ec3edfd31b09ff1a54f5436a7bba6e541aae1a3e9ead8c541fe3621b678ce6e` |
| `stubs/resources/views/flux/kanban/column/footer.blade.php` | 224 | `99df6464323f4f52a38e4163ff5e25de9270c67727ae6bff750dc36977f991f0` |
| `stubs/resources/views/flux/kanban/column/header.blade.php` | 1166 | `654d9c6cfff1a5be5c455c95361b4a8a31b8e2ff9f391feb07dfae89e2adf2a9` |
| `stubs/resources/views/flux/kanban/column/index.blade.php` | 303 | `c610a1621e9d190e8862720a25e36c289d78729742697fbb98d5777d962468d1` |
| `stubs/resources/views/flux/kanban/index.blade.php` | 105 | `856c8c569c40eae4a5a59914f9e42af6b7f06922ee034746435253cccf314378` |
| `stubs/resources/views/flux/pillbox/empty.blade.php` | 168 | `cca15cd7736f329539f8e724719acc6d4e288d0623b48e8975adc83067520f18` |
| `stubs/resources/views/flux/pillbox/index.blade.php` | 680 | `d8ce9a59a8542fea0ffd1fc39843365437682655f2cca3d1d38e1ea67501b50f` |
| `stubs/resources/views/flux/pillbox/indicator.blade.php` | 124 | `438b8c281d5dd431702c26cc0b8d621e3bb3fa397155a247a781f42c08b7e2d9` |
| `stubs/resources/views/flux/pillbox/input.blade.php` | 850 | `0f8e3e88a707eaf7e566b7faa7f743a9bc2a9506f4ff6d91cbc8000068309101` |
| `stubs/resources/views/flux/pillbox/option/create.blade.php` | 1118 | `909ed74a5e251056384633864e86d71ed268eaf06e703b101912da78028073f9` |
| `stubs/resources/views/flux/pillbox/option/empty.blade.php` | 427 | `7c78d0daec6823864fb4f5bf5e90ed83fd1f385d04dbf06069c5b7e1f1a286ed` |
| `stubs/resources/views/flux/pillbox/option.blade.php` | 1492 | `0401747809a961c13174a54f2020860a995bdd87ec72d256e0bdab873736c0ef` |
| `stubs/resources/views/flux/pillbox/options.blade.php` | 1963 | `426df1922c05453a64738940164f4f2a98a83bbc300af6c0d229fcd6eed8a865` |
| `stubs/resources/views/flux/pillbox/search.blade.php` | 3349 | `9382339958a1fc68129c66967fcd95d76e08915b6061329f9912b7b2b24ed215` |
| `stubs/resources/views/flux/pillbox/selected.blade.php` | 2316 | `decf0874aa39df28473f5173cc306161d19bc3b8e158b7bb8105490a43bdc494` |
| `stubs/resources/views/flux/pillbox/trigger.blade.php` | 2990 | `1997d34472fad579f7667ef0d98a8cb8d9683eb93b23a91c780d70b24d80d327` |
| `stubs/resources/views/flux/pillbox/variants/combobox.blade.php` | 2260 | `cb81737fd464aed4eaee5dbc4ad991b1bbeae3755736b848d57387c676fd9dd2` |
| `stubs/resources/views/flux/pillbox/variants/default.blade.php` | 1484 | `494624f02128d66c58f058599e4a070dc0d3349aa3232992eb1d1fc80c07b608` |
| `stubs/resources/views/flux/popover/index.blade.php` | 653 | `abab6be8c9afe030832bd2a6ef721a2498ce15dbe1f24b5c1c08afd8e30eb071` |
| `stubs/resources/views/flux/select/button.blade.php` | 2271 | `2bba0426b43e432b692bb2b9558eca45ec8e62c9cfd7adb6fe31bc231463b419` |
| `stubs/resources/views/flux/select/empty.blade.php` | 160 | `d77246da4283c4ea8a5ee416be46aa7d8156fedaa60785239693fffa9b8ba41c` |
| `stubs/resources/views/flux/select/indicator/index.blade.php` | 474 | `b42831ede623b2ddd89f91e9b06d64d99728d1c311ab0584a28b56fcf7b6569c` |
| `stubs/resources/views/flux/select/indicator/variants/check.blade.php` | 124 | `438b8c281d5dd431702c26cc0b8d621e3bb3fa397155a247a781f42c08b7e2d9` |
| `stubs/resources/views/flux/select/indicator/variants/checkbox.blade.php` | 1179 | `20c31bba1b8798d376070a41dc6b8376a3942f9a2b6451b7fb64dd2101fa001d` |
| `stubs/resources/views/flux/select/indicator/variants/radio.blade.php` | 1153 | `05a5815aa6c24f630e242a6b7cb6607edd53429aa5df7d8052ad572663d5c7ba` |
| `stubs/resources/views/flux/select/input.blade.php` | 1522 | `cd416c3eb9d314619c0d93d03ae9e5f1e60089f0542a2ecb906897b115dc58da` |
| `stubs/resources/views/flux/select/option/create.blade.php` | 1231 | `65526106af29078ae8164fa5ede6a054c41cf0200038f40a634fce826c3386b0` |
| `stubs/resources/views/flux/select/option/empty.blade.php` | 428 | `d8b757a8c9b8d2c2937ed0dd84ab8686596afa2189dd7f78b96a2a29329a1944` |
| `stubs/resources/views/flux/select/option/variants/custom.blade.php` | 1556 | `c8064af02f39b0dc0b5b1296bace517956cbea6b4fabfe038b289b4a5dac6d95` |
| `stubs/resources/views/flux/select/options.blade.php` | 1747 | `2089fa8d718bdd9f122cca8a1bf9277f28d512067f31d266eb663e6c8e2fbb1f` |
| `stubs/resources/views/flux/select/search.blade.php` | 3249 | `124645a118764953cfbf763570cb3a6ffd95fdff48cf73266171ae986e8eca5f` |
| `stubs/resources/views/flux/select/selected.blade.php` | 789 | `2344e9a85d70df81f5bed2ba606421ea82f8bc96788675d36515e171625c098a` |
| `stubs/resources/views/flux/select/variants/combobox.blade.php` | 1671 | `5bd5d1399aad302f686714f8e5a0ee26a4dc6372a9c508eb1816150e88bc7cd5` |
| `stubs/resources/views/flux/select/variants/custom.blade.php` | 785 | `068061877fbd289032d02fad1ca3bbe826fca46fdfc702aa347e41034409932c` |
| `stubs/resources/views/flux/select/variants/listbox.blade.php` | 1441 | `00445b42426786528186d71fce793ab6662a90be2e6eb3c078859c7a06aac3f6` |
| `stubs/resources/views/flux/slider/index.blade.php` | 2805 | `9ff52ab941779f4740969613c7420391282fc1076cab680dbe2678e979cfce85` |
| `stubs/resources/views/flux/slider/tick.blade.php` | 797 | `bceb341307f31f5e67bb715363e8abe5eb890ca77a1263f51b474c0ba4f70bb2` |
| `stubs/resources/views/flux/tab/group.blade.php` | 121 | `b268a88d1e1d744a055bddaa1dfffd5abafdca8ddcfbc622a843f89e606213fc` |
| `stubs/resources/views/flux/tab/index.blade.php` | 4088 | `3fcbad8e73d21d5387f2bc6ea7bca675533b93904949a88146489081da1d955f` |
| `stubs/resources/views/flux/tab/panel.blade.php` | 423 | `eb019f863c07b8ac48ecb140fa82762f9993944d9aadf8169deab33bbc1714fd` |
| `stubs/resources/views/flux/tabs.blade.php` | 2204 | `3dc0bac7cc97c5827b63c24f222ae69334b10aef9f2a90ec1d804cf9ff84a8c3` |
| `stubs/resources/views/flux/time-picker/button.blade.php` | 2355 | `000557e1666479a431f11e4b9fe329bce626d9bf326d78eeaa7bcefef3baf7b5` |
| `stubs/resources/views/flux/time-picker/index.blade.php` | 3979 | `353746a83761fee457ad288648ef8e28b8504fec586cf1f9bc26ae7583e39644` |
| `stubs/resources/views/flux/time-picker/input.blade.php` | 4212 | `defd2e1527a6dbcfb058401c9d6d535a9485e1df9634ff9fd5c851dc6b6314d5` |
| `stubs/resources/views/flux/time-picker/selected.blade.php` | 673 | `645f043d135dfc345beea78f5c28b3f245e414f2112ba1c9dd7171f128101eac` |
| `stubs/resources/views/flux/timeline/block.blade.php` | 143 | `e683605869ce39b2a0d24139eeae4c66fa91c8732a679f52c52c01e5c1a9a81f` |
| `stubs/resources/views/flux/timeline/content.blade.php` | 223 | `d8cb32d4cb1180d189076ae995862df92152c2b68e6e1b5baf5de83fbd8f4c10` |
| `stubs/resources/views/flux/timeline/index.blade.php` | 554 | `11d4134b143f02c4c4d796e732fb05968ef89ae63a4503bbdc6b58b24c243c05` |
| `stubs/resources/views/flux/timeline/indicator.blade.php` | 3327 | `67c44826d6cbd4120dd45263c4d12045b10a7970c76cd6e108ad893baeedd4c3` |
| `stubs/resources/views/flux/timeline/item.blade.php` | 1320 | `92fe025a76fa5403bd7914df38a3bf56be7cbe9f82f9ce11f75e5aadadb10114` |
| `stubs/resources/views/flux/timeline/subgrid.blade.php` | 145 | `8d90fa219de82ffd3beecf2118df693f6d6778506e3a51555324c3aff99352a7` |

## Приложение G. Углубление существующих модулей и удаление дублирования

Это карта ответственности, а не предложение нового универсального UI framework. **Module** здесь — существующий предметный узел; **Interface** — его входы, события и инварианты; **Implementation** — Flux и внутренний код. **Seam** — место замены виджета, **Adapter** нужен только при несовпадении реальных событий/значений. **Depth** оценивается тем, сколько повторяемого поведения скрывает Interface; **Leverage** — пользой нескольким сценариям, **Locality** — сосредоточением исправлений и тестов в одном месте.

### G.1. Пофайловое решение для JavaScript

| Файл / Module | Передать Pro или удалить после эквивалентной проверки | Сохранить / адаптировать | Проверяемый Interface |
| --- | --- | --- | --- |
| `resources/js/menu-translations.js` | ручные arrow-key tabs, roving tabindex, конкурирующее x-show | EN base projection, copy-only-empty, completeness, повторное раскрытие ошибки, scoped subscriptions | locale + form identity + draft + validation reveal |
| `resources/js/menu-image-picker.js` | второй drop/drag handler, ручные locale tabs, визуальный upload progress | batch queue, preview identity, object URLs, pending rollback, metadata dirty/save | batch selected → preview/upload → explicit persistence |
| `resources/js/menu-workspace.js` | визуальное tab/highlight поведение | revision-aware dirty guard, lazy child selection, pending navigation, history | один активный серверный раздел и сохранённый draft |
| `resources/js/staff-workspace.js` | контролы и disclosure внутри редактора | modal/nonmodal transition, offline close/reconcile, discard consent, safe opener restoration | editor session + scope + unsaved state |
| `resources/js/kitchen-delay-timers.js` | только presentation около timer | серверная clock baseline, elapsed/attention/delay, freeze terminal | timer state без дополнительных poll requests |
| `resources/js/waiter-sounds.js` | settings presentation через Popover/Flux controls | AudioContext, gesture unlock, semantic signals, explicit user preference | звук только по существующему разрешённому событию |
| `resources/js/passkeys.js` | нет эквивалента Pro | WebAuthn/Fortify transport целиком | существующий auth contract |
| `resources/js/app.js` | imports действительно удалённых presentation modules | единственная registration point и корректный lifecycle | один runtime и один набор global listeners |
| текущий незавершённый `resources/js/workspace-navigation.js` | существующий search presentation заменить Command после завершения текущей работы | normalized filtering, shortcut guards, cleanup, текущая navigation context | один navigation payload, без перехвата editor/IME |
| clipboard-код в staff, guest-actions и settings/security | три расходящиеся реализации feedback/fallback | контекст reveal, lifetime секретов и native share | truthful copy result без storage/log/global payload |

**Deletion test:** не создавать заново generic UiSelect, когда прямой Flux проще. Удаление предметного multilingual-authoring или gallery Module вернёт copy/validation/receipts логику в несколько форм; такие модули нужно сохранить и углубить, а не растворять по страницам.

Не объединять history-код menu/staff механически. `menu-workspace.js` отменяет часть Navigation API traverse, а staff использует восстановление entry index после popstate: в `docs/DECISIONS.md` зафиксирована причина для WebKit. Общий Seam появится только после общей behavioural specification и одинаково проходящих browser cases, а не из-за похожих названий функций.

### G.2. Gallery upload — Adapter обязателен

**Доказательства:** `flux-pro/dist/flux.js:7683–7705,7722–7745,7793–7806,7823–7827`; `resources/js/menu-image-picker.js:40–62`; Pro receiver в `file-upload/index.blade.php` помечен `wire:ignore`.

Pro останавливает original input change и испускает новый event от `ui-file-upload`; очищает внутренний список при click/dragenter; finish/error/cancel снимает своё disabled-состояние. Текущий код читает `event.target.files`, сам записывает input.files при drop и ведёт накопленные previews. Простая замена внешнего тега не сохраняет этот Interface.

План Adapter:

1. Нативный выбор и drop принадлежат Flux; application Adapter получает **новую партию**, не добавляет повторно всю накопленную очередь.
2. Server temporary upload, browser preview и persisted gallery получают разные stable identities. Индекс массива не служит mutation credential.
3. Cancel chooser/drop без файлов не удаляет предыдущую удачную партию. Повторный выбор того же файла обрабатывается явно.
4. Error одной партии не очищает уже завершённые pending uploads. Неудачная persistence mutation не подменяется upload success.
5. `uploading` Flux и `saving/removing/offline` продукта соединяются в итоговое disabled-состояние. Завершение upload не разрешает запрещённый save/remove.
6. Object URLs освобождаются при удалении preview, успешном commit и destroy; после морфа сохраняется правильная связь preview→file.
7. Максимум 8 проверяется сервером для совокупности existing+pending; client count — подсказка, не гарантия.
8. Private restore multipart остаётся отдельным безопасным upload contract; общий визуальный FileItem не даёт разрешения использовать публичную временную загрузку.

### G.3. Multilingual authoring и фокус

`components/menu/translation-fields.blade.php` остаётся общим Module для всех существующих menu translation forms; Tabs и, где предусмотрено P11, Editor находятся внутри. Это реальная повторная польза без нового UiTabs.

- У Pro `tab/index.blade.php` и `tab/panel.blade.php` автоматически появляется `wire:key=name`. Повторяемым `en/lt/ru` нужны explicit keys с entity/form/locale и отдельным tab/panel suffix; сохранить существующий idPrefix.
- Один владелец active-tab, selected и tabindex. Application только запрашивает нужную locale при ошибке/copy; Pro владеет переключением и keyboard interaction.
- Staff initial-focus сейчас ищет input/select, translation error-focus — invalid/input/textarea. Новый Listbox фокусируется на button, Editor — на contenteditable. Предусмотреть `data-editor-initial-focus` и `data-validation-focus` на настоящем focusable node, без query по случайному первому input внутри search popover.
- Native validity hidden input не должна удерживать submit на невидимой вкладке. Сохранить действующий `novalidate` и server errors/reveal.
- Повторная **идентичная** ошибка обязана снова раскрыть вкладку после ручного ухода пользователя. Значение, ID, caret и input method не теряются при обычном morph.
- Для rich description отдельно определить limit исходного HTML payload и limit plain content; HTML markup не должен незаметно съедать весь прежний лимит текста.
- Metadata alt/caption остаются plain text и сохраняются отдельно от image upload; Editor туда не подключается.

### G.4. Единые данные для навигации, контекстных действий и уведомлений

**Navigation Module:** принять текущий `BuildApplicationNavigationAction` и его подготовленный `navigationItems` (key/label/icon/href/current/group) как источник sidebar/mobile/Command. Вычисление прав выполняется сервером; не выводить скрытые destinations в JSON для последующего клиентского filtering. При смене organization/branch/logout очищать результаты поиска предыдущего контекста.

**Action list Module:** обычное dropdown и Context используют один уже разрешённый presentation payload действий. Browser передаёт стабильный action identifier в явный существующий метод; не делать универсальный вызов произвольного PHP method по имени из клиента. Server повторяет policy и проверки состояния, даже если пункт был разрешён при render.

**Notification Module:** принять текущую параллельную работу `app/Livewire/Notifications/UnreadCount.php`, его view и query service. При заключительном повторном чтении sidebar уже содержит один responsive host, панель — lazy modal/flyout с epoch guard; `PANEL_LIMIT = 20` задаёт последние read+unread уведомления, count считается отдельно, details читаются только при panelOpen. Timeline можно встроить внутрь этой панели; переход к Popover требует отдельной эквивалентности, а не повторного переписывания готового recovery. Сохранить type+branch scope, locked audience fingerprint, private per-request list и повторную policy-проверку destination. Открытие не означает прочтение; read/all-read остаются отдельными Actions. Смена account/permissions очищает прежний payload. Это snapshot незавершённой работы, а не присвоенная плану проверенная реализация.

**Clipboard transport Module:** объединение полезно для staff invitation, guest invite и security copy только при общем маленьком Interface success/failure/fallback. Значение не уходит в storage, telemetry или глобальный toast payload. Guest native share остаётся самостоятельным. Текущий guest fallback должен учитывать return `execCommand` и rejection `writeText`; UI не показывает ложное «скопировано».

### G.5. Предметные композиции, которые нужно сохранить

| Существующий узел | Внутренняя Pro-композиция | Что даёт Depth |
| --- | --- | --- |
| `components/menu/translation-fields` | Tabs, Editor, Popover help | один copy/completeness/error contract для всех сущностей |
| `components/menu/item-label-fields` | Pillbox, searchable options | общие enum labels; authoring «содержит» отличается от guest «исключить» |
| `components/menu/item-images` | FileUpload/FileItem, Slider, Tabs | batch/revision/preview/metadata в одном сценарии |
| `App\View\Components\Ui\ImageUploadInput` | Pro upload shell | MIME/help продолжают поступать из StoreLocalImageAction |
| `components/dashboard/report` | DatePicker/Calendar + Chart + доступная таблица | один committed period и один scoped report payload |
| `components/dashboard/operation-card` | сохранить карточку; help/Context только при нескольких существующих действиях | единая priority/next-action семантика, без дополнительного меню для единственного перехода |
| `components/ui/metric-strip` | существующий semantic dl, Pro только при наличии данных | суммы не превращаются в выдуманную временную серию |
| staff editor composition | Pro Select/Pillbox/Accordion внутри существующего host | одинаковый responsive/offline/consent lifecycle |
| guest dish/detail composition | Composer/Timeline/Tabs по сценарию | сохранение note/modifiers/idempotency и local close |

## Приложение H. Дополнительные места интеграции и взаимодействие функций

Эти уточнения расширяют таблицу 2.5. У каждого пункта есть предметная причина. Несуществующие calendar reservations, AI chat, онлайн-платежи, файлы в комментариях и свободная сортировка заказов не вводятся лишь ради демонстрации виджета.

| Поверхность | Дополнительная интеграция | Данные и ограничения | Этап |
| --- | --- | --- | --- |
| Shared shell | Command, сгруппированные результаты, keyboard hint, empty state | тот же navigationItems и текущий tenant, без отдельного route registry | P7/P15 |
| Notification center | Timeline внутри текущей flyout panel; Popover только с доказанной эквивалентностью | один host/poller, текущий limit20, read+unread, lazy details, audience/destination guards | P12/P15 |
| Branch picker | searchable Listbox с scoped groups, selected text, empty/loading | selected/all-branches draft guard, недоступный выбранный филиал не подменяется первым | P3 |
| Organization/brand/branch selectors | bounded async search/Autocomplete только для текстового поиска | native ID selectors остаются closed allowlist; stale response не пересекает scope | P3 |
| Onboarding summary | Accordion readiness, Popover explanations, Timeline реальных шагов | текущий шаг из persisted graph, expanded invalid section; не менять retry semantics | P4/P8 |
| Branch opening hours | TimePicker, Accordion по дням, Popover timezone help | server HH:mm, overnight/closed/overlap правила существующего Form | P5 |
| Temporary closure / hidden-until | DatePicker + TimePicker с отдельным draft/apply | branch-local timezone, explicit open/closed state и существующая Action | P5 |
| Report period | DatePicker range, inline Calendar на wide viewport, domain presets | один atomic period URL, максимум 31 день, inclusive last7, browser day не authority | P5 |
| Report details | Chart cursor/tooltip/summary/legend и связанная таблица | один payload и currency scope; null/empty/stale/error различаются | P9 |
| Menu workspace | Tabs навигации, Accordion advanced fields | один смонтированный child, URL/back/dirty semantics сохраняются | P4 |
| Menu translation forms | Tabs, localized Editor toolbar, help Popover | EN/LT/RU, base projection, repeated hidden error | P4/P11 |
| Dish label authoring / guest filters | Pillbox/selected chips/clear/empty | общие подписи enum, разная логика contains/excludes | P3 |
| Dish media | FileItem previews, dropzone, metadata Tabs, focal Slider/ticks | limit8, generated storage names, remove receipt, отдельный save metadata | P6 |
| CSV import | FileUpload inline/FileItem, Accordion preview/errors | локальный server validation, existing preview/commit; upload не запускает import автоматически | P6 |
| Catalog bulk quality | Pillbox filters, Popover explanations, Context разрешённых действий | bounded selected IDs, server policy каждого mutation, no hidden bulk apply | P3/P7 |
| Category/item/service-point rows | Context + тот же visible dropdown | keyboard/touch equivalent, destructive confirmation, no bypass dirty editor | P7 |
| Team employee editor | searchable role/branch Select, Pillbox выбранных зон/фильтра, Accordion advanced access | иерархическое дерево не заменять flat picker с потерей контекста; invitation-only identity, stale revision и assignment fingerprint | P3/P4 |
| Team invitation | Timeline реальных lifecycle events, Popover consent help | no credential in data payload; recipient/version/replay остаются на сервере | P8 |
| Guest dish note / waiter draft edit | Composer multiline с явной save action | plain text, maxlength, no accidental parent submit, modifiers и attempt UUID сохраняются | P8 |
| Guest order / waiter detail | Timeline по настоящим статусам и server timestamps | cancelled/rejected/existing order snapshots, не сочинять этапы для отсутствующих событий | P8 |
| Kitchen/bar | Kanban column/header/cards/footer + counts, Context existing action | item transitions, 24-ticket bounded page, ordered queue, served_at-derived completed | P10 |
| Service-point operational details | Accordion/Popover read-only объяснения, Context enabled actions | permanent QR identity; occupied/cancelled history не получает новые переходы | P4/P7 |
| Waiter preference panel | Popover sound preference + Flux controls | existing AudioContext/user gesture; не добавлять звук кухне без requirement | P12/P15 |
| Account/auth/security | Accordion второстепенных инструкций, Tabs только локального presentation, consistent feedback | Fortify feature flags/passkeys/recovery intact; simple native form submit сохраняется | P12 |
| Superadmin/history/recovery | Select filters, Timeline подготовленного audit payload, Accordion details | scoped redaction, bounded pagination, private restore upload/reauth unchanged | P12 |
| Local component reference | все доступные family/variant fixtures, light/dark/locale examples | переиспользовать текущую local reference работу; local-only route и fictitious data | P1/P13/P15 |
| QR print/PDF | общие prepared data и controls страницы настройки | сам PDF остаётся semantic/native static markup, геометрия и readable code не меняются | P12 |

### H.1. Неприметные особенности Pro runtime, которые требуют отдельной проверки

| Находка в локальной поставке | Следствие для реализации | Проверка |
| --- | --- | --- |
| `DateValue.today()` использует browser `new Date()`, flux.js:5953 | built-in today/presets не являются branch-local «сегодня» | browser и branch в разных датах около полуночи/DST; domain presets серверные |
| TimePicker default locale берётся из navigator, flux.js:6727 | html lang сам по себе не доказывает правильный time display | явно передать SupportedLocale/24-hour policy; смена языка не меняет HH:mm |
| Calendar locale/start-day зависят от Intl и attributes, flux.js:8680+ | начало недели и labels нужно контролировать явно | EN/LT/RU, поддержанные браузеры, даты без преобразования в UTC instant |
| Chart date formatting принудительно добавляет UTC, flux.js:12010,13006,13042 | daily bucket labels не должны случайно смещаться или переименовываться | передавать согласованные day keys/labels, browser timezone не меняет отчёт |
| Chart root использует wire:ignore.children | изменение branch/range/locale должно обновлять и data, и labels | проверить API обновления; при необходимости semantic revision key, без remount на каждый poll |
| JS numbers ограничены безопасными целыми | minor-unit sums нельзя безусловно преобразовать в float для графика | guard safe range/явная display scale; canonical totals/table остаются exact |
| Tabs генерируют wire:key из name | en/lt/ru повторяются в нескольких редакторах | explicit entity/form/locale/tab-or-panel keys |
| Composer submit default — cmd-enter, flux.js:9622,9750–9762 | Ctrl/Cmd+Enter может submit enclosing form | проверить exact parent action; note save не подтверждает заказ |
| В проверенном Composer key handler нет isComposing guard; inner textarea stopPropagation | IME и delegated dirty handler требуют адаптации | composing Enter не отправляет; outer event корректно помечает draft |
| FileUpload меняет target change и disabled lifecycle | прежний native picker handler несовместим без Adapter | batch/disabled cases G.2 |
| Main runtime регистрирует custom elements напрямую | два scripts или main+module вызовут повторные registrations/listeners | один script, navigate 5 циклов, console и request counts |
| Editor CSS/JS загружаются через @assets | экран без editor не должен предзагружать тяжёлый editor bundle | первый lazy mount, повторный mount и возврат browser history |
| AssetManager кеширует на год и использует manifest tokens | patch dist без обновления token оставит клиентам старый JS | согласованный artifact; patched asset token меняется, upstream manifest не правится без причины |
| Локальные provider/facade aliases неочевидно согласованы | успешный Composer resolution ещё не доказывает весь boot | alias/container/render тест, не менять upstream по догадке |

**Overlays:** Pro Popover/Listbox внутри native staff dialog проверяются в обоих режимах 63rem/64rem и поверх sticky/mobile actions. Escape сначала закрывает ближайший widget, затем editor согласно существующему контракту; outside-click не теряет dirty draft. Command, Context и Editor link dialog не должны одновременно владеть shortcut/focus. Новая top-layer панель не лечится произвольным увеличением z-index.

**Form transport:** при Native→Listbox/Pillbox учитывать string/array/null/empty semantics, disabled/read-only, hidden submit inputs и duplicate name. Для Livewire и обычного POST отдельно проверить, что сервер получает ровно одно ожидаемое значение и сохраняет исходные invalid values для ошибки. Никакой hidden control не становится доверенным ID.

**Rich content:** fixtures должны покрывать все 25 editor templates в подходящей parent composition, включая code/highlight/align/sub/sup/undo/redo/link. Сохранить только явно разрешённые форматы; link URL schemes валидируются server-side, paste очищается повторно на сервере, sanitizer идемпотентен, raw HTML доступен единственному проверенному renderer. Список безопасных форматирующих возможностей и запрещённых executable features фиксируется в P11, без заявления «любой HTML поддержан».

## Приложение I. Расширенная программа доказательств

### I.1. Три независимых измерения полноты

1. **Distribution:** каждый файл принятого release обязательно присутствует; отдельно old→new mapping объясняет судьбу всех 139 файлов старого snapshot. Пояснение replacement не разрешает пропустить файл новой принятой поставки. Лицензия/metadata/assets/templates проверяются целиком.
2. **Component contract:** каждая family и применимый variant проверены внутри корректной композиции, включая state, accessibility и Livewire transport. Внутренний toolbar/axis/option template не обязан рендериться как самостоятельная страница.
3. **Product use:** каждое применимое семейство работает в настоящем сценарии из матриц 2.3/2.5/H. Протестированный range slider без предметного применения помечается QA-only и не увеличивает показатель продуктового внедрения.

До P0 denominator — 18 family / 125 templates / 139 содержательных файлов исходной поставки. После выбора более нового release переснять все три denominator. Статический счётчик Flux tags сам по себе не доказывает качество или завершение.

### I.2. Дополнительные тестовые обязанности

Названия ниже — **планируемые новые файлы**, их ещё нет. Допустимо объединить близкие обязанности, если диагностика остаётся ясной.

| Планируемый тест / проверка | Положительное доказательство | Негативный case |
| --- | --- | --- |
| `tests/Feature/FluxProPackageContractTest.php` | настоящий package, stable compatible version, provider/helpers/paths, physical mirror | missing source/helper, wrong identity, symlink/absolute root path, duplicate provider |
| `tests/Feature/FluxProDistributionIntegrityTest.php` | manifest path/size/hash, license, dist keys, approved patches | отсутствующий/лишний/изменённый файл; manifest нельзя обновлять автоматически при падении |
| `tests/Feature/FluxProRuntimeAssetsTest.php` | 5 endpoints, debug/production, MIME, content/version tokens, supported caching | HTML/error вместо JS, stale copied runtime, original root unavailable |
| `tests/Browser/FluxProControlsTest.php` | family/variant fixtures EN/LT/RU, focus, theme, keyboard, morph | repeated validation, hidden error, duplicated listeners, uncontrolled submit |
| Clean installation drill | новый release directory, tracked package, допустимые dependency inputs, isolated env | root folder никогда не присутствовал; dependency cache miss даёт точный blocker |
| Release/rollback drill | app+Free+Pro+lock+assets одного snapshot, source/vendor equality | смешанные старые/new dist, stale CSS, доступ к personal path |
| Production exposure smoke | served JS/CSS только предусмотренными routes | package composer/PHP/license source не открыт как web directory |

Source equality тест сравнивает реальные байты/путь, а runtime tests — настоящие ответы и поведение. Не писать фиктивный тест, который только проверяет наличие строки `flux-pro` в composer.json.

### I.3. Совместные browser cases, добавленные при глубоком анализе

- [ ] Два translation editors и image metadata editor на одном экране: независимые tabs, уникальные IDs/keys, повторная одинаковая ошибка открывает правильную locale.
- [ ] Pro Listbox внутри staff editor на 63→64rem и обратно: popup, focus, Escape, nonmodal desktop, offline close и focus restoration.
- [ ] Command/Context при dirty menu/staff editor: action не обходит guard; cancel navigation сохраняет draft и корректный history cursor.
- [ ] Keyboard command не перехватывает contenteditable, IME, repeat, editor Cmd+K и уже открытый dialog.
- [ ] Browser и branch имеют разные local dates: today/yesterday/last7, hidden-until, overnight hours и DST остаются предметно корректными.
- [ ] Upload A успешен, B неудачен/отменён, C выбран повторно; A сохранён, previews stable, object URLs освобождены.
- [ ] Upload finish приходит во время offline/save/remove; UI не разрешает запрещённую мутацию.
- [ ] Один notification host на mobile/desktop, закрытая/открытая панель, visible/hidden document; no double poll, no automatic mark-read, late open response после close не открывает панель; смена account/rights очищает payload, destination повторно авторизуется.
- [ ] Composer внутри guest/waiter forms: Enter, Ctrl/Cmd+Enter, composing Enter, repeated click, server validation; родительский order transition не запускается случайно.
- [ ] Clipboard denied/unavailable: нет ложного success, есть selectable fallback; секрет не попадает в storage/console/toast payload.
- [ ] Calendar/Chart меняют branch/range/locale без старых tooltip labels; суммы в таблице/CSV и chart labels согласованы по валюте.
- [ ] Kanban: forward transition, stale card, другой tenant/department, двойной request и poll во время drag; keyboard/button equivalent работает.
- [ ] Editor: сначала plain page, затем lazy editor через navigate, rich save/reopen, unsafe paste/link, undo/redo, back-forward, пять циклов без duplicate runtime.
- [ ] Старые Free Button/Modal/Sidebar/Toast/OTP/Progress работают на новом общем Pro runtime; это отдельная регрессия, а не побочный эффект проверки Pro.
- [ ] Root folder отсутствует: hard refresh, JS/CSS HTTP responses, production theme, Pro selector, gallery upload и editor boot проходят.

Проверки, затрагивающие запись, выполняются с factories в owned disposable SQLite и изолированном storage. Тестовый компонентный reference закрыт в production и не читает настоящие tenant data. Herd остаётся обслуживающим runtime для локальной UI-инспекции; отдельный dev server не запускается.

### I.4. Последовательность проверки артефакта перед удалением

~~~text
fresh scoped inventory + free disk + no competing writers
  -> accepted package provenance + full hashes
  -> package mirror into clean release from allowed local inputs
  -> dependency/platform/discovery contract
  -> source-vendor hash equality
  -> Vite production build after mirror
  -> isolated config/route/view caches
  -> targeted + complete application/browser/coverage gates
  -> new directory without root flux-pro: install/build/runtime drill
  -> release rollback drill
  -> final source inventory and old-path search
  -> remove only original root directory
  -> repeat affected integrity/assets/build/cache/browser checks
  -> record exact result
~~~

Не запускать `composer setup` как невинную проверку: он включает миграции. Composer post-update hooks также могут менять generated guidance; сначала работать в disposable копии, затем оценивать diff. `COMPOSER_DISABLE_NETWORK=1` может быть дополнительным режимом проверки при достаточном cache, но не заменяет проверку всех запускаемых plugins/scripts на отсутствие запрещённых запросов. Невыполненный cold-install из-за недостающих разрешённых архивов — blocker, а не pass.

### I.5. Rollback и кеши

- Release bundle включает совместимый Free, Pro, installed metadata, app, lock и compiled Vite assets. В source artifact обязательно входит внутренний package; одного git archive без vendor/build недостаточно для запуска.
- Cache directories принадлежат release/drill; не очищать канонический application cache другого процесса ради проверки плана.
- Raw bundle manifest tokens и HTTP Last-Modified/304 должны соответствовать фактическому accepted asset. При approved JS patch обновлять cache identity вместе с байтами, документируя divergence.
- После переключения release проверить warm browser и hard reload: старый открытый Livewire snapshot может содержать предыдущий markup. Обработать штатное обновление страницы, не оставлять смешанные версии runtime.
- Срочный UI rollback сохраняет SQLite/media и additive rich columns; plain projection остаётся совместимой со старым кодом. Down migration не является первым действием rollback.
- Проверенный rollback использует предыдущий согласованный внутренний package artifact. Возврат root `flux-pro/` в runtime path не входит в постоянный процесс.

## Приложение J. Итоговые критерии и порядок обновления кода

### J.1. Изменяемые и создаваемые файлы по назначению

| Назначение | Существующее / планируемое размещение | Когда |
| --- | --- | --- |
| Полная принятая поставка и карта исходного snapshot | новый `packages/livewire/flux-pro/**`, карта F + accepted release additions/replacements | P14 после совместимого P0 |
| Provenance/content inventory | новые `packages/livewire/flux-pro.provenance.json`, `packages/livewire/flux-pro.sha256` | P14, затем каждое обновление |
| Composer integration | root `composer.json`, `composer.lock` | P0/P14 |
| CSS source и theme contracts | `resources/css/app.css`, существующие layout/theme parts | P1/P2 |
| Product compositions / presentation | перечисленные в 2.5/G/H Blade и UI classes | P3–P13/P15 |
| Event/value adaptation | существующие JS Modules, gallery-specific Adapter; общий clipboard только при доказанной пользе | P6/P8/P15 |
| Business additions | existing Forms/Actions/read services; additive rich migration, sanitizer/renderer, истинные chart data | P9/P11, не прятать в vendor |
| Runtime/integrity regression | будущие tests из I.2 + существующие tests из 7.2 | с первым изменением, RED/GREEN |
| Formatting/scanners | `pint.json`, architecture tests, при необходимости точечный scanner scope | P1/P14/P17 |
| Release/update contract | существующие frontend/deployment/testing/decisions/current-version/compliance документы | по мере факта; финал P18 |
| Старый source root | удалить только `flux-pro/**` после проверки | P17 |

**Граница анализа и исполнения:** первоначальный анализ менял только этот план; временный визуальный обзор не является новой canonical spec. Последующий разрешённый checkpoint добавил внутреннюю копию, provenance/checksums, integrity test, узкую защиту Pint и текущую документацию/traceability. Dependencies, runtime, application data, migrations и удаление исходной папки в этом checkpoint не менялись. Параллельные правки других задач сохраняются.

### J.2. Когда можно сказать «всё перенесено и интегрировано»

- [ ] Полный принятый release присутствует в tracked internal package; original139 и additions/replacements учтены без потерянных файлов.
- [ ] Exact version/constraints/provenance подтверждены; установленная пара совместима; штатная Composer identity и license intact.
- [ ] Все применимые family/variant задачи P1–P13 приняты; QA-only варианты и реальные blockers обозначены честно.
- [ ] Убран заменённый first-party widget-код, но сохранены нужные domain state/Actions/transport/recovery.
- [ ] Canonical package, mirrored vendor, build и release manifest согласованы по hashes.
- [ ] Отсутствие старой папки доказано fresh install/runtime/build drill, затем фактическим удалением и повторной проверкой.
- [ ] История Git и release artifacts позволяют восстановить предыдущую поставку локально без доступа к исходному каталогу.
- [ ] Нет новых скрытых online runtime services, лицензирующих заглушек, вторых frameworks или дополнительных постоянных workers.
- [ ] Все результаты тестов связаны с проверенным source snapshot; docs и compliance отражают внедрение, а не намерения.

### J.3. Обновление после завершения миграции

Обычный цикл: **полный официальный compatible release → временная проверка происхождения → сравнение old/new inventory → review минимальных first-party adaptations/patches → обновление canonical package и metadata → mirror → build → regression gates → согласованный artifact**.

Обновления принадлежат конкретной версии, поэтому после upgrade нужно снова проверить changed props, aliases, embedded libraries, labels, CSS sources, current overrides и runtime lifecycle. Если upstream закрыл accessibility/localization gap, удалить corresponding override после положительной регрессии. Если компонент из P13 отсутствует в принятом release, оставить честный статус availability и не выдавать локальную имитацию за Flux Pro.

Лимит объёма работ заранее не срезается: программа охватывает исходную поставку полностью, текущие интерфейсы, необходимую backend-поддержку, перенос, удаление и сопровождение. Порядок остаётся по зависимостям; version/source blocker решается до массовой UI-миграции.
