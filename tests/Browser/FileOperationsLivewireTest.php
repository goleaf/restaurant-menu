<?php

declare(strict_types=1);

use App\Actions\Backups\CreateConsistentSqliteBackupAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\QrCodeStatus;
use App\Enums\SystemRole;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\QrCode;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Events\ResponsePrepared;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Pest\Browser\Api\PendingAwaitablePage;
use Pest\Browser\Execution;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\IsolatedBrowserIdentity;
use Tests\Support\PestFileUploadServer;

beforeEach(function (): void {
    PestFileUploadServer::configure();
    $this->withVite();
    IsolatedBrowserIdentity::configure();
});

test('report and QR controls generate real Livewire PDFs and an authorized binary CSV', function (): void {
    $this->seed(SystemPermissionsSeeder::class);
    $owner = User::factory()->create(['password' => 'password']);
    $organization = app(CreateOrganizationAction::class)->handle($owner, ['name' => 'File browser organization']);
    $brand = Brand::factory()->for($organization)->create();
    $branch = Branch::factory()->for($organization)->for($brand)->create();
    $point = ServicePoint::factory()->for($branch)->create(['name' => 'Browser export table']);
    $qr = QrCode::factory()->for($point)->create(['status' => QrCodeStatus::Active]);
    $identity = $qr->public_token;
    $observed = fileBrowserResponses();
    $page = visit(route('login', absolute: false));
    $page->fill('email', $owner->email)->fill('password', 'password')->click('@login-button')
        ->assertPathIs(route('restaurant.dashboard', absolute: false))
        ->navigate(route('restaurant.exports.index', ['branch' => $branch->id], false))
        ->assertPresent('[data-layout="data-exports"]');
    fileBrowserObserveEffects($page);
    $pdf = sprintf('button[wire\\:click="downloadPdf(%d, \'service-points\')"]', $branch->id);
    $csv = sprintf('button[wire\\:click="downloadCsv(%d, \'service-points\')"]', $branch->id);
    $page->click($pdf)->assertScript('window.fileEffects.downloads', 1)
        ->click($csv)->assertScript('window.fileEffects.redirects', 1)
        ->assertNoJavaScriptErrors()->assertNoConsoleLogs();
    Execution::instance()->waitForExpectation(fn () => expect($observed->binary)->toHaveCount(1));
    expect($observed->pdfs)->toHaveCount(1)
        ->and($observed->binary)->toHaveCount(1)
        ->and($observed->binary[0]['content'])->toContain('Browser export table')
        ->and($observed->binary[0]['cache'])->toContain('private', 'no-store');
    $page->navigate(route('organizations.brands.branches.qr.print', [$organization, $brand, $branch], false))
        ->check('input[type="checkbox"][value="'.$point->id.'"]');
    fileBrowserObserveEffects($page);
    $page->click('button[wire\\:click="downloadPdf"]')->assertScript('window.fileEffects.downloads', 1)
        ->resize(390, 844)->screenshot(filename: 'files-qr-livewire-390')
        ->assertNoJavaScriptErrors()->assertNoConsoleLogs();
    expect($observed->pdfs)->toHaveCount(2)
        ->and($observed->pdfs)->each->toBe('%PDF-')
        ->and($qr->fresh()->public_token)->toBe($identity);
});

test('backup controls restore only the reviewed disposable database and invalidate the old browser session', function (string $locale): void {
    $originalConnection = config('database.default');
    $connection = 'browser_restore_'.Str::lower(Str::random(10));
    $directory = storage_path('framework/testing/'.$connection);
    File::ensureDirectoryExists($directory);
    File::put($directory.'/database.sqlite', '');
    config()->set('database.connections.'.$connection, ['driver' => 'sqlite', 'database' => $directory.'/database.sqlite', 'prefix' => '', 'foreign_key_constraints' => true, 'busy_timeout' => 5000]);
    try {
        Artisan::call('migrate', ['--database' => $connection, '--force' => true]);
        config()->set('database.default', $connection);
        $this->seed(SystemPermissionsSeeder::class);
        $operator = User::factory()->create(['password' => 'password', 'name' => 'Backup original state', 'locale' => $locale]);
        $operator->roles()->attach(Role::query()->where('code', SystemRole::Superadmin->value)->firstOrFail());
        $backup = app(CreateConsistentSqliteBackupAction::class)->handle();
        $fixture = $directory.'/browser-restore.sqlite';
        File::copy($backup, $fixture);
        expect(filesize($fixture))->toBeLessThan(2 * 1024 * 1024 - 2048);
        File::delete($backup);
        $operator->update(['name' => 'Keep current state until finalization']);
        FileUploadConfiguration::storage();
        fileBrowserDecodeUpload($fixture);
        $observed = fileBrowserResponses();
        $page = visit(route('login', ['lang' => $locale], false));
        $page->fill('email', $operator->email)->fill('password', 'password')->click('@login-button')
            ->assertPathIs(route('dashboard', absolute: false))
            ->navigate(route('password.confirm', absolute: false))
            ->assertPathIs(route('password.confirm', absolute: false))
            ->fill('password', 'password')->click('@confirm-password-button')
            ->assertPathIs(route('dashboard', absolute: false))
            ->navigate(route('superadmin.dashboard', absolute: false));
        $page->click(__('ui.superadmin.dashboard.download_sqlite'))
            ->fill('#dangerous-action-sqlite-backup-download-reason', 'Browser recovery snapshot')
            ->fill('#dangerous-action-sqlite-backup-download-confirmation', 'BACKUP')
            ->click('button[wire\\:click="downloadBackup"]');
        Execution::instance()->waitForExpectation(fn () => expect($observed->binary)->toHaveCount(1));
        $page->assertPathIs(route('superadmin.dashboard', absolute: false));
        expect($observed->binary)->toHaveCount(1)
            ->and($observed->binary[0]['content'])->toStartWith("SQLite format 3\0")
            ->and(AuditLog::query()->where('action', 'backup_downloaded')->count())->toBe(1);
        $page->click(__('ui.superadmin.dashboard.restore_sqlite'))
            ->fill('#dangerous-action-sqlite-backup-restore-reason', 'Review verified browser fixture')
            ->fill('#dangerous-action-sqlite-backup-restore-confirmation', 'RESTORE')
            ->click('button[wire\\:click="prepareBackupRestore"]')
            ->assertPathIs(route('superadmin.backups.sqlite.restore', absolute: false))
            ->assertMissing('form[method="POST"]');
        fileBrowserAttach($page, $fixture);
        $page->assertScript('Livewire.find(document.querySelector("[x-data=restoreUpload]").getAttribute("wire:id")).get("upload.backup") !== null');
        $page->click('form[wire\\:submit="preview"] button[type="submit"]')
            ->assertVisible('form[method="POST"]')
            ->assertSee('browser-restore.sqlite')
            ->assertSee('Review verified browser fixture')
            ->assertMissing('input[name="backup"]')
            ->assertMissing('input[name="path"]');
        expect($operator->fresh()->name)->toBe('Keep current state until finalization');
        $page->script('window.dispatchEvent(new Event("offline"))');
        $page->assertDisabled('form[method="POST"] button[type="submit"]');
        $page->script('window.dispatchEvent(new Event("online"))');
        $page->assertEnabled('form[method="POST"] button[type="submit"]')
            ->resize(320, 900)->assertScript('document.documentElement.scrollWidth <= innerWidth')
            ->assertSee(__('ui.superadmin.backup_restore.submit', [], $locale));
        fileBrowserAssertRestoreButtonFits($page);
        $page->screenshot(filename: 'files-restore-preview-'.$locale.'-320');
        $page->resize(640, 1400)->script('document.documentElement.style.zoom = "2"');
        fileBrowserAssertRestoreButtonFits($page);
        $page->screenshot(filename: 'files-restore-preview-'.$locale.'-zoom200');
        $page->script('document.documentElement.style.zoom = ""');
        $page->assertNoJavaScriptErrors()->assertNoConsoleLogs();
        $retiredSessionFile = config('session.files').'/'.session()->getId();
        expect(File::isFile($retiredSessionFile))->toBeTrue();
        $page->click('form[method="POST"] button[type="submit"]')
            ->assertPathIs(route('login', absolute: false))
            ->assertSee(__('ui.superadmin.backup_restore.completed', [], $locale))
            ->navigate(route('superadmin.dashboard', absolute: false))
            ->assertPathIs(route('login', absolute: false));
        expect(User::query()->findOrFail($operator->id)->name)->toBe('Backup original state')
            ->and(AuditLog::query()->where('action', 'backup_restored')->count())->toBe(1)
            ->and($observed->restores)->toBe(1)
            ->and(File::isFile($retiredSessionFile))->toBeFalse();
        $page->fill('email', $operator->email)->fill('password', 'password')->click('@login-button')
            ->assertPathIs(route('superadmin.dashboard', absolute: false))
            ->assertNoJavaScriptErrors()->assertNoConsoleLogs();
    } finally {
        config()->set('database.default', $originalConnection);
        DB::purge($connection);
        config()->set('database.connections.'.$connection, null);
        File::deleteDirectory($directory);
    }
})->with(['en' => ['en'], 'lt' => ['lt'], 'ru' => ['ru']]);

function fileBrowserObserveEffects(PendingAwaitablePage $page): void
{
    $page->script(<<<'JAVASCRIPT'
        (() => {
            window.fileEffects = { downloads: 0, redirects: 0 };
            const originalFetch = window.fetch;
            window.fetch = async (...args) => {
                const response = await originalFetch(...args);
                if (response.headers.get('content-type')?.includes('application/json')) {
                    response.clone().json().then(data => {
                        for (const component of data.components ?? []) {
                            if (component.effects?.download) window.fileEffects.downloads++;
                            if (component.effects?.redirect) window.fileEffects.redirects++;
                        }
                    });
                }
                return response;
            };
        })()
    JAVASCRIPT);
}

function fileBrowserResponses(): object
{
    $observed = new class
    {
        public int $restores = 0;

        public array $pdfs = [];

        public array $binary = [];
    };
    $seen = new WeakMap;
    Event::listen(ResponsePrepared::class, function (ResponsePrepared $event) use ($observed, $seen): void {
        if (isset($seen[$event->request])) {
            return;
        }
        $seen[$event->request] = true;
        if ($event->request->isMethod('POST') && $event->request->routeIs('superadmin.backups.sqlite.restore.store')) {
            $observed->restores++;
        }
        if ($event->response instanceof BinaryFileResponse && $event->request->routeIs('restaurant.files.download')) {
            $observed->binary[] = ['content' => $event->response->getFile()->getContent(), 'cache' => $event->response->headers->get('Cache-Control')];
        }
        $content = $event->response->getContent();
        if (is_string($content) && str_contains((string) $event->response->headers->get('Content-Type'), 'application/json')) {
            foreach (json_decode($content, true)['components'] ?? [] as $component) {
                if (isset($component['effects']['download'])) {
                    $observed->pdfs[] = substr(base64_decode($component['effects']['download']['content'], true), 0, 5);
                }
            }
        }
    });

    return $observed;
}

/** Pest's in-process server omits multipart files; decode only this exact disposable fixture. */
function fileBrowserDecodeUpload(string $fixture): void
{
    $middleware = new class($fixture)
    {
        public function __construct(private readonly string $fixture) {}

        public function handle(Request $request, Closure $next): Response
        {
            $type = $request->header('Content-Type', '');
            if (! str_starts_with($type, 'multipart/form-data') || ! str_contains($request->path(), 'livewire')) {
                return $next($request);
            }
            expect(preg_match('/boundary="?([^";]+)"?/', $type, $matches))->toBe(1);
            $body = $request->getContent();
            expect(strlen($body))->toBeLessThan(filesize($this->fixture) + 2048);
            $files = [];
            foreach (explode('--'.$matches[1], $body) as $part) {
                if (! str_contains($part, 'name="files[]"')) {
                    continue;
                }
                [$headers, $contents] = explode("\r\n\r\n", $part, 2);
                expect($headers)->toContain('filename="browser-restore.sqlite"');
                $contents = substr($contents, 0, -2);
                expect(hash('sha256', $contents))->toBe(hash_file('sha256', $this->fixture));
                $files[] = UploadedFile::fake()->createWithContent('browser-restore.sqlite', $contents);
            }
            expect($files)->toHaveCount(1);
            $request->files->set('files', $files);

            return $next($request);
        }
    };
    app()->instance('browser.file-operation-upload', $middleware);
    app(Kernel::class)->prependMiddleware('browser.file-operation-upload');
}

function fileBrowserAttach(PendingAwaitablePage $page, string $fixture): void
{
    $page->assertScript('document.querySelector("ui-file-upload").inputEl instanceof HTMLInputElement && Array.isArray(document.querySelector("ui-file-upload").files)');
    $encoded = json_encode(base64_encode(File::get($fixture)), JSON_THROW_ON_ERROR);
    expect($page->script(<<<JAVASCRIPT
        (() => {
            const input = document.querySelector('input[type="file"]');
            if (!(input instanceof HTMLInputElement) || input.disabled) return false;
            const bytes = Uint8Array.from(atob({$encoded}), character => character.charCodeAt(0));
            const transfer = new DataTransfer();
            transfer.items.add(new File([bytes], 'browser-restore.sqlite', { type: 'application/vnd.sqlite3' }));
            input.files = transfer.files;
            input.dispatchEvent(new Event('change', { bubbles: true }));
            return true;
        })()
    JAVASCRIPT))->toBeTrue();
}

function fileBrowserAssertRestoreButtonFits(PendingAwaitablePage $page): void
{
    $page->script('new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)))');
    $geometry = $page->script(<<<'JAVASCRIPT'
        (() => {
            const button = document.querySelector('form[method="POST"] button[type="submit"]');
            const label = [...button.querySelectorAll('span')].find(span => span.textContent.trim().length > 0);
            const range = document.createRange();
            range.selectNodeContents(label);
            const rect = button.getBoundingClientRect();
            const parent = button.closest('form').getBoundingClientRect();
            return {
                buttonInsideForm: rect.left >= parent.left - 1 && rect.right <= parent.right + 1,
                buttonNotClipped: button.scrollWidth <= button.clientWidth,
                labelNotClipped: label.scrollWidth <= label.clientWidth,
                linesInsideButton: [...range.getClientRects()].every(line => line.left >= rect.left - 1 && line.right <= rect.right + 1 && line.top >= rect.top - 1 && line.bottom <= rect.bottom + 1),
                buttonWidth: rect.width,
                formWidth: parent.width,
                labelScrollWidth: label.scrollWidth,
                labelClientWidth: label.clientWidth,
            };
        })()
    JAVASCRIPT);
    expect($geometry['buttonInsideForm'])->toBeTrue(json_encode($geometry, JSON_THROW_ON_ERROR))
        ->and($geometry['buttonNotClipped'])->toBeTrue()
        ->and($geometry['labelNotClipped'])->toBeTrue()
        ->and($geometry['linesInsideButton'])->toBeTrue();
}
