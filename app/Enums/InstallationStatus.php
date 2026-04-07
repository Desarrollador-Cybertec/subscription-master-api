<?php

namespace App\Enums;

enum InstallationStatus: string
{
    case Active = 'active';
    case Expired = 'expired';
    case Suspended = 'suspended';
}
