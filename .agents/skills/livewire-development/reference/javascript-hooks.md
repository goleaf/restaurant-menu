<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Livewire 4 JavaScript Integration

## Installed 4.4.1 menu integration caveat

Use the global `Livewire.interceptMessage` subscription, filtered by component ID and the intended action, for disposable menu editors. The installed component-scoped message unsubscribe path calls a missing `WeakBag.delete` method. Global unsubscribe uses the supported registry path. The installed success lifecycle exposes `onRender`; do not assume newer documentation's callback names. Keep static `novalidate` on translated forms so post-response morphs cannot block subsequent server validation.

## Interceptor System (v4)

### Intercept Messages

```js
Livewire.interceptMessage(({ message, onFinish, onSuccess, onError }) => {
    onFinish(() => { /* After message processing, or error/cancellation */ });
    onSuccess(({ payload }) => { /* payload.snapshot, payload.effects */ });
    onError(() => { /* Server errors */ });
});
```

### Intercept Requests

```js
Livewire.interceptRequest(({ request, onResponse, onSuccess, onError, onFailure }) => {
    onResponse(({ response }) => { /* When received */ });
    onSuccess(({ response, body, json }) => { /* Success */ });
    onError(({ response, body, preventDefault }) => { /* 4xx/5xx */ });
    onFailure(({ error }) => { /* Network failures */ });
});
```

### Component-Scoped Interceptors

Register against the component's `$wire` inside its supported script context, or pass the `$wire` object into the existing application JavaScript integration. A bare `this` in a normal script is not the component. Avoid logging response payloads or guest credentials.

```js
$wire.$intercept('save', ({ onFinish }) => {
    onFinish(() => { /* Clean up local pending UI */ });
});
```

## Magic Properties

- `$errors` - Access validation errors from JavaScript
- `$intercept` - Component-scoped interceptors

Checked against Livewire 4.4.1's installed `dist/livewire.esm.js` and the [Livewire JavaScript reference](https://livewire.laravel.com/docs/4.x/javascript). Confirm installed hooks before using features shown only in newer documentation.
