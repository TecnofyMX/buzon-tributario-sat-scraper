<?php

declare(strict_types=1);

namespace Tecnofy\BuzonTributarioSatScraper;

final class Notification
{
    public function __construct(
        public string $folio,
        public string $issuingAuthority,
        public string $administrativeAct,
        public string $noticeDate,
        public NotificationStatus $status,
    ) {
        if ('' === trim($this->folio)) {
            throw new \InvalidArgumentException('The notification folio cannot be empty.');
        }

        if (1 !== preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->noticeDate)) {
            throw new \InvalidArgumentException('The notice date must use YYYY-MM-DD format.');
        }
    }
}
