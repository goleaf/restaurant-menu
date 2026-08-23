# Локальные пользователи, пароли и роли

Снимок текущей локальной SQLite-базы проекта `restaurant-menu` на 2026-08-23.

> Конфиденциально: файл находится в приватном каталоге `storage/app/private`, игнорируется Git и не предназначен для production. Хеши паролей не экспортировались. Указанный пароль проверен через `Hash::check()` для каждого из 12 пользователей.

| ID | Имя | Email | Пароль | Системная роль | Организация | Доступные филиалы |
|---:|---|---|---|---|---|---|
| 1 | Demo Superadmin | `superadmin@demo.test` | `DemoPassword2026!` | `superadmin` — суперадминистратор | Системный доступ, без членства в организации | Все филиалы через системную роль |
| 2 | Demo Owner | `owner@demo.test` | `DemoPassword2026!` | `owner` — владелец | Demo Food Group (`active`) | Bella Pizza Old Town; Bella Pizza Terrace; Sushi Master Center; Coffee Bar Small Hall |
| 3 | Demo Director | `director@demo.test` | `DemoPassword2026!` | `director` — директор | Demo Food Group (`active`) | Bella Pizza Old Town; Bella Pizza Terrace; Sushi Master Center; Coffee Bar Small Hall |
| 4 | Demo Restaurant Admin | `admin@demo.test` | `DemoPassword2026!` | `restaurant_admin` — администратор ресторана | Demo Food Group (`active`) | Bella Pizza Old Town; Bella Pizza Terrace; Sushi Master Center; Coffee Bar Small Hall |
| 5 | Demo Shift Manager | `manager@demo.test` | `DemoPassword2026!` | `shift_manager` — менеджер смены | Demo Food Group (`active`) | Bella Pizza Old Town; Bella Pizza Terrace; Sushi Master Center; Coffee Bar Small Hall |
| 6 | Demo Waiter | `waiter@demo.test` | `DemoPassword2026!` | `waiter` — официант | Demo Food Group (`active`) | Bella Pizza Old Town; Bella Pizza Terrace |
| 7 | Demo Head Chef | `chef@demo.test` | `DemoPassword2026!` | `head_chef` — шеф-повар | Demo Food Group (`active`) | Bella Pizza Old Town; Bella Pizza Terrace; Sushi Master Center |
| 8 | Demo Cook | `cook@demo.test` | `DemoPassword2026!` | `cook` — повар | Demo Food Group (`active`) | Bella Pizza Old Town; Sushi Master Center |
| 9 | Demo Bartender | `bartender@demo.test` | `DemoPassword2026!` | `bartender` — бармен | Demo Food Group (`active`) | Bella Pizza Terrace; Coffee Bar Small Hall |
| 10 | Demo Cashier | `cashier@demo.test` | `DemoPassword2026!` | `cashier` — кассир | Demo Food Group (`active`) | Bella Pizza Old Town; Coffee Bar Small Hall |
| 11 | Demo Accountant | `accountant@demo.test` | `DemoPassword2026!` | `accountant` — бухгалтер | Demo Food Group (`active`) | Bella Pizza Old Town; Bella Pizza Terrace; Sushi Master Center; Coffee Bar Small Hall |
| 12 | Demo Marketer | `marketer@demo.test` | `DemoPassword2026!` | `marketer` — маркетолог | Demo Food Group (`active`) | Bella Pizza Old Town; Bella Pizza Terrace; Sushi Master Center; Coffee Bar Small Hall |

## Итог

- Пользователей: 12.
- Уникальных системных ролей: 12.
- Общий пароль всех текущих demo-пользователей: `DemoPassword2026!`.
- Все email подтверждены.
- Все членства организации и назначения филиалов имеют статус `active`.
- В production demo-сеяние и demo-вход запрещены.
