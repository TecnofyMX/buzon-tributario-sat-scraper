<?php

declare(strict_types=1);

namespace Tecnofy\BuzonTributarioSatScraper;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/** @implements IteratorAggregate<int, Communication> */
final readonly class CommunicationCollection implements Countable, IteratorAggregate
{
    /** @var list<Communication> */
    private array $items;

    public function __construct(Communication ...$items)
    {
        $this->items = array_values($items);
    }

    public function count(): int
    {
        return count($this->items);
    }

    /** @return Traversable<int, Communication> */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    /** @return list<Communication> */
    public function all(): array
    {
        return $this->items;
    }
}
