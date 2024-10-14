package main

import (
	"database/sql"
	_ "embed"
	"encoding/json"
	"fmt"
	_ "github.com/mattn/go-sqlite3"
	"io"
	"log"
	"math"
	"net/http"
	"os"
	"strconv"
	"strings"
	"text/template"
	"time"
)

func main() {
	db, err := sql.Open("sqlite3", "./nordpool.db")
	if err != nil {
		log.Fatalf("Error opening database: %s", err)
	}

	if len(os.Args) > 1 {
		if os.Args[1] == "generate" {
			today, tomorrow, err := latestData(db)
			if err != nil {
				log.Fatalf("Error fetching latest data: %s", err)
			}
			writeHtml(today, tomorrow)
		} else if os.Args[1] == "csv" {
			writeCsv(db, ",")
		} else if os.Args[1] == "excel" {
			writeCsv(db, ";")
		} else if os.Args[1] == "update" {

			endDate, err := inferEndDate()
			if err != nil {
				log.Fatalf("Error parsing date: %s", err)
			}

			data, err := fetch(endDate)
			if err != nil {
				log.Fatalf("Error fetching data: %s", err)
			}

			var nordpool nordpoolData2
			err = json.Unmarshal(data, &nordpool)
			if err != nil {
				log.Fatalf("Error parsing JSON: %s", err)
			}

			prices := nordpool.Convert()
			for _, price := range prices {
				err = price.Store(db)
				if err != nil {
					log.Printf("Error storing price: %s", err)
				}
			}
		}
	} else {
		fmt.Println("Usage: nordpool [generate|csv|excel|update] [date]")
	}

}

func writeCsv(db *sql.DB, separator string) {
	res, err := db.Query("SELECT ts_start, ts_end, value FROM spot_prices  order by ts_start desc")
	if err != nil {
		log.Fatalf("Error querying database: %s", err)
	}
	defer func(rows *sql.Rows) {
		_ = rows.Close()
	}(res)
	loc, _ := time.LoadLocation("Europe/Riga")
	fmt.Printf(strings.Replace("ts_start|ts_end|price\n", "|", separator, -1))
	for res.Next() {
		var price SpotPrice
		var tsStart string
		var tsEnd string

		err = res.Scan(&tsStart, &tsEnd, &price.Price)
		if err != nil {
			log.Fatalf("Error scanning row: %s", err)
		}
		price.Price /= 1000

		price.StartTime, err = time.ParseInLocation("2006-01-02T15:04:05Z", tsStart, time.UTC)
		if err != nil {
			log.Fatalf("Error parsing start time: %s", err)
		}

		price.EndTime, err = time.ParseInLocation("2006-01-02 15:04:05-07:00", tsEnd, time.UTC)
		if err != nil {
			log.Fatalf("Error parsing end time: %s", err)
		}

		fmt.Printf(strings.Replace("%s|%s|%f\n", "|", separator, -1),
			price.StartTime.In(loc).Format("2006-01-02 15:04:05"),
			price.EndTime.In(loc).Format("2006-01-02 15:04:05"),
			price.Price,
		)
	}
}

// nordpoolData is a specific part of the JSON structure returned by the Nordpool API (new version)
type nordpoolData2 struct {
	DeliveryDateCet  string `json:"deliveryDateCET"`
	Version          int    `json:"version"`
	UpdatedAt        string `json:"updatedAt"`
	Market           string `json:"market"`
	MultiAreaEntries []struct {
		DeliveryStart string             `json:"deliveryStart"`
		DeliveryEnd   string             `json:"deliveryEnd"`
		EntryPerArea  map[string]float64 `json:"entryPerArea"`
	} `json:"multiAreaEntries"`
}

func (n *nordpoolData2) Convert() []SpotPrice {
	ret := make([]SpotPrice, 0)
	for _, entry := range n.MultiAreaEntries {
		price := entry.EntryPerArea["LV"]
		startTime, err := time.Parse("2006-01-02T15:04:05Z", entry.DeliveryStart)
		if err != nil {
			log.Panicf("Error parsing start time: %s", err)
		}
		endTime, err := time.Parse("2006-01-02T15:04:05Z", entry.DeliveryEnd)
		if err != nil {
			log.Panicf("Error parsing end time: %s", err)
		}
		ret = append(ret, SpotPrice{
			StartTime: startTime,
			EndTime:   endTime,
			Price:     float64(price) / 1000,
		})
	}
	return ret
}

type SpotPrice struct {
	StartTime time.Time
	EndTime   time.Time
	Price     float64
}

type SpotDay struct {
	Date   time.Time
	Prices []SpotPrice
	Min    float64
	Max    float64
	Avg    float64
}

func (sd *SpotDay) UpdateAggregates() {
	sd.Min = math.MaxFloat64
	sd.Max = -math.MaxFloat64
	var sum float64
	for _, price := range sd.Prices {
		if price.Price < sd.Min {
			sd.Min = price.Price
		}
		if price.Price > sd.Max {
			sd.Max = price.Price
		}
		sum += price.Price
	}
	sd.Avg = sum / float64(len(sd.Prices))
	//fmt.Printf("D: avg: %f, max: %f, min: %f\n", sd.Avg, sd.Max, sd.Min)
}

func (sd SpotDay) HourlyPrice(hour int) *float64 {
	for _, price := range sd.Prices {
		if price.StartTime.Hour() == hour {
			p := price.Price
			//p := price.Price
			return &p
		}
	}

	return nil
}

func (sd SpotDay) HourlyPriceVat(hour int) *float64 {
	p := sd.HourlyPrice(hour)
	if p != nil {
		ret := *p * 1.21
		return &ret
	}
	return p
}

func (sd SpotDay) HourtlyPriceAsColor(hour int) string {
	price := sd.HourlyPrice(hour)

	if price == nil {
		return "#fff"
	}

	fmt.Println()
	var pct float64
	if (sd.Max - sd.Min) == 0 {
		pct = 0
	} else {
		pct = (*price - sd.Min) / (sd.Max - sd.Min)
	}

	percentColors := []struct {
		Pct   float64
		Color []int
	}{
		{Pct: 0.0, Color: []int{0x00, 0xff, 0}},
		{Pct: 0.5, Color: []int{0xff, 0xff, 0}},
		{Pct: 1.0, Color: []int{0xff, 0x00, 0}},
	}
	var i int
	for i = 1; i < len(percentColors)-1; i++ {
		if pct < percentColors[i].Pct {
			break
		}
	}

	//fmt.Printf("D: i: %d\n", i)

	lower := percentColors[i-1]
	upper := percentColors[i]
	rng := upper.Pct - lower.Pct
	rngPct := (pct - lower.Pct) / rng
	pctLower := 1 - rngPct
	pctUpper := rngPct
	r := int(math.Floor(float64(lower.Color[0])*pctLower + float64(upper.Color[0])*pctUpper))
	g := int(math.Floor(float64(lower.Color[1])*pctLower + float64(upper.Color[1])*pctUpper))
	b := int(math.Floor(float64(lower.Color[2])*pctLower + float64(upper.Color[2])*pctUpper))

	return fmt.Sprintf("rgb(%d,%d,%d)", r, g, b)
}

// Store stores a SpotPrice in the database, ignoring existing entries
func (prices *SpotPrice) Store(db *sql.DB) error {
	_, err := db.Exec("INSERT OR IGNORE INTO spot_prices (ts_start, ts_end, value) VALUES (?, ?, ?)", prices.StartTime, prices.EndTime, prices.Price)
	if err != nil {
		return err
	}
	return nil
}

// inferEndDate infers the end date from the command line arguments, or defaults to tomorrow
func inferEndDate() (*time.Time, error) {
	var err error
	ret := time.Now().UTC().Add(time.Hour * 24)
	if len(os.Args) > 2 {
		ret, err = time.Parse("2006-01-02", os.Args[2])
		if err != nil {
			return nil, err
		}
	}

	ret = ret.Truncate(time.Hour * 24)
	return &ret, nil
}

// fetch fetches the data from the Nordpool API
func fetch(endDate *time.Time) ([]byte, error) {
	// https://dataportal-api.nordpoolgroup.com/api/DayAheadPrices?date=2024-10-15&market=DayAhead&deliveryArea=LV&currency=EUR
	// url := fmt.Sprintf("https://www.nordpoolgroup.com/api/marketdata/page/59?currency=,EUR,EUR,EUR&endDate=%s", endDate.Format("02-01-2006"))
	url := fmt.Sprintf("https://dataportal-api.nordpoolgroup.com/api/DayAheadPrices?date=%s&market=DayAhead&deliveryArea=LV&currency=EUR", endDate.Format("2006-01-02"))

	c := &http.Client{}
	req, err := http.NewRequest("GET", url, nil)
	if err != nil {
		return nil, err
	}

	req.Header.Set("Accept", "application/json")
	req.Header.Set("Referer", "https://www.nordpoolgroup.com/Market-data1/Dayahead/Area-Prices/ALL1/Hourly/")
	req.Header.Set("User-Agent", "Mozilla/5.0 (X11; Linux x86_64; rv:60.0) Gecko/20100101 Firefox/60.0")

	resp, err := c.Do(req)
	if err != nil {
		return nil, err
	}

	defer func(Body io.ReadCloser) {
		_ = Body.Close()
	}(resp.Body)

	return io.ReadAll(resp.Body)
}

// latestData returns data for today and tomorrow from the database
func latestData(db *sql.DB) (*SpotDay, *SpotDay, error) {
	res, err := db.Query("SELECT ts_start, ts_end, value FROM spot_prices WHERE ts_start >= date('now', '-1 days') AND ts_start < date('now', '+2 days') order by ts_start")
	if err != nil {
		return nil, nil, err
	}
	defer func(rows *sql.Rows) {
		_ = rows.Close()
	}(res)
	today := &SpotDay{
		Prices: make([]SpotPrice, 0),
	}
	tomorrow := &SpotDay{
		Prices: make([]SpotPrice, 0),
	}
	utcLoc := time.Now().UTC().Location()
	rigaLoc := time.Now().Location()
	for res.Next() {
		var price SpotPrice
		var tsStart string
		var tsEnd string

		err = res.Scan(&tsStart, &tsEnd, &price.Price)
		if err != nil {
			return nil, nil, err
		}
		price.Price /= 1000

		price.StartTime, err = time.ParseInLocation("2006-01-02T15:04:05Z", tsStart, utcLoc)
		if err != nil {
			panic(err)
			return nil, nil, err
		}

		price.EndTime, err = time.ParseInLocation("2006-01-02 15:04:05-07:00", tsEnd, utcLoc)
		if err != nil {
			panic(err)
			return nil, nil, err
		}

		price.StartTime = price.StartTime.In(rigaLoc)
		price.EndTime = price.EndTime.In(rigaLoc)

		if price.StartTime.Format("2006-01-02") == time.Now().Format("2006-01-02") {
			today.Date = price.StartTime.Truncate(time.Hour * 24)
			today.Prices = append(today.Prices, price)
		} else if price.StartTime.Format("2006-01-02") == time.Now().Add(time.Hour*24).Format("2006-01-02") {
			tomorrow.Date = price.StartTime.Truncate(time.Hour * 24)
			tomorrow.Prices = append(tomorrow.Prices, price)
		}
	}
	today.UpdateAggregates()
	tomorrow.UpdateAggregates()
	//fmt.Printf("D: today: %s, tomorrow: %s\n", today.Date, tomorrow.Date)
	//fmt.Printf("D: D avg: %f, max: %f, min: %f\n", today.Avg, today.Max, today.Min)

	return today, tomorrow, nil
}

//go:embed template.html
var html string

func writeHtml(today, tomorrow *SpotDay) {
	funcs := template.FuncMap{
		"ffformat": func(f float64) string {
			str := fmt.Sprintf("%.4f", f)
			pointPos := strings.Index(str, ".")
			return str[0:pointPos+3] + "<span class=\"extra-decimals\">" + str[pointPos+3:] + "</span>"
		},
		"fformat": func(f *float64) string {
			if f == nil {
				return "-"
			}
			str := fmt.Sprintf("%.4f", *f)
			pointPos := strings.Index(str, ".")
			return str[0:pointPos+3] + "<span class=\"extra-decimals\">" + str[pointPos+3:] + "</span>"
		},
		"inc": func(i int) int {
			ret := i + 1
			if ret == 24 {
				return 0
			}
			return ret
		},
		"lpad": func(i int) string {
			if i < 10 {
				return "0" + strconv.Itoa(i)
			}
			return strconv.Itoa(i)
		},
	}
	tmpl, err := template.New("html").Funcs(funcs).Parse(html)
	if err != nil {
		log.Fatalf("Error parsing template: %s", err)
	}
	err = tmpl.Execute(os.Stdout, struct {
		Today    SpotDay
		Tomorrow SpotDay
		Months   []string
		Hours    []int
	}{
		Today:    *today,
		Tomorrow: *tomorrow,
		Months: []string{
			"",
			"jan",
			"feb",
			"mar",
			"apr",
			"mai",
			"jūn",
			"jūl",
			"aug",
			"sep",
			"okt",
			"nov",
			"dec",
		},
		Hours: []int{
			0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23,
		},
	})

	if err != nil {
		log.Fatalf("Error executing template: %s", err)
	}

}
