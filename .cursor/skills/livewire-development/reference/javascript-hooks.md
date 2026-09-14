# Livewire 4 JavaScript Integration

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
