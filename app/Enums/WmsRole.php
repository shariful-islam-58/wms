<?php

namespace App\Enums;

enum WmsRole: string
{
    case Admin = 'admin';
    case WarehouseOperator = 'warehouse_operator';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::WarehouseOperator => 'Warehouse Operator',
        };
    }

    public function canManageNetwork(): bool
    {
        return $this === self::Admin;
    }
}
