<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Demo users and roles

This is the canonical catalogue of fictitious identities exposed by the non-production `/demo-login` role selector. It is not a credential report: reusable passwords, password hashes, tokens and secrets are neither documented nor recoverable from the catalogue.

| Name | Email | System role | Organization | Prepared branch access |
|---|---|---|---|---|
| Demo Superadmin | `superadmin@demo.test` | `superadmin` | System-wide | All branches through the system role |
| Demo Owner | `owner@demo.test` | `owner` | Demo Food Group | All four primary demo branches |
| Demo Director | `director@demo.test` | `director` | Demo Food Group | All four primary demo branches |
| Demo Restaurant Admin | `admin@demo.test` | `restaurant_admin` | Demo Food Group | All four primary demo branches |
| Demo Shift Manager | `manager@demo.test` | `shift_manager` | Demo Food Group | All four primary demo branches |
| Demo Waiter | `waiter@demo.test` | `waiter` | Demo Food Group | Bella Pizza Old Town; Bella Pizza Terrace |
| Demo Head Chef | `chef@demo.test` | `head_chef` | Demo Food Group | Bella Pizza Old Town; Bella Pizza Terrace; Sushi Master Center |
| Demo Cook | `cook@demo.test` | `cook` | Demo Food Group | Bella Pizza Old Town; Sushi Master Center |
| Demo Bartender | `bartender@demo.test` | `bartender` | Demo Food Group | Bella Pizza Terrace; Coffee Bar Small Hall |
| Demo Cashier | `cashier@demo.test` | `cashier` | Demo Food Group | Bella Pizza Old Town; Coffee Bar Small Hall |
| Demo Accountant | `accountant@demo.test` | `accountant` | Demo Food Group | All four primary demo branches |
| Demo Marketer | `marketer@demo.test` | `marketer` | Demo Food Group | All four primary demo branches |

The seeder creates a high-entropy random password only when an identity is first created and preserves the existing hash on repeated runs. Operators and visitors do not receive that value. On the dedicated `ruflo.test` demo, access is performed through the guarded role selector described in [`DEMO_LOGIN.md`](DEMO_LOGIN.md). The selector and demo seeding are denied in production.
