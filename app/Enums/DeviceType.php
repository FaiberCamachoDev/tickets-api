<?php

namespace App\Enums;

enum DeviceType: string
{
    case PC = 'pc';
    case LAPTOP = 'laptop';
    case MOBILE = 'mobile';
    case TABLET = 'tablet';
    case OTHER = 'other';
}
