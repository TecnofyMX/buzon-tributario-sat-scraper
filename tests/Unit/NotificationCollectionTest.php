<?php

declare(strict_types=1);

namespace Tecnofy\BuzonTributarioSatScraper\Tests\Unit;

use Tecnofy\BuzonTributarioSatScraper\Notification;
use Tecnofy\BuzonTributarioSatScraper\NotificationCollection;
use Tecnofy\BuzonTributarioSatScraper\NotificationStatus;
use Tecnofy\BuzonTributarioSatScraper\Tests\TestCase;

final class NotificationCollectionTest extends TestCase
{
    public function testFiltersAreImmutable(): void
    {
        $pending = new Notification('P-1', 'SAT', 'Acto A', '2026-09-01', NotificationStatus::Pending);
        $notified = new Notification('N-1', 'SAT', 'Acto B', '2026-08-31', NotificationStatus::Notified);
        $collection = new NotificationCollection($pending, $notified);

        self::assertCount(2, $collection);
        self::assertSame([$pending], $collection->pending()->all());
        self::assertSame([$notified], $collection->notified()->all());
        self::assertSame([$pending, $notified], iterator_to_array($collection));
    }
}
