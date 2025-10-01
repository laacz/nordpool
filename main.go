package main

import (
	"database/sql"
	_ "embed"
	"encoding/json"
	"fmt"
	"io"
	"log"
	"net/http"
	"os"
	"strings"
	"time"

	_ "github.com/mattn/go-sqlite3"
)

func initDatabase(db *sql.DB) error {
	queries := []string{
		`CREATE TABLE IF NOT EXISTS spot_prices (
    		ts_start timestamp NOT NULL,
			ts_end timestamp NOT NULL,
			value decimal(10, 2) NOT NULL,
			country varchar(2) NOT NULL,
			created_at timestamp DEFAULT CURRENT_TIMESTAMP,
			UNIQUE(country, ts_start, ts_end)
		)`,
		`CREATE TABLE IF NOT EXISTS price_indices (
			ts_start timestamp NOT NULL,
			ts_end timestamp NOT NULL,
			value decimal(10, 2) NOT NULL,
			country varchar(2) NOT NULL,
			resolution_minutes integer NOT NULL,
			created_at timestamp DEFAULT CURRENT_TIMESTAMP,
			UNIQUE(country, ts_start, ts_end, resolution_minutes)
		)`,
	}

	for _, query := range queries {
		_, err := db.Exec(query)
		if err != nil {
			return fmt.Errorf("error executing query: %s, %w", query, err)
		}
	}

	return nil
}

func main() {
	db, err := sql.Open("sqlite3", "./nordpool.db")
	if err != nil {
		log.Fatalf("Error opening database: %s", err)
	}

	err = initDatabase(db)
	if err != nil {
		log.Fatalf("Error initializing database: %s", err)
	}

	country := "LV"
	resolution := 60
	if len(os.Args) > 1 {
		if os.Args[1] == "csv" {
			if len(os.Args) > 2 {
				country = strings.ToUpper(os.Args[2])
			}
			if len(os.Args) > 3 {
				fmt.Sscanf(os.Args[3], "%d", &resolution)
			}

			writeCsv(db, ",", country, resolution)
		} else if os.Args[1] == "excel" {
			if len(os.Args) > 2 {
				country = strings.ToUpper(os.Args[2])
			}
			if len(os.Args) > 3 {
				fmt.Sscanf(os.Args[3], "%d", &resolution)
			}

			writeCsv(db, ";", country, resolution)
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
		} else if os.Args[1] == "update-indices" {

			endDate, err := inferEndDate()
			if err != nil {
				log.Fatalf("Error parsing date: %s", err)
			}

			//log.Printf("Fetching data for %s", endDate.Format("2006-01-02"))

			for _, resolution := range []int{15, 60} {
				retry := 0
				var data []byte
				for retry < 5 {
					data, err = fetchAggregationData(endDate, resolution)
					if err == nil {
						break
					}
					retry++
					log.Printf("Error fetching aggregation data for %dmin resolution, retrying %d: %s", resolution, retry, err)
					time.Sleep(time.Second * 5)
				}
				if err != nil {
					log.Printf("Error fetching aggregation data for %dmin resolution: %s", resolution, err)
					continue
				}

				if len(data) == 0 {
					log.Printf("No data returned for %dmin resolution", resolution)
					continue
				}

				var aggregation AggregationData
				err = json.Unmarshal(data, &aggregation)
				if err != nil {
					log.Printf("Error parsing aggregation JSON for %dmin resolution: %s", resolution, err)
					continue
				}

				for _, country := range []string{"LV", "LT", "EE"} {
					prices := aggregation.Convert(country)
					for _, price := range prices {
						err = price.Store(db, country)
						if err != nil {
							log.Printf("Error storing aggregation price: %s", err)
						}
					}
				}
			}
		}
	} else {
		fmt.Println("Usage: nordpool [csv|excel|update|update-indices] [date]")
	}

}

func writeCsv(db *sql.DB, separator string, country string, resolution int) {
	res, err := db.Query("SELECT ts_start, ts_end, value FROM price_indices WHERE country = ? AND resolution_minutes = ? order by ts_start desc", country, resolution)
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

		price.EndTime, err = time.ParseInLocation("2006-01-02T15:04:05Z", tsEnd, time.UTC)
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

// AggregationData represents the JSON structure returned by the DayAheadPriceIndices API
type AggregationData struct {
	DeliveryDateCet     string   `json:"deliveryDateCET"`
	Version             int      `json:"version"`
	UpdatedAt           string   `json:"updatedAt"`
	Market              string   `json:"market"`
	IndexNames          []string `json:"indexNames"`
	Currency            string   `json:"currency"`
	ResolutionInMinutes int      `json:"resolutionInMinutes"`
	MultiIndexEntries   []struct {
		DeliveryStart string             `json:"deliveryStart"`
		DeliveryEnd   string             `json:"deliveryEnd"`
		EntryPerArea  map[string]float64 `json:"entryPerArea"`
	} `json:"multiIndexEntries"`
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

func (a *AggregationData) Convert(country string) []AggregationPrice {
	ret := make([]AggregationPrice, 0)
	for _, entry := range a.MultiIndexEntries {
		price := entry.EntryPerArea[country]

		startTime, err := time.Parse("2006-01-02T15:04:05Z", entry.DeliveryStart)
		if err != nil {
			log.Panicf("Error parsing start time: %s", err)
		}
		endTime, err := time.Parse("2006-01-02T15:04:05Z", entry.DeliveryEnd)
		if err != nil {
			log.Panicf("Error parsing end time: %s", err)
		}
		ret = append(ret, AggregationPrice{
			StartTime:           startTime,
			EndTime:             endTime,
			Price:               price,
			ResolutionInMinutes: a.ResolutionInMinutes,
		})
	}
	return ret
}

type SpotPrice struct {
	StartTime time.Time
	EndTime   time.Time
	Price     float64
}

type AggregationPrice struct {
	StartTime           time.Time
	EndTime             time.Time
	Price               float64
	ResolutionInMinutes int
}

// Store stores a SpotPrice in the database, ignoring existing entries
func (price *SpotPrice) Store(db *sql.DB, country string) error {
	//fmt.Println(country, price)
	_, err := db.Exec("INSERT OR IGNORE INTO spot_prices (ts_start, ts_end, value, country) VALUES (?, ?, ?, ?)", price.StartTime, price.EndTime, price.Price, country)
	if err != nil {
		return err
	}
	return nil
}

// Store stores an AggregationPrice in the database, ignoring existing entries
func (price *AggregationPrice) Store(db *sql.DB, country string) error {
	//fmt.Println(country, price)
	_, err := db.Exec("INSERT OR IGNORE INTO price_indices (ts_start, ts_end, value, country, resolution_minutes) VALUES (?, ?, ?, ?, ?)",
		price.StartTime, price.EndTime, price.Price, country, price.ResolutionInMinutes)
	if err != nil {
		return err
	}
	return nil
}

// inferEndDate infers the end date from the command line arguments, or returns tomorrow
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

// makeHTTPRequest creates a common HTTP request with standard headers
func makeHTTPRequest(url string) ([]byte, error) {
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

// fetch fetches the data from the Nordpool API
func fetch(endDate *time.Time) ([]byte, error) {
	url := fmt.Sprintf("https://dataportal-api.nordpoolgroup.com/api/DayAheadPrices?date=%s&market=DayAhead&deliveryArea=LV,EE,LT&currency=EUR", endDate.Format("2006-01-02"))
	return makeHTTPRequest(url)
}

// fetchAggregationData fetches data from the DayAheadPriceIndices API for all countries
func fetchAggregationData(endDate *time.Time, resolutionMinutes int) ([]byte, error) {
	url := fmt.Sprintf("https://dataportal-api.nordpoolgroup.com/api/DayAheadPriceIndices?date=%s&market=DayAhead&indexNames=LV,LT,EE&currency=EUR&resolutionInMinutes=%d",
		endDate.Format("2006-01-02"), resolutionMinutes)
	return makeHTTPRequest(url)
}
