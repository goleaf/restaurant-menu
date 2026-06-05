<?php

namespace App\Enums;

enum PermissionOverrideState: string
{
    case Default = 'default';
    case Allow = 'allow';
    case Deny = 'deny';

    public function label(): string
    {
        return match ($this) {
            self::Default => 'Default',
            self::Allow => 'Allow',
            self::Deny => 'Deny',
        };
    }

    public function labelKey(): string
    {
        return 'permissions.actions.'.$this->value;
    }

    public function summaryLabel(): string
    {
        return match ($this) {
            self::Default => 'Role default',
            self::Allow => 'Allowed by override',
            self::Deny => 'Denied by override',
        };
    }

    public function summaryLabelKey(): string
    {
        return match ($this) {
            self::Default => 'permissions.states.role_default',
            self::Allow => 'permissions.states.allowed_by_override',
            self::Deny => 'permissions.states.denied_by_override',
        };
    }

    public function enabledValue(): ?bool
    {
        return match ($this) {
            self::Default => null,
            self::Allow => true,
            self::Deny => false,
        };
    }
}
