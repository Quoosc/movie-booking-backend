<?php

namespace App\Enums;

enum SeatStatus: string
{
    case AVAILABLE = 'AVAILABLE';
    case LOCKED    = 'LOCKED';
    case BOOKED   = 'BOOKED';
}
