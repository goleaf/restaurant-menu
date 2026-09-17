<?php

declare(strict_types=1);

namespace App\Support\Auth;

use Symfony\Component\HttpFoundation\Response;

/** Transport acknowledgement for a redirect already recorded by Livewire. */
final class LivewireAuthRedirectResponse extends Response
{
    public function __construct()
    {
        parent::__construct('', 204);
    }
}
