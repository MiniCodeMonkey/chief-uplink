<?php

namespace App\Enums;

enum ServerStatus: string
{
    case Provisioning = 'provisioning';
    case Active = 'active';
    case Failed = 'failed';
}
