<?php

declare(strict_types=1);

namespace Tecnofy\BuzonTributarioSatScraper\Internal;

final class Page
{
    public function __construct(public string $html, public string $uri)
    {
    }
}
