<?php

declare(strict_types=1);

namespace Tecnofy\BuzonTributarioSatScraper;

final class Communication
{
    public function __construct(
        public string $receivedAt,
        public string $subject,
    ) {
        if (1 !== preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $this->receivedAt)) {
            throw new \InvalidArgumentException('The communication date must use YYYY-MM-DD HH:MM:SS format.');
        }
        if ('' === trim($this->subject)) {
            throw new \InvalidArgumentException('The communication subject cannot be empty.');
        }
    }
}
