<?php

namespace App\Enums;

enum DeviceStatus: string
{
    case AVAILABLE = 'available';
    case ASSIGNED = 'assigned';
    case MAINTENANCE = 'maintenance';
}
