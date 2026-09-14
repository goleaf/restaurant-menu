<?php

declare(strict_types=1);

namespace App\Enums;

enum MenuOperationPhase: string
{
    case Preparing = 'preparing';
    case Media = 'media';
    case Variants = 'variants';
    case Modifiers = 'modifiers';
    case Discovering = 'discovering';
    case Items = 'items';
    case Categories = 'categories';
    case Finalizing = 'finalizing';
    case Completed = 'completed';
    case Failed = 'failed';
}
