<?php

declare(strict_types=1);

namespace App\Providers;

use App\Mcp\McpContext;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

final class McpServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Auth::viaRequest('restaurant-mcp', function (Request $request): ?User {
            $context = $request->attributes->get(McpContext::class);

            return $context instanceof McpContext ? $context->user : null;
        });
    }
}
