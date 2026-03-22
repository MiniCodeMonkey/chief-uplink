<?php

namespace App\Enums;

enum CloudProvider: string
{
    case Hetzner = 'hetzner';
    case DigitalOcean = 'digitalocean';
}
