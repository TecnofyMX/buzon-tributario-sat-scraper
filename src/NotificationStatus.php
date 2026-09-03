<?php

declare(strict_types=1);

namespace Tecnofy\BuzonTributarioSatScraper;

enum NotificationStatus: string
{
    case Pending = 'pending';
    case Notified = 'notified';
}
