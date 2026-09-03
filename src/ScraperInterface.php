<?php

declare(strict_types=1);

namespace Tecnofy\BuzonTributarioSatScraper;

interface ScraperInterface
{
    public function notifications(): NotificationCollection;

    public function unreadCommunications(): CommunicationCollection;
}
