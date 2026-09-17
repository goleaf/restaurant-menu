<?php

use App\Actions\Auth\AuthenticateUserAction;
use App\Actions\Auth\CompleteTwoFactorLoginAction;
use App\Actions\Auth\ConfirmPasswordAction;
use App\Actions\Auth\LoginAsDemoRoleAction;
use App\Actions\Auth\LoginAsLocalUserAction;
use App\Actions\Auth\LogoutAction;
use App\Actions\Auth\ResetPasswordAction;
use App\Actions\Auth\SendEmailVerificationAction;
use App\Actions\Backups\PrepareBackupDownloadAction;
use App\Actions\Invitations\SwitchInvitationAccountAction;
use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;

function boundaryAst(string $file): array
{
    return boundarySourceAst(file_get_contents($file));
}

function boundarySourceAst(string $source): array
{
    $nodes = (new ParserFactory)->createForNewestSupportedVersion()->parse($source);
    $traverser = new NodeTraverser(new NameResolver);

    return $traverser->traverse($nodes ?? []);
}

function boundaryFiles(string $directory): array
{
    return iterator_to_array(new RegexIterator(new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/'.$directory, FilesystemIterator::SKIP_DOTS)), '/\.php$/'));
}

test('controllers delegate payload validation and presentation queries', function (): void {
    $finder = new NodeFinder;
    $violations = [];
    foreach (boundaryFiles('app/Http/Controllers') as $file) {
        foreach ($finder->findInstanceOf(boundaryAst((string) $file), Node\Expr::class) as $node) {
            $method = $node instanceof Node\Expr\MethodCall || $node instanceof Node\Expr\StaticCall ? (string) $node->name : '';
            if (in_array($method, ['validate', 'validateWithBag', 'loadMissing', 'query', 'findAcceptableById'], true)
                || ($node instanceof Node\Expr\StaticCall && (string) $node->class === 'Illuminate\\Support\\Facades\\Validator' && $method === 'make')) {
                $violations[] = basename((string) $file).':'.$node->getStartLine().' '.$method;
            }
        }
    }
    expect($violations)->toBe([]);
});

test('domain actions do not resolve actor or ui state through http session', function (): void {
    $finder = new NodeFinder;
    $violations = [];
    // These transport operations own authentication/session transitions or password-confirmed file grants.
    // Ordinary domain Actions still receive explicit actors/data and cannot depend on HTTP state.
    $transportActions = [
        AuthenticateUserAction::class,
        CompleteTwoFactorLoginAction::class,
        ConfirmPasswordAction::class,
        LoginAsDemoRoleAction::class,
        LoginAsLocalUserAction::class,
        LogoutAction::class,
        ResetPasswordAction::class,
        SendEmailVerificationAction::class,
        PrepareBackupDownloadAction::class,
        SwitchInvitationAccountAction::class,
    ];
    $observedTransportActions = [];
    foreach (boundaryFiles('app/Actions') as $file) {
        $nodes = boundaryAst((string) $file);
        $class = $finder->findFirstInstanceOf($nodes, Node\Stmt\Class_::class);
        $name = $class?->namespacedName?->toString();
        $transport = in_array($name, $transportActions, true);
        if ($transport) {
            $observedTransportActions[] = $name;
        }
        foreach ($finder->findInstanceOf($nodes, Node\Expr::class) as $node) {
            if ($node instanceof Node\Expr\FuncCall && $node->name instanceof Node\Name && in_array((string) $node->name, ['request', 'auth', 'session', 'redirect'], true)
                || $node instanceof Node\Expr\StaticCall && $node->class instanceof Node\Name
                    && ((string) $node->class === 'Flux\\Flux' || (! $transport && in_array((string) $node->class, ['Illuminate\\Support\\Facades\\Auth', 'Illuminate\\Support\\Facades\\Session'], true)))) {
                $violations[] = basename((string) $file).':'.$node->getStartLine();
            }
        }
        foreach ($finder->findInstanceOf($nodes, Node\Param::class) as $param) {
            if (! $transport && $param->type instanceof Node\Name && (string) $param->type === 'Illuminate\\Http\\Request') {
                $violations[] = basename((string) $file).':http-request';
            }
        }
    }
    sort($observedTransportActions);
    sort($transportActions);
    expect($observedTransportActions)->toBe($transportActions);
    expect($violations)->toBe([]);
});

test('rules form requests and Livewire forms do not mutate persistence or generate ui side effects', function (): void {
    $violations = [];
    foreach (['app/Http/Requests', 'app/Rules', 'app/Support/Validation', 'app/Livewire/Forms'] as $directory) {
        if (! is_dir(dirname(__DIR__, 2).'/'.$directory)) {
            continue;
        }
        foreach (boundaryFiles($directory) as $file) {
            foreach (boundaryInputViolations(boundaryAst((string) $file)) as $violation) {
                $violations[] = basename((string) $file).':'.$violation;
            }
        }
    }
    expect($violations)->toBe([]);
});

/** @param array<Node> $nodes @return list<string> */
function boundaryInputViolations(array $nodes): array
{
    $violations = [];
    $writes = ['save', 'saveOrFail', 'saveQuietly', 'saveMany', 'delete', 'deleteOrFail', 'forceDelete', 'destroy', 'truncate', 'restore', 'create', 'createMany', 'forceCreate', 'update', 'updateQuietly', 'updateOrCreate', 'firstOrCreate', 'insert', 'insertOrIgnore', 'upsert', 'increment', 'decrement', 'sync', 'syncWithoutDetaching', 'attach', 'detach', 'notify', 'notifyNow', 'redirect', 'toast', 'store', 'storeAs', 'storePublicly', 'put', 'putFile', 'move'];
    $actionClass = static fn (Node $node): bool => $node instanceof Node\Name && str_starts_with($node->toString(), 'App\\Actions\\');
    foreach ((new NodeFinder)->find($nodes, static fn (Node $node): bool => $node instanceof Node\Expr || $node instanceof Node\Param) as $node) {
        if (($node instanceof Node\Expr\MethodCall || $node instanceof Node\Expr\NullsafeMethodCall || $node instanceof Node\Expr\StaticCall)
            && $node->name instanceof Node\Identifier && in_array($node->name->toString(), $writes, true)) {
            $violations[] = $node->getStartLine().':persistent-write';
        }
        if ($node instanceof Node\Expr\StaticCall && $node->class instanceof Node\Name
            && in_array($node->class->toString(), ['Flux\\Flux', 'Illuminate\\Support\\Facades\\DB', 'Illuminate\\Support\\Facades\\Mail', 'Illuminate\\Support\\Facades\\Notification', 'Illuminate\\Support\\Facades\\Bus', 'Illuminate\\Support\\Facades\\Queue'], true)) {
            $violations[] = $node->getStartLine().':side-effect';
        }
        if ($node instanceof Node\Expr\FuncCall && $node->name instanceof Node\Name
            && in_array($node->name->toString(), ['event', 'dispatch', 'dispatch_sync', 'redirect', 'file_put_contents', 'unlink', 'rename'], true)) {
            $violations[] = $node->getStartLine().':side-effect';
        }
        if ($node instanceof Node\Param && $node->type instanceof Node\Name && $actionClass($node->type)
            || $node instanceof Node\Expr\New_ && $actionClass($node->class)
            || $node instanceof Node\Expr\StaticCall && $actionClass($node->class)
                && ! ($node->class->toString() === 'App\\Actions\\Branches\\GetBranchOpeningStatusAction'
                    && $node->name instanceof Node\Identifier && $node->name->toString() === 'dayLabels')) {
            $violations[] = $node->getStartLine().':business-operation';
        }
        if ($node instanceof Node\Expr\FuncCall && $node->name instanceof Node\Name
            && in_array($node->name->toString(), ['app', 'resolve'], true) && isset($node->args[0])) {
            $argument = $node->args[0]->value;
            if ($argument instanceof Node\Expr\ClassConstFetch && $actionClass($argument->class)
                || $argument instanceof Node\Scalar\String_ && str_starts_with(ltrim($argument->value, '\\'), 'App\\Actions\\')) {
                $violations[] = $node->getStartLine().':business-operation';
            }
        }
    }

    return $violations;
}

/** @param array<Node> $nodes @return list<string> */
function boundaryLivewireViolations(array $nodes): array
{
    $violations = [];
    $forbiddenClass = static fn (string $name): bool => str_contains($name, '\\Http\\Controllers\\')
        || in_array($name, [
            'Illuminate\\Contracts\\Http\\Kernel', 'Illuminate\\Foundation\\Http\\Kernel',
            'Symfony\\Component\\HttpKernel\\HttpKernelInterface', 'Symfony\\Component\\HttpKernel\\HttpKernel',
            'Illuminate\\Routing\\Router', 'Illuminate\\Support\\Facades\\Http',
            'Illuminate\\Http\\Client\\Factory', 'Illuminate\\Http\\Client\\PendingRequest',
            'GuzzleHttp\\Client', 'GuzzleHttp\\ClientInterface', 'Psr\\Http\\Client\\ClientInterface',
        ], true);
    foreach ((new NodeFinder)->find($nodes, static fn (Node $node): bool => $node instanceof Node\Name || $node instanceof Node\Expr) as $node) {
        if ($node instanceof Node\Name && $forbiddenClass($node->toString())
            || $node instanceof Node\Scalar\String_ && $forbiddenClass(ltrim($node->value, '\\'))) {
            $violations[] = $node->getStartLine().':http-delegation';
        }
        if (($node instanceof Node\Expr\New_ || $node instanceof Node\Expr\StaticCall || $node instanceof Node\Expr\ClassConstFetch)
            && ! $node->class instanceof Node\Name && ! $node->class instanceof Node\Stmt\Class_) {
            $violations[] = $node->getStartLine().':dynamic-class';
        }
        $container = $node instanceof Node\Expr\FuncCall && $node->name instanceof Node\Name && in_array($node->name->toString(), ['app', 'resolve'], true)
            || $node instanceof Node\Expr\MethodCall && $node->name instanceof Node\Identifier && in_array($node->name->toString(), ['make', 'makeWith', 'get'], true)
                && ($node->var instanceof Node\Expr\FuncCall && $node->var->name instanceof Node\Name && $node->var->name->toString() === 'app');
        if ($container && isset($node->args[0])) {
            $class = $node->args[0]->value;
            if (! ($class instanceof Node\Scalar\String_ || $class instanceof Node\Expr\ClassConstFetch && $class->class instanceof Node\Name)) {
                $violations[] = $node->getStartLine().':dynamic-container';
            } elseif ($class instanceof Node\Scalar\String_ && in_array($class->value, ['router', 'http'], true)) {
                $violations[] = $node->getStartLine().':internal-http';
            }
        }
        if ($node instanceof Node\Expr\StaticCall && $node->class instanceof Node\Name && $node->name instanceof Node\Identifier
            && (in_array($node->class->toString(), ['Illuminate\\Http\\Request', 'Symfony\\Component\\HttpFoundation\\Request'], true) && in_array($node->name->toString(), ['create', 'createFrom', 'createFromBase'], true)
                || $node->class->toString() === 'Illuminate\\Support\\Facades\\Route' && in_array($node->name->toString(), ['dispatch', 'dispatchToRoute', 'respondWithRoute'], true))) {
            $violations[] = $node->getStartLine().':internal-http';
        }
        if ($node instanceof Node\Expr\FuncCall && $node->name instanceof Node\Name && in_array($node->name->toString(), ['call_user_func', 'call_user_func_array', 'forward_static_call', 'forward_static_call_array', 'curl_exec', 'curl_init'], true)) {
            $violations[] = $node->getStartLine().':dynamic-or-http-dispatch';
        }
    }

    return $violations;
}

test('Livewire components never delegate application requests to controllers kernels or dynamic classes', function (): void {
    $violations = [];
    foreach (boundaryFiles('app/Livewire') as $file) {
        foreach (boundaryLivewireViolations(boundaryAst((string) $file)) as $violation) {
            $violations[] = (string) $file.':'.$violation;
        }
    }
    expect($violations)->toBe([]);
});

test('the Livewire architecture guard detects aliases dynamic construction and internal HTTP dispatch', function (string $source): void {
    expect(boundaryLivewireViolations(boundarySourceAst('<?php '.$source)))->not->toBeEmpty();
})->with([
    'aliased controller' => ['use App\Http\Controllers\Invitations\ShowInvitationController as Screen; app(Screen::class)->__invoke();'],
    'vendor controller' => ['use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController as Auth; new Auth;'],
    'kernel injection' => ['function invoke(Illuminate\Contracts\Http\Kernel $kernel) { $kernel->handle($request); }'],
    'literal controller' => ['app("App\\\\Http\\\\Controllers\\\\Example")->run();'],
    'dynamic new' => ['$type = $input; new $type;'],
    'dynamic static' => ['$type::execute();'],
    'dynamic container' => ['app($type)->handle();'],
    'container make' => ['app()->make($type)->handle();'],
    'internal router' => ['app("router")->dispatch($request);'],
    'request clone' => ['Illuminate\Http\Request::create("/internal", "POST");'],
    'HTTP facade' => ['Illuminate\Support\Facades\Http::post(route("internal"));'],
    'callback dispatch' => ['call_user_func([$type, "__invoke"]);'],
]);

test('the input boundary catches instance static nullsafe and external side effects', function (string $source): void {
    expect(boundaryInputViolations(boundarySourceAst('<?php '.$source)))->not->toBeEmpty();
})->with([
    ['$model->save();'], ['App\Models\User::create($data);'], ['$model?->delete();'],
    ['$model->items()->sync($ids);'], ['Illuminate\Support\Facades\DB::transaction($callback);'],
    ['Illuminate\Support\Facades\Mail::send($message);'], ['event($event);'], ['file_put_contents($path, $bytes);'],
    ['app(App\Actions\Invitations\AcceptInvitationAction::class)->handle($invitation, $user);'],
    ['function validate(App\Actions\Invitations\AcceptInvitationAction $action) { $action->handle($invitation, $user); }'],
]);

test('architecture guards allow ordinary Livewire coordination and value validation', function (): void {
    $safe = boundarySourceAst('<?php use App\Actions\Invitations\AcceptInvitationAction; function accept(AcceptInvitationAction $action) { $action->handle($invitation, request()->user()); $this->dispatch("accepted"); }');
    expect(boundaryLivewireViolations($safe))->toBe([])
        ->and(boundaryInputViolations(boundarySourceAst('<?php use Illuminate\Validation\Rule; $this->fill($input); $this->validate(["email" => [Rule::unique(App\Models\User::class)]]);')))->toBe([]);
});
