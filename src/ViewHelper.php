<?php

class ViewHelper
{
    private array $percentColors = [
        ['pct' => 0.0, 'color' => ['r' => 0x00, 'g' => 0x88, 'b' => 0x00]],
        ['pct' => 0.5, 'color' => ['r' => 0xAA, 'g' => 0xAA, 'b' => 0x00]],
        ['pct' => 1.0, 'color' => ['r' => 0xAA, 'g' => 0x00, 'b' => 0x00]],
    ];

    public function format(float $number): string
    {
        $num = number_format($number, 4);

        return substr($num, 0, strpos($num, '.') + 3) .
            '<span class="extra-decimals">' . substr($num, -2) . '</span>';
    }

    public function getColorPercentage(float $value, float $min, float $max): string
    {
        if ($value === -9999.0) {
            return '#fff';
        }

        $pct = ($max - $min) == 0 ? 0 : ($value - $min) / ($max - $min);

        for ($i = 1; $i < count($this->percentColors) - 1; $i++) {
            if ($pct < $this->percentColors[$i]['pct']) {
                break;
            }
        }

        $lower = $this->percentColors[$i - 1];
        $upper = $this->percentColors[$i];
        $range = $upper['pct'] - $lower['pct'];
        $rangePct = ($pct - $lower['pct']) / $range;
        $pctLower = 1 - $rangePct;
        $pctUpper = $rangePct;

        $color = [
            'r' => floor($lower['color']['r'] * $pctLower + $upper['color']['r'] * $pctUpper),
            'g' => floor($lower['color']['g'] * $pctLower + $upper['color']['g'] * $pctUpper),
            'b' => floor($lower['color']['b'] * $pctLower + $upper['color']['b'] * $pctUpper),
        ];

        return 'rgb(' . implode(',', [$color['r'], $color['g'], $color['b']]) . ')';
    }

    public function getLegendColors(): array
    {
        return $this->percentColors;
    }
}
