<?php

declare(strict_types=1);

namespace Tecnofy\BuzonTributarioSatScraper\Services;

use GuzzleHttp\RequestOptions;
use Tecnofy\BuzonTributarioSatScraper\Exceptions\PaginationException;
use Tecnofy\BuzonTributarioSatScraper\Exceptions\UnexpectedPageException;
use Tecnofy\BuzonTributarioSatScraper\Internal\HttpRequester;
use Tecnofy\BuzonTributarioSatScraper\Internal\NavigationRequest;
use Tecnofy\BuzonTributarioSatScraper\Internal\Page;
use Tecnofy\BuzonTributarioSatScraper\Notification;
use Tecnofy\BuzonTributarioSatScraper\NotificationCollection;
use Tecnofy\BuzonTributarioSatScraper\NotificationStatus;

final readonly class NotificationService
{
    private const MAX_PAGES = 100;

    public function __construct(
        private HttpRequester $requester,
        private NotificationParser $parser,
    ) {
    }

    public function collect(Page $firstPage): NotificationCollection
    {
        /** @var list<array{page: Page, status: NotificationStatus|null}> $pages */
        $pages = [['page' => $firstPage, 'status' => null]];
        /** @var list<NavigationRequest> $queue */
        $queue = [];
        /** @var array<string, true> $visited */
        $visited = [];
        /** @var array<string, Notification> $notifications */
        $notifications = [];
        $recognizedPage = false;

        for ($index = 0; $index < self::MAX_PAGES; ++$index) {
            if (isset($pages[$index])) {
                $page = $pages[$index]['page'];
                $status = $pages[$index]['status'];
            } elseif ([] !== $queue) {
                $request = array_shift($queue);
                if (isset($visited[$request->fingerprint()])) {
                    --$index;
                    continue;
                }
                $visited[$request->fingerprint()] = true;
                $page = $this->follow($request);
                $status = $request->status;
            } else {
                break;
            }

            $recognizedPage = $recognizedPage || $this->parser->recognizesNotificationsPage($page);
            foreach ($this->parser->parse($page, $status) as $notification) {
                $existing = $notifications[$notification->folio] ?? null;
                if (null === $existing || NotificationStatus::Notified === $notification->status) {
                    $notifications[$notification->folio] = $notification;
                }
            }

            foreach ($this->parser->discoverNavigations($page, $status) as $navigation) {
                if (! isset($visited[$navigation->fingerprint()])) {
                    $queue[] = $navigation;
                }
            }
        }

        if ([] !== $queue) {
            throw new PaginationException('The SAT notification pagination exceeded the safe page limit.');
        }
        if (! $recognizedPage) {
            throw new UnexpectedPageException('The SAT response does not contain Mis notificaciones.');
        }

        $pending = array_values(array_filter(
            $notifications,
            static fn (Notification $item): bool => NotificationStatus::Pending === $item->status,
        ));
        $notified = array_values(array_filter(
            $notifications,
            static fn (Notification $item): bool => NotificationStatus::Notified === $item->status,
        ));

        return new NotificationCollection(...$pending, ...$notified);
    }

    private function follow(NavigationRequest $request): Page
    {
        $options = [];
        if ('GET' === strtoupper($request->method)) {
            $options[RequestOptions::QUERY] = $request->fields;
        } else {
            $options[RequestOptions::FORM_PARAMS] = $request->fields;
        }

        return $this->requester->request($request->method, $request->uri, $options);
    }
}
