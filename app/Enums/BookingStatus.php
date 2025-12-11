<?php

namespace App\Enums;

enum BookingStatus: string
{
    case PENDING   = 'PENDING';
    case PENDING_PAYMENT = 'PENDING_PAYMENT';
    case EXPIRED = 'EXPIRED';
    case REFUND_PENDING = 'REFUND_PENDING';
    case REFUNDED = 'REFUNDED';
    case CONFIRMED = 'CONFIRMED';
    case CANCELLED = 'CANCELLED';
}
