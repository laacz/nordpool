<?php

class PriceCollection
{
    /**
     * @param  Price[]  $prices
     */
    public function __construct(private array $prices) {}

    /**
     * Transforms prices into a grid structure for display
     *
     * @param  DateTimeZone  $tz  Target timezone for display
     * @param  bool  $hourly  If true, averages quarters into hourly values
     * @param  float  $multiplier  Multiplier to apply (e.g., 1.21 for VAT)
     * @return array Grid structure: [date][hour][quarter] => value
     *               For 15min: [date][hour][0,1,2,3] => value
     *               For 60min: [date][hour][0] => averaged_value
     */
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
