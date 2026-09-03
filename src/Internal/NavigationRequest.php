<?php

declare(strict_types=1);

namespace Tecnofy\BuzonTributarioSatScraper\Internal;

use Tecnofy\BuzonTributarioSatScraper\NotificationStatus;

final readonly class NavigationRequest
{
    /** @param array<string, string> $fields */
    public function __construct(
        public string $method,
        public string $uri,
        public array $fields = [],
        public ?NotificationStatus $status = null,
    ) {
    }

    public function fingerprint(): string
    {
        $fields = $this->fields;
        ksort($fields);

        return hash('sha256', strtoupper($this->method) . "\n" . $this->uri . "\n" . http_build_query($fields));
    }
}
