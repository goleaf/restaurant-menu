# Integrations

The application has no production external HTTP client, online payment provider, webhook receiver, remote object store, email delivery contract, AI service, search service, WebSocket service or analytics SDK. Manual payments record offline cash/card-terminal/other settlement only. This is an intentional current product boundary, not a placeholder.

Local integrations are Laravel Fortify/passkeys/2FA, SQLite, database-backed cache/session/queue, local files, QR rendering, Livewire, Flux UI Free and Vite/Tailwind. Flux Pro is not installed or licensed and its template sources must not be referenced.

A future external integration requires a dedicated client/gateway, configuration through `config()`, bounded connection/total timeouts, explicit status/schema mapping, safe retries and idempotency, sensitive-log redaction, fake-based tests and a no-stray-network test. A server-side user-controlled URL fetch additionally requires scheme/host/IP/redirect/size protections against SSRF.
