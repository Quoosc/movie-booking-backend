<?php

namespace App\Enums;

enum LockOwnerType: string
{
    case USER = 'USER';
    // Match DB enum value for guest sessions to avoid MySQL truncation
    case GUEST_SESSION = 'GUEST_SESSION';
}
