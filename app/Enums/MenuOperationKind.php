<?php

declare(strict_types=1);

namespace App\Enums;

enum MenuOperationKind: string
{
    case CreateItem = 'create_item';
    case ImageReorder = 'image_reorder';
    case ImagePresentation = 'image_presentation';
    case VariantChange = 'variant_change';
    case ModifierChange = 'modifier_change';
    case CloneModifierGroup = 'clone_modifier_group';
    case DuplicateItem = 'duplicate_item';
    case DeleteMenu = 'delete_menu';
    case DeleteCategory = 'delete_category';
    case ImageUpload = 'image_upload';
    case ImageRemove = 'image_remove';
    case ImagePromote = 'image_promote';
    case CatalogImport = 'catalog_import';
    case BulkItems = 'bulk_items';
}
