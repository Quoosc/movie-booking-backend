<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case PAYPAL = 'PAYPAL';
    case MOMO   = 'MOMO';
}
