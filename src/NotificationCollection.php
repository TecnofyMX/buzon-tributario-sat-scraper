<?php

declare(strict_types=1);

namespace Tecnofy\BuzonTributarioSatScraper;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/** @implements IteratorAggregate<int, Notification> */
final readonly class NotificationCollection implements Countable, IteratorAggregate
{
    /** @var list<Notification> */
    private array $items;

    public function __construct(Notification ...$items)
    {
        $this->items = array_values($items);
    }

    public function count(): int
    {
        return count($this->items);
    }

    /** @return Traversable<int, Notification> */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    /** @return list<Notification> */
    public function all(): array
    {
        return $this->items;
    }

    public function pending(): self
    {
        return $this->filterByStatus(NotificationStatus::Pending);
    }

    public function notified(): self
    {
        return $this->filterByStatus(NotificationStatus::Notified);
    }

    private function filterByStatus(NotificationStatus $status): self
    {
        return new self(...array_values(array_filter(
            $this->items,
            static fn (Notification $notification): bool => $status === $notification->status,
        )));
    }
}
