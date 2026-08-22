# ADR 0003: Digest-at-rest one-time tokens

- Status: accepted
- Date: 2026-08-22

## Decision

Invitation and future bearer-style one-time credentials are generated with a cryptographically secure random source, shown/sent only in the URL at issuance, and stored only as a deterministic cryptographic digest with purpose, owner, expiry and consumption/revocation state. Consumption is rate-limited and atomic; replay and concurrent double-use produce no second effect.

## Rationale and consequences

A database disclosure must not reveal usable bearer credentials. Deterministic digest lookup avoids plaintext storage while allowing indexed resolution. Existing pending plaintext invitations require a forward-safe migration/compatibility decision; token contents are never logged or serialized.
