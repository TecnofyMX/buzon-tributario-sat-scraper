<?php

declare(strict_types=1);

namespace Tecnofy\BuzonTributarioSatScraper\Internal;

final class Form
{
    /** @param array<string, string> $fields */
    public function __construct(
        public string $action,
        public string $method,
        public array $fields,
    ) {
    }
}
