# Organization administration CRUD evidence index

This document is an implementation and test-evidence view of canonical requirement `sys-admin-crud-001`. It does not redefine product requirements. The executable inventory is `Tests\Support\OrganizationCrudMatrix`; focused feature tests remain authoritative for behavior, authorization, validation and tenant isolation.

CRUD uses the domain-safe lifecycle equivalent where physical deletion would violate identity or history.

| # | Resource | Create | Read | Update | Delete or lifecycle equivalent | Required demo state |
|---|---|---|---|---|---|---|
| 1 | Organization | create | bounded accessible list | identity and logo | confirmed soft delete | canonical organization with logo |
| 2 | Brand | create | bounded tenant list | identity and logo | confirmed soft delete | three brands with media coverage |
| 3 | Branch | create | bounded authorized list | identity, locale, currency and state | confirmed soft delete | active and inactive branches |
| 4 | Branch public profile | defaults with branch | current profile | contact, social and media | remove media and clear optional fields | complete profile and local media |
| 5 | Branch settings | ensure singleton | current settings | guest, service and locale rules | reset through validated defaults | complete settings per branch |
| 6 | Opening hours | add intervals | weekly schedule | replace intervals | remove interval or close day | regular, split and closed days |
| 7 | Temporary closure | set | current closure | reason and end time | clear | one bounded closure |
| 8 | Organization staff | add member | searched, paginated members | role and status | suspend/reactivate | all roles and suspended member |
| 9 | Branch staff | assign member | searched, paginated assignments | role, status and waiter areas | suspend/reactivate/detach lifecycle | active and suspended assignments |
| 10 | Invitation | create link/code | searched, paginated history | recipient and expiry immutable | cancel pending | pending, expired and cancelled |
| 11 | Permission override | allow/deny | effective matrix | switch allow/deny | return to role default | one allow and one deny |
| 12 | Area node | create root/child | ordered tree | hierarchy, icon, order and state | confirmed soft delete | nested active/inactive areas |
| 13 | Service point | single/bulk create | searched, filtered, paginated list | identity, location and state | guarded soft delete | multiple types/statuses/QR states |
| 14 | QR identity | generate | show/download/print | reissue | disable/revoke | active and historical identities |
| 15 | Kitchen department | create | ordered list | identity, type, order and state | guarded delete | kitchen, bar and inactive custom department |
| 16 | Menu | create | ordered list | name, status and order | confirmed soft delete | active, draft and archived menus |
| 17 | Menu schedule | create interval | ordered intervals | day and time range | delete interval | weekday and weekend intervals |
| 18 | Menu category | create root/child | ordered tree | base and EN/LT/RU content | confirmed soft delete | localized active/inactive categories |
| 19 | Menu item | create dish | ordered list | content, price, nutrition and department | confirmed soft delete | localized available/unavailable dishes |
| 20 | Menu item images | upload up to eight | primary and ordered gallery | promote primary | remove image and parent cleanup | primary and secondary images |
| 21 | Item availability | with dish | catalog and stop list | available/unavailable | unavailable hides from guest menu | both states |
| 22 | Modifier group | create | ordered groups | required limits and order | delete | required and optional groups |
| 23 | Modifier option | create | nested options | name, price, availability and order | delete | free, surcharge, discount and unavailable |
| 24 | Item-modifier assignment | attach | assigned groups | idempotent reattach | detach | assigned and unassigned dishes |
| 25 | Menu item variant | create | ordered variants | data and EN/LT/RU content | delete with default invariant | default, optional and unavailable variants |
| 26 | Branch subscription context | ensure | access evaluation | status transition | inactive lifecycle state | active canonical subscription |

Operational orders, guests, drafts, tickets, waiter calls, payments, audit logs and notifications remain demo prerequisites because they constrain safe lifecycle operations, but their primary CRUD surfaces are outside `/organizations`.
