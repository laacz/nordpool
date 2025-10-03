package main

import (
	"fmt"
	"math"
)

type RGB struct {
	R, G, B int
}

type ColorStop struct {
	Pct   float64
	Color RGB
}

var percentColors = []ColorStop{
	{0.0, RGB{0x00, 0x88, 0x00}},
	{0.5, RGB{0xaa, 0xaa, 0x00}},
	{1.0, RGB{0xaa, 0x00, 0x00}},
}

// GetColorPercentage returns an RGB color string based on value position between min and max
func GetColorPercentage(value, min, max float64) string {
	if value == -9999 {
		return "#fff"
	}

	var pct float64
	if max-min == 0 {
		pct = 0
	} else {
		pct = (value - min) / (max - min)
	}

	// Find the color stops to interpolate between
	i := 1
	for i < len(percentColors)-1 {
		if pct < percentColors[i].Pct {
			break
		}
		i++
	}

	lower := percentColors[i-1]
	upper := percentColors[i]
	colorRange := upper.Pct - lower.Pct
	rangePct := (pct - lower.Pct) / colorRange
	pctLower := 1 - rangePct
	pctUpper := rangePct

	r := int(math.Floor(float64(lower.Color.R)*pctLower + float64(upper.Color.R)*pctUpper))
	g := int(math.Floor(float64(lower.Color.G)*pctLower + float64(upper.Color.G)*pctUpper))
	b := int(math.Floor(float64(lower.Color.B)*pctLower + float64(upper.Color.B)*pctUpper))

	return fmt.Sprintf("rgb(%d,%d,%d)", r, g, b)
}

// FormatPrice formats a price with 2 main decimals and 2 extra decimals in a span
func FormatPrice(price float64) string {
	formatted := fmt.Sprintf("%.4f", price)
	if len(formatted) >= 4 {
		main := formatted[:len(formatted)-2]
		extra := formatted[len(formatted)-2:]
		return fmt.Sprintf("%s<span class=\"extra-decimals\">%s</span>", main, extra)
	}
	return formatted
}

// FormatPriceSimple formats a price with 4 decimals
func FormatPriceSimple(price float64) string {
	return fmt.Sprintf("%.4f", price)
}

// GetGoodBadColors returns the RGB values for good and bad colors (for legend)
func GetGoodBadColors() (RGB, RGB) {
	return percentColors[0].Color, percentColors[2].Color
}
