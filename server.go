package main

import (
	"bytes"
	"database/sql"
	"fmt"
	"log"
	"math"
	"net/http"
	_ "net/http/pprof"
	"os"
	"strings"
	"sync"
	"time"
)

const dbPath = "./nordpool.db"

// priceCache holds raw database results and rendered HTML
type priceCache struct {
	mu           sync.RWMutex
	lastModTime  time.Time
	cachedPrices map[string]map[int][]priceRow
	cachedHTML   map[string]string
}

type priceRow struct {
	tsStart string
	tsEnd   string
	value   float64
}

// htmlCacheKey represents the parameters that affect rendered HTML
type htmlCacheKey struct {
	country    string
	resolution int
	withVAT    bool
	date       string // YYYY-MM-DD format
}

func (k htmlCacheKey) String() string {
	return fmt.Sprintf("%s:%d:%t:%s", k.country, k.resolution, k.withVAT, k.date)
}

var cache = &priceCache{
	cachedPrices: make(map[string]map[int][]priceRow),
	cachedHTML:   make(map[string]string),
}

// Cached timezone locations (loaded once at startup)
var (
	tzRiga *time.Location
	tzCET  *time.Location
)

func init() {
	var err error
	tzRiga, err = time.LoadLocation("Europe/Riga")
	if err != nil {
		log.Fatalf("Failed to load Europe/Riga timezone: %s", err)
	}
	tzCET, err = time.LoadLocation("Europe/Berlin")
	if err != nil {
		log.Fatalf("Failed to load Europe/Berlin timezone: %s", err)
	}
}

// checkAndInvalidateCache checks if the database file has been modified
// and invalidates the cache if necessary
func checkAndInvalidateCache() error {
	fileInfo, err := os.Stat(dbPath)
	if err != nil {
		return fmt.Errorf("failed to stat database file: %w", err)
	}

	modTime := fileInfo.ModTime()

	cache.mu.Lock()
	defer cache.mu.Unlock()

	// If database has been modified since last cache, invalidate both caches
	if modTime.After(cache.lastModTime) {
		cache.cachedPrices = make(map[string]map[int][]priceRow)
		cache.cachedHTML = make(map[string]string)
		cache.lastModTime = modTime
		log.Printf("Cache invalidated - database modified at %v", modTime)
		return nil
	}

	return nil
}

// getCachedPrices returns cached price data or queries the database
// Returns (rows, fromCache, error)
func getCachedPrices(db *sql.DB, country string, resolution int, sqlTime string) ([]priceRow, bool, error) {
	// Check and invalidate cache if database has changed
	if err := checkAndInvalidateCache(); err != nil {
		return nil, false, err
	}

	// Try to get from cache
	cache.mu.RLock()
	if cache.cachedPrices[country] != nil && cache.cachedPrices[country][resolution] != nil {
		cached := cache.cachedPrices[country][resolution]
		cache.mu.RUnlock()
		return cached, true, nil
	}
	cache.mu.RUnlock()

	// Cache miss - query database
	query := `
		SELECT ts_start, ts_end, value
		FROM price_indices
		WHERE country = ?
		AND ts_start >= DATE(?, '-2 day')
		AND ts_start <= DATE(?, '+3 day')
		AND resolution_minutes = ?
		ORDER BY ts_start DESC
	`

	rows, err := db.Query(query, country, sqlTime, sqlTime, resolution)
	if err != nil {
		return nil, false, fmt.Errorf("database query error: %w", err)
	}
	defer rows.Close()

	// Read all rows
	var priceRows []priceRow
	for rows.Next() {
		var row priceRow
		if err := rows.Scan(&row.tsStart, &row.tsEnd, &row.value); err != nil {
			continue
		}
		priceRows = append(priceRows, row)
	}

	// Store in cache
	cache.mu.Lock()
	if cache.cachedPrices[country] == nil {
		cache.cachedPrices[country] = make(map[int][]priceRow)
	}
	cache.cachedPrices[country][resolution] = priceRows
	cache.mu.Unlock()

	return priceRows, false, nil
}

// getCachedHTML retrieves cached HTML if available
func getCachedHTML(key htmlCacheKey) (string, bool) {
	cache.mu.RLock()
	defer cache.mu.RUnlock()

	html, ok := cache.cachedHTML[key.String()]
	return html, ok
}

// setCachedHTML stores rendered HTML in cache
func setCachedHTML(key htmlCacheKey, html string) {
	cache.mu.Lock()
	defer cache.mu.Unlock()

	cache.cachedHTML[key.String()] = html
}

func serveHTTP(db *sql.DB, port string) error {
	// Wrap handleIndex with logging middleware
	http.HandleFunc("/", loggingMiddleware(func(w http.ResponseWriter, r *http.Request) {
		handleIndex(db, w, r)
	}))

	// Serve static files
	http.Handle("/echarts.min.js", http.FileServer(http.Dir("./public")))
	http.Handle("/lv.svg", http.FileServer(http.Dir("./public")))
	http.Handle("/lt.svg", http.FileServer(http.Dir("./public")))
	http.Handle("/ee.svg", http.FileServer(http.Dir("./public")))
	http.Handle("/nordpool-ee-1h-excel.csv", http.FileServer(http.Dir("./public")))
	http.Handle("/nordpool-ee-1h.csv", http.FileServer(http.Dir("./public")))
	http.Handle("/nordpool-ee-excel.csv", http.FileServer(http.Dir("./public")))
	http.Handle("/nordpool-ee.csv", http.FileServer(http.Dir("./public")))
	http.Handle("/nordpool-lt-1h-excel.csv", http.FileServer(http.Dir("./public")))
	http.Handle("/nordpool-lt-1h.csv", http.FileServer(http.Dir("./public")))
	http.Handle("/nordpool-lt-excel.csv", http.FileServer(http.Dir("./public")))
	http.Handle("/nordpool-lt.csv", http.FileServer(http.Dir("./public")))
	http.Handle("/nordpool-lv-1h-excel.csv", http.FileServer(http.Dir("./public")))
	http.Handle("/nordpool-lv-1h.csv", http.FileServer(http.Dir("./public")))
	http.Handle("/nordpool-lv-excel.csv", http.FileServer(http.Dir("./public")))
	http.Handle("/nordpool-lv.csv", http.FileServer(http.Dir("./public")))

	log.Printf("Server starting on http://localhost:%s", port)
	log.Printf("Profiling available at http://localhost:%s/debug/pprof/", port)
	return http.ListenAndServe(":"+port, nil)
}

// loggingMiddleware logs request duration and path
func loggingMiddleware(next http.HandlerFunc) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		start := time.Now()
		next(w, r)
		duration := time.Since(start)
		log.Printf("Request: %s %s - Duration: %v", r.Method, r.URL.Path, duration)
	}
}

func handleIndex(db *sql.DB, w http.ResponseWriter, r *http.Request) {
	startTotal := time.Now()
	// Parse country from URL path
	path := r.URL.Path
	parts := strings.Split(strings.Trim(path, "/"), "/")
	country := "LV"
	if len(parts) > 0 && parts[0] != "" {
		country = strings.ToUpper(parts[0])
	}

	// Validate country
	if _, ok := countryConfigs[country]; !ok {
		country = "LV"
	}

	// Parse query parameters
	withVAT := r.URL.Query().Has("vat")
	resolution := 15
	if r.URL.Query().Get("res") == "60" {
		resolution = 60
	}
	quartersPerHour := 4
	if resolution == 60 {
		quartersPerHour = 1
	}

	// Parse current time (for testing) - use cached timezone locations
	currentTime := time.Now().In(tzRiga)
	if nowParam := r.URL.Query().Get("now"); nowParam != "" {
		if t, err := time.ParseInLocation("2006-01-02 15:04:05", nowParam, tzRiga); err == nil {
			currentTime = t
		}
	}

	// Check HTML cache first
	cacheKey := htmlCacheKey{
		country:    country,
		resolution: resolution,
		withVAT:    withVAT,
		date:       currentTime.Format("2006-01-02"),
	}

	if cachedHTML, ok := getCachedHTML(cacheKey); ok {
		w.Header().Set("Content-Type", "text/html; charset=utf-8")
		w.Write([]byte(cachedHTML))
		totalDuration := time.Since(startTotal)
		log.Printf("Timing breakdown [Cache: HIT] [HTML: HIT] - Total: %v", totalDuration)
		return
	}

	// Create locale
	locale := NewLocale(country)

	// Query database (with caching)
	currentTimeCET := currentTime.In(tzCET)
	sqlTime := currentTimeCET.Format("2006-01-02 15:04:05")

	startQuery := time.Now()
	priceRows, fromCache, err := getCachedPrices(db, locale.Config.Code, resolution, sqlTime)
	if err != nil {
		http.Error(w, fmt.Sprintf("Database error: %s", err), http.StatusInternalServerError)
		return
	}
	queryDuration := time.Since(startQuery)

	// Process prices
	startProcessing := time.Now()
	prices := make(map[string]map[int]map[int]*float64)
	vat := locale.Config.VAT
	vatMultiplier := 1.0
	if withVAT {
		vatMultiplier = 1 + vat
	}

	for _, row := range priceRows {
		tsStart := row.tsStart
		value := row.value

		startTime, err := time.Parse("2006-01-02T15:04:05Z", tsStart)
		if err != nil {
			continue
		}
		startTime = startTime.In(tzRiga)

		hour := startTime.Hour()
		minute := startTime.Minute()
		quarter := 0
		if resolution == 15 {
			quarter = minute / 15
		}

		dateKey := startTime.Format("2006-01-02")
		if prices[dateKey] == nil {
			prices[dateKey] = make(map[int]map[int]*float64)
		}
		if prices[dateKey][hour] == nil {
			prices[dateKey][hour] = make(map[int]*float64)
		}

		price := math.Round(vatMultiplier*value/1000*10000) / 10000
		prices[dateKey][hour][quarter] = &price
	}
	processingDuration := time.Since(startProcessing)

	// Extract today and tomorrow
	todayKey := currentTime.Format("2006-01-02")
	tomorrowKey := currentTime.Add(24 * time.Hour).Format("2006-01-02")

	today := prices[todayKey]
	if today == nil {
		today = make(map[int]map[int]*float64)
	}
	tomorrow := prices[tomorrowKey]
	if tomorrow == nil {
		tomorrow = make(map[int]map[int]*float64)
	}

	// Calculate statistics
	todayFlat := flattenPrices(today, quartersPerHour)
	tomorrowFlat := flattenPrices(tomorrow, quartersPerHour)

	expectedCount := 24 * quartersPerHour
	var todayAvg, tomorrowAvg *float64
	if len(todayFlat) == expectedCount {
		avg := average(todayFlat)
		todayAvg = &avg
	}
	if len(tomorrowFlat) == expectedCount {
		avg := average(tomorrowFlat)
		tomorrowAvg = &avg
	}

	todayMin, todayMax := minMax(todayFlat)
	tomorrowMin, tomorrowMax := minMax(tomorrowFlat)

	// Build legend and values for chart
	legend := make([]string, 0)
	valuesJSON := make(map[string][]float64)
	valuesJSON["today"] = make([]float64, 0)
	valuesJSON["tomorrow"] = make([]float64, 0)

	for hour := 0; hour < 24; hour++ {
		for q := 0; q < quartersPerHour; q++ {
			if resolution == 15 {
				legend = append(legend, fmt.Sprintf("%02d:%02d", hour, q*15))
			} else {
				legend = append(legend, fmt.Sprintf("%02d:00", hour))
			}

			todayVal := 0.0
			if today[hour] != nil && today[hour][q] != nil {
				todayVal = *today[hour][q]
			}
			tomorrowVal := 0.0
			if tomorrow[hour] != nil && tomorrow[hour][q] != nil {
				tomorrowVal = *tomorrow[hour][q]
			}

			valuesJSON["today"] = append(valuesJSON["today"], todayVal)
			valuesJSON["tomorrow"] = append(valuesJSON["tomorrow"], tomorrowVal)
		}
	}

	// Add final point for chart continuity
	legend = append(legend, "00:00")
	finalVal := 0.0
	if tomorrow[0] != nil && tomorrow[0][0] != nil {
		finalVal = *tomorrow[0][0]
	}
	valuesJSON["today"] = append(valuesJSON["today"], finalVal)

	// Get good/bad colors
	goodColor, badColor := GetGoodBadColors()

	// Determine if we should show the notice
	showNotice := time.Now().Before(time.Date(2025, 10, 8, 0, 0, 0, 0, time.UTC))

	// Check if localhost
	isLocalhost := strings.HasPrefix(r.Host, "localhost")

	// Prepare page data
	pageData := PageData{
		Locale:          locale,
		CountryConfigs:  GetCountryConfigs(),
		WithVAT:         withVAT,
		Resolution:      resolution,
		QuartersPerHour: quartersPerHour,
		Today:           today,
		Tomorrow:        tomorrow,
		TodayAvg:        todayAvg,
		TomorrowAvg:     tomorrowAvg,
		TodayMin:        todayMin,
		TodayMax:        todayMax,
		TomorrowMin:     tomorrowMin,
		TomorrowMax:     tomorrowMax,
		CurrentTime:     currentTime,
		Legend:          legend,
		ValuesJSON:      valuesJSON,
		GoodColor:       goodColor,
		BadColor:        badColor,
		ShowNotice:      showNotice,
		IsLocalhost:     isLocalhost,
	}

	// Render template to buffer for caching
	startRender := time.Now()
	var buf bytes.Buffer
	if err := Index(pageData).Render(r.Context(), &buf); err != nil {
		log.Printf("Error rendering template: %s", err)
		http.Error(w, "Error rendering template", http.StatusInternalServerError)
		return
	}
	renderDuration := time.Since(startRender)

	// Store in HTML cache
	renderedHTML := buf.String()
	setCachedHTML(cacheKey, renderedHTML)

	// Write to response
	w.Header().Set("Content-Type", "text/html; charset=utf-8")
	w.Write([]byte(renderedHTML))

	totalDuration := time.Since(startTotal)

	// Log timing breakdown with cache status
	dbCacheStatus := "MISS"
	if fromCache {
		dbCacheStatus = "HIT"
	}
	log.Printf("Timing breakdown [DB Cache: %s] [HTML: MISS] - Query: %v, Processing: %v, Rendering: %v, Total: %v",
		dbCacheStatus, queryDuration, processingDuration, renderDuration, totalDuration)
}

func flattenPrices(day map[int]map[int]*float64, quartersPerHour int) []float64 {
	result := make([]float64, 0)
	for hour := 0; hour < 24; hour++ {
		for q := 0; q < quartersPerHour; q++ {
			if day[hour] != nil && day[hour][q] != nil {
				result = append(result, *day[hour][q])
			}
		}
	}
	return result
}

func average(values []float64) float64 {
	if len(values) == 0 {
		return 0
	}
	sum := 0.0
	for _, v := range values {
		sum += v
	}
	return sum / float64(len(values))
}

func minMax(values []float64) (float64, float64) {
	if len(values) == 0 {
		return 0, 0
	}
	min := values[0]
	max := values[0]
	for _, v := range values {
		if v < min {
			min = v
		}
		if v > max {
			max = v
		}
	}
	return min, max
}
