<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Current-source research

Source: local main7a5f3cf plus incoming dirty changes, not supplied static SHA. Existing Settings mount calls EnsureBranchSettingsAction (write). Its single BranchSettingsForm passes settings/profile/images to SaveBranchConfigurationAction. UpdateBranchSettingsAction falls back missing service_modes/percentage and writes branches.currency. UpdateBranchAction independently writes both currencies. Existing actions-livewire rule enshrines that broad path and must be superseded.

require_waiter_confirmation_for_orders, order_flow_mode, guest_join_requires_approval and service_modes have no independent runtime switch behavior; mandatory staff confirmation is implemented in ordering Actions. allow_waiter_opened_sessions needs real server admission enforcement. Guest invitation allowance currently only restricts creation; acceptance must recheck. Legacy future modes remain informational.

Public profile: branches public_name/public_description/contact strings, branch→brand→organization logo fallback in BuildGuestEntryContextAction. Generic URL validation too broad. Existing cover lacks actor/version/removal. Shared media utilities protect outer-commit/rollback files, but image processing transaction length requires review.

Cleanup computes inactivity before cancellation; its transaction rechecks orders/drafts/status but misses newer activity/threshold. Existing bounded1000 scan does not report remaining rows. Preview/confirmation absent.

## Parameter map
| Parameter / storage | Section / Form | Rules / Policy / Action | Consumers / effect | Cache / audit / tests |
|---|---|---|---|---|
| branches.public_name,public_description,public_translations | Profile / BranchPublicProfileForm | BranchProfileRules / manageSettings / UpdateBranchPublicProfileAction | QR/pure preview, next render | direct presenter; profile audit; profile/locale tests |
| branches.phone,email,website_url,instagram_url,facebook_url,tiktok_url | Profile / same | nullable strings/RFC email/HTTP(S) / same | guest public display, next render | same |
| branches.logo_path,cover_image_path | separate media | ImageUploadRules / current resource policy / media Actions | next render, fallback provenance | own media fingerprint/receipt, file rollback tests |
| branches.name,address,city,country,timezone | canonical structural editor | RestaurantIdentityForm/BranchProfileRules / update / UpdateBranchAction | structural identity; timezone affects future wall-clock resolution | identity version; existing center tests |
| allow_guest_created_sessions(true),allow_waiter_opened_sessions(true),allow_guest_invite_links(true) | Guests / GuestProcessForm | booleans / manageSettings / group action | next server request; existing visits/orders untouched | settings audit; admission tests |
| require_waiter_confirmation_for_orders(true),guest_join_requires_approval(true),order_flow_mode(waiter_confirmation),service_modes(dine_in) | informational supported/legacy | no independent UI write | staff confirmation fixed; future modes informational | preserve raw values on unrelated writes |
| branches.currency / settings.default_currency(EUR, branch currency on create) | Settlement / SettlementSettingsForm | supported currency + monetary guard / manageSettings or canonical update | future money; no conversion/history rewrite | recheck all monetary blockers; audit/race tests |
| service_charge_enabled(false),service_charge_basis_points(0),tips_enabled(false) | Settlement / same | exact decimal0..100→basis points / same | manual settlement calculation; snapshots retained | payment fingerprint recheck; history tests |
| default_language(en) | Language / LocaleSettingsForm | EN/LT/RU / same | fallback only, explicit preference wins | settings audit; locale tests |
| polling_interval_seconds(1,1..60) | Advanced / AdvancedSettingsForm | numeric integer / same | bounded visible poll cadence only | polling cache; interval tests |
| inactivity_warning_minutes(45,1..1440),pending_session_expire_minutes(30,1..1440) | Advanced / same | numeric integer / same | next explicit cleanup evaluation; no save transition | settings audit; cleanup race tests |

## Sources
https://livewire.laravel.com/docs/4.x/forms ; https://livewire.laravel.com/docs/4.x/url ; https://www.sqlite.org/lang_transaction.html ; https://fluxui.dev/components/file-upload ; https://fluxui.dev/components/modal ; https://tailwindcss.com/docs/compatibility . Installed package source remains API authority. PHP.net archive on2026-09-20 lists8.6Beta3 for testing, RC1 planned24Sep; no production acceptance implied. Boost MCP default launcher errors; explicit php85 boost:execute-tool succeeds.
