# Security policy

## Supported version

Security fixes are applied to the current `main` branch. The repository does not promise security support for historical prompt snapshots or old commits.

## Reporting a vulnerability

Do not open a public issue containing exploit details, credentials, personal data, QR/guest/invitation tokens, or database contents. Report the issue privately to the repository owner with:

- affected commit and route/component;
- reproduction steps using synthetic data;
- expected and observed authorization boundary;
- impact and any known workaround.

Never include production secrets or a copied production SQLite database.

## Implemented boundaries

The authoritative control inventory is [`docs/security.md`](docs/security.md). In brief:

- Fortify owns authentication, password reset, password confirmation, passkeys, and 2FA.
- Protected pages and every mutation require server-side authorization.
- Organization and branch access is tenant-scoped; superadmin bypass is explicit.
- Public QR, guest, and invitation credentials are high-entropy tokens and must not be logged or exported.
- Blade escapes user content; raw output is limited to an audited generated SVG boundary.
- Files use configured local disks and private downloads authorize at request time.
- Production error responses do not expose stack traces or SQL details.
- Composer and npm audits are release gates.

## Secrets

Runtime secrets belong in `.env` and are accessed through configuration. `.env.example` contains names and safe placeholders only. If a secret reaches Git or logs, revoke/rotate it immediately; deleting the visible value is not sufficient.
