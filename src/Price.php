<?php

class Price
{
    public function __construct(
        public float $price,
        public DateTimeImmutable $startDate,
        public DateTimeImmutable $endDate,
        public string $country,
        public int $resolution,
    ) {}
}
