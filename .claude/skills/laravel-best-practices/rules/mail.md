<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Mail Best Practices

## Choose Delivery Deliberately

Implementing `ShouldQueue` makes `Mail::send()` queue the mailable too. This project has no required long-running worker: retain configured synchronous mail delivery or use an explicitly optional queue with bounded recovery. Invitation administration must continue to provide its authorized manual-delivery path when mail is unconfigured.

## Use `afterCommit()` on Mailables Inside Transactions

A queued mailable dispatched inside a transaction may process before the commit. Use `$this->afterCommit()` in the constructor.

## Use `assertQueued()` Not `assertSent()` for Queued Mailables

`Mail::assertSent()` only catches synchronous mail. Queued mailables fail `assertSent` with a "Did you mean to use assertQueued()?" hint.

Incorrect: `Mail::assertSent(OrderShipped::class);` when mailable implements `ShouldQueue`.

Correct: `Mail::assertQueued(OrderShipped::class);`

## Use Markdown Mailables for Transactional Emails

Markdown mailables auto-generate both HTML and plain-text versions, use responsive components, and allow global style customization. Generate with `--markdown` flag.

## Separate Content Tests from Sending Tests

Content tests: instantiate the mailable directly, call `assertSeeInHtml()`.
Sending tests: use `Mail::fake()` and `assertSent()`/`assertQueued()`.
Don't mix them — it conflates concerns and makes tests brittle.
