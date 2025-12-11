<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case PENDING        = 'PENDING';
    case SUCCESS        = 'SUCCESS';
    case FAILED         = 'FAILED';
    case REFUND_PENDING = 'REFUND_PENDING';
    case REFUNDED       = 'REFUNDED';
    case REFUND_FAILED  = 'REFUND_FAILED';
}
