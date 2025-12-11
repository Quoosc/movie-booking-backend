<?php

namespace App\Enums;

enum LockOwnerType: string
{
    case USER = 'USER';
    case GUEST = 'GUEST';
}
