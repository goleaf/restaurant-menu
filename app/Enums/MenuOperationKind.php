<?php

declare(strict_types=1);

namespace App\Enums;

enum MenuOperationKind: string
{
    case DuplicateItem = 'duplicate_item';
    case DeleteMenu = 'delete_menu';
    case DeleteCategory = 'delete_category';
    case ImageUpload = 'image_upload';
    case ImageRemove = 'image_remove';
    case ImagePromote = 'image_promote';
}
