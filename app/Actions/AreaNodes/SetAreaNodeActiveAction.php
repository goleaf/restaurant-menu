<?php

declare(strict_types=1);

namespace App\Actions\AreaNodes;

use App\Models\AreaNode;

final class SetAreaNodeActiveAction
{
    public function handle(AreaNode $areaNode, bool $isActive): AreaNode
    {
        $areaNode->updateOrFail(['is_active' => $isActive]);

        return $areaNode;
    }
}
