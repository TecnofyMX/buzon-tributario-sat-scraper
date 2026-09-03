<?php

declare(strict_types=1);

namespace Tecnofy\BuzonTributarioSatScraper\Internal;

final readonly class Page
{
    public function __construct(public string $html, public string $uri)
    {
    }
}
