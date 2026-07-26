<?php

namespace App\Enums;

enum DocumentNotificationType: string
{
    case Assignment = 'assignment';
    case Placement = 'placement';
    case StatusChanged = 'status_changed';
    case UpstreamReturn = 'upstream_return';
    case UnclaimedReminder = 'unclaimed_reminder';
    case Cancellation = 'cancellation';
    case Correction = 'correction';
}
