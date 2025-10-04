<?php

class Prices implements IteratorAggregate, Countable
{
    public function __construct(
        /* @property []Price */
        private array $prices,
    ) {}

    public function getIterator(): Traversable
    {
        yield from $this->prices;
    }

    public function count(): int
    {
        return count($this->prices);
    }
}
