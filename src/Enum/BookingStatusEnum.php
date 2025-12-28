<?php

namespace App\Enum;

enum BookingStatusEnum: string
{
    case ACTIVE = 'active';
    case CANCELLED = 'cancelled';
    case ARCHIVED = 'archived';
}