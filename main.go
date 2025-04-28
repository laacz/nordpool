package main

import (
	"database/sql"
	_ "embed"
	"encoding/json"
	"fmt"
	"io"
	"log"
	"math"
	"net/http"
	"os"
	"strings"
	"time"

	_ "github.com/mattn/go-sqlite3"
)

func main() {
	db, err := sql.Open("sqlite3", "./nordpool.db")
	if err != nil {
		log.Fatalf("Error opening database: %s", err)
	}

	if len(os.Args) > 1 {
		if os.Args[1] == "csv" {
			country := "LV"
			if len(os.Args) > 2 {
				country = strings.ToUpper(os.Args[2])
			}

			writeCsv(db, ",", country)
		} else if os.Args[1] == "excel" {
			country := "LV"
			if len(os.Args) > 2 {
				country = strings.ToUpper(os.Args[2])
			}

			writeCsv(db, ";", country)
		} else if os.Args[1] == "update" {

			endDate, err := inferEndDate()
			if err != nil {
				log.Fatalf("Error parsing date: %s", err)
			}

			retry := 0
			var data []byte
			for retry < 5 {
				data, err = fetch(endDate)
				if err == nil {
					break
				}
				retry++
				log.Printf("Error fetching data, retrying %d: %s", retry, err)
				time.Sleep(time.Second * 5)
			}
			if err != nil {
				log.Fatalf("Error fetching data: %s", err)
			}

			if len(data) == 0 {
				return
			}

			var nordpool NordpoolData
			err = json.Unmarshal(data, &nordpool)
			if err != nil {
				log.Fatalf("Error parsing JSON: %s", err)
			}

			for _, country := range []string{"LV", "LT", "EE"} {
				prices := nordpool.Convert(country)
				for _, price := range prices {
					err = price.Store(db, country)
					if err != nil {
						log.Printf("Error storing price: %s", err)
					}
				}
			}
		}
	} else {
		fmt.Println("Usage: nordpool [csv|excel|update] [date]")
	}

}

func writeCsv(db *sql.DB, separator string, country string) {
	res, err := db.Query("SELECT ts_start, ts_end, value FROM spot_prices WHERE country = ? order by ts_start desc", country)
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

// NordpoolData is a specific part of the JSON structure returned by the Nordpool API (new version)
type NordpoolData struct {
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

func (n *NordpoolData) Convert(country string) []SpotPrice {
	ret := make([]SpotPrice, 0)
	for _, entry := range n.MultiAreaEntries {
		price := entry.EntryPerArea[country]

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
			Price:     price,
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
func (price *SpotPrice) Store(db *sql.DB, country string) error {
	fmt.Println(country, price)
	_, err := db.Exec("INSERT OR IGNORE INTO spot_prices (ts_start, ts_end, value, country) VALUES (?, ?, ?, ?)", price.StartTime, price.EndTime, price.Price, country)
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

	// ```command
	// curl 'https://dataportal-api.nordpoolgroup.com/api/DayAheadPrices?date=2025-04-01&market=DayAhead&deliveryArea=EE,LT,AT&currency=EUR' \
	// -H 'accept: application/json, text/plain, */*' \
	// -H 'accept-language: en,lv;q=0.9,en-GB;q=0.8' \
	// -H 'origin: https://data.nordpoolgroup.com' \
	// -H 'priority: u=1, i' \
	// -H 'referer: https://data.nordpoolgroup.com/' \
	// -H 'sec-ch-ua: "Google Chrome";v="135", "Not-A.Brand";v="8", "Chromium";v="135"' \
	// -H 'sec-ch-ua-mobile: ?0' \
	// -H 'sec-ch-ua-platform: "Windows"' \
	// -H 'sec-fetch-dest: empty' \
	// -H 'sec-fetch-mode: cors' \
	// -H 'sec-fetch-site: same-site' \
	// -H 'user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36'
	// ```
	url := fmt.Sprintf("https://dataportal-api.nordpoolgroup.com/api/DayAheadPrices?date=%s&market=DayAhead&deliveryArea=LV,EE,LT&currency=EUR", endDate.Format("2006-01-02"))

	c := &http.Client{}
	req, err := http.NewRequest("GET", url, nil)
	if err != nil {
		return nil, err
	}

	req.Header.Set("Accept", "application/json")
	req.Header.Set("Accept-Language", "en,lv;q=0.9,en-GB;q=0.8")
	req.Header.Set("Origin", "https://data.nordpoolgroup.com")
	req.Header.Set("Priority", "u=1, i")
	req.Header.Set("Referer", "https://www.nordpoolgroup.com/Market-data1/Dayahead/Area-Prices/ALL1/Hourly/")
	req.Header.Set("Sec-CH-UA", `"Google Chrome";v="135", "Not-A.Brand";v="8", "Chromium";v="135"`)
	req.Header.Set("Sec-CH-UA-Mobile", "?0")
	req.Header.Set("Sec-CH-UA-Platform", `"Windows"`)
	req.Header.Set("Sec-Fetch-Dest", "empty")
	req.Header.Set("Sec-Fetch-Mode", "cors")
	req.Header.Set("User-Agent", "Mozilla/5.0 (X11; Linux x86_64; rv:60.0) Gecko/20100101 Firefox/60.0")

	resp, err := c.Do(req)
	if err != nil {
		return nil, err
	}

	if resp.StatusCode > 299 {
		return nil, fmt.Errorf("HTTP error: %d", resp.StatusCode)
	}

	defer func(Body io.ReadCloser) {
		_ = Body.Close()
	}(resp.Body)

	return io.ReadAll(resp.Body)
}
