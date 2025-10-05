<?php

readonly class PriceCollection
{
    /**
     * @param  Price[]  $prices
     */
    public function __construct(private array $prices) {}

    public function toGrid(
        DateTimeZone $tz,
        bool $hourly,
        float $multiplier = 1.0
    ): array {
        // Build 15min grid first
        $grid = [];
        foreach ($this->prices as $price) {
            $start = $price->startDate->setTimeZone($tz);
            $date = $start->format('Y-m-d');
            $hour = (int) $start->format('H');
            $quarter = (int) ((int) $start->format('i') / 15);

            $grid[$date][$hour][$quarter] = round($multiplier * $price->price, 4);
        }

        // If hourly requested, average the quarters
        if ($hourly) {
            foreach ($grid as $date => $hours) {
                foreach ($hours as $hour => $quarters) {
                    if (count($quarters) === 4) {
                        $grid[$date][$hour] = [0 => round(array_sum($quarters) / 4, 4)];
                    }
                }
            }
        }

        return $grid;
    }
}
