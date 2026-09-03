<?php

declare(strict_types=1);

namespace Tecnofy\BuzonTributarioSatScraper;

interface ScraperInterface
{
    public function unreadCommunications(): CommunicationCollection;
}
