<?php

declare(strict_types=1);

namespace Tecnofy\BuzonTributarioSatScraper\Tests\Unit\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Tecnofy\BuzonTributarioSatScraper\Internal\FormParser;
use Tecnofy\BuzonTributarioSatScraper\Internal\HttpRequester;
use Tecnofy\BuzonTributarioSatScraper\Internal\Page;
use Tecnofy\BuzonTributarioSatScraper\Notification;
use Tecnofy\BuzonTributarioSatScraper\NotificationStatus;
use Tecnofy\BuzonTributarioSatScraper\Services\NotificationParser;
use Tecnofy\BuzonTributarioSatScraper\Services\NotificationService;
use Tecnofy\BuzonTributarioSatScraper\Tests\TestCase;

final class NotificationServiceTest extends TestCase
{
    public function testPaginatesAndNotifiedVersionWinsForSameFolio(): void
    {
        $mock = new MockHandler([
            new Response(200, [], self::fixture('notifications-pending-page-2.html')),
            new Response(200, [], self::fixture('notifications-notified.html')),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);
        $requester = new HttpRequester($client);
        $service = new NotificationService(
            $requester,
            new NotificationParser(new FormParser()),
        );

        $result = $service->collect(new Page(
            self::fixture('notifications-pending.html'),
            'https://wwwmat.sat.gob.mx/iniciar-expediente/mis-notificaciones',
        ));

        self::assertCount(3, $result);
        self::assertSame(['ABC-002'], array_map(
            static fn (Notification $item): string => $item->folio,
            $result->pending()->all(),
        ));
        self::assertSame(['ABC-001', 'ABC-003'], array_map(
            static fn (Notification $item): string => $item->folio,
            $result->notified()->all(),
        ));
        self::assertSame(NotificationStatus::Notified, $result->all()[1]->status);
    }

    public function testEmptyNotificationPageReturnsEmptyCollection(): void
    {
        $client = new Client(['handler' => HandlerStack::create(new MockHandler())]);
        $requester = new HttpRequester($client);
        $service = new NotificationService($requester, new NotificationParser(new FormParser()));

        $result = $service->collect(new Page(
            self::fixture('notifications-empty.html'),
            'https://wwwmat.sat.gob.mx/iniciar-expediente/mis-notificaciones',
        ));

        self::assertCount(0, $result);
    }
}
