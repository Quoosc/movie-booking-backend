<?php

namespace App\Enums;

enum BookingStatus: string
{
    case PENDING_PAYMENT = 'PENDING_PAYMENT';
    case CONFIRMED = 'CONFIRMED';
    case CANCELLED = 'CANCELLED';
    case EXPIRED = 'EXPIRED';
}
