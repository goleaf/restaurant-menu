<?php

namespace App\Actions\AreaNodes;

use App\Models\AreaNode;

class DeleteAreaNodeAction
{
    public function handle(AreaNode $areaNode): void
    {
        $areaNode->children()->update(['parent_id' => $areaNode->parent_id]);

        $areaNode->delete();
    }
}
