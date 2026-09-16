<?php

declare(strict_types=1);

namespace App\Support\Navigation;

use Illuminate\Support\Facades\Auth;
use Livewire\ComponentHook;
use Livewire\Mechanisms\HandleComponents\ComponentContext;

final class WorkspaceActorGuard extends ComponentHook
{
    /** @param array<string, mixed> $memo */
    public function hydrate(array $memo): void
    {
        if (array_key_exists('workspaceActor', $memo) || (Auth::check() && $this->ownsAuthenticatedState())) {
            abort_unless(($memo['workspaceActor'] ?? null) === Auth::id(), 409);
        }
    }

    public function dehydrate(ComponentContext $context): void
    {
        if (Auth::check() && $this->ownsAuthenticatedState()) {
            $context->addMemo('workspaceActor', Auth::id());
        }
    }

    private function ownsAuthenticatedState(): bool
    {
        return str_starts_with($this->component::class, 'App\\Livewire\\') && ! str_starts_with($this->component::class, 'App\\Livewire\\PublicQr\\');
    }
}
