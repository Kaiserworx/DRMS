<?php

namespace App\Enums;

enum ReceivingBoxTokenAction: string
{
    case Generated = 'generated';
    case Regenerated = 'regenerated';
}
