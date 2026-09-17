<?php

declare(strict_types=1);

namespace App\Http\Controllers\Restaurant;

use App\Actions\Exports\BuildDataExportsIndexAction;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Files\PreparedDownloadStore;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class DownloadPreparedFileController extends Controller
{
    public function __invoke(Request $request, string $grant, PreparedDownloadStore $downloads, BuildDataExportsIndexAction $exports, RequirePassword $password): Response
    {
        abort_unless($request->isMethod('GET'), 405);
        abort_if(str_contains(strtolower($request->header('Purpose', '').' '.$request->header('Sec-Purpose', '').' '.$request->header('X-Moz', '')), 'prefetch'), 405);
        abort_if($request->headers->has('Range'), 416);
        $data = $downloads->peek($request, $grant);
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        if ($data['kind'] === 'csv') {
            abort_unless(is_int($data['branch_id']), 403);
            $exports->branch($user, $data['branch_id']);
        } else {
            abort_unless(in_array($data['kind'], ['sqlite', 'media'], true) && $user->isSuperadmin(), 403);
            $confirmation = $password->handle($request, static fn (): Response => new Response(status: 204));
            if ($confirmation->getStatusCode() !== 204) {
                return $confirmation;
            }
        }
        $data = $downloads->consume($request, $grant);
        $contentType = match ($data['kind']) {
            'csv' => 'text/csv; charset=UTF-8',
            'sqlite' => 'application/vnd.sqlite3',
            'media' => 'application/zip',
            default => abort(403),
        };

        $response = response()->download($data['path'], $data['filename'], ['Content-Type' => $contentType, 'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0', 'X-Content-Type-Options' => 'nosniff'])->deleteFileAfterSend(true);
        $response->setPrivate();

        return $response;
    }
}
