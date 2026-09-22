<?php

namespace App\Enums;

enum StockMovementType: string
{
    case Receive = 'receive';
    case Transfer = 'transfer';
    case Dispatch = 'dispatch';
}
