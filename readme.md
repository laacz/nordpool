# Nordpool spot prices for Latvia

This repository contains a script that downloads Nordpool spot prices for Latvia and saves them in a sqlite3 database. After each update it generates static HTML and CSV files with the latest data.

```bash
# build new binary as ./nordpool
make build
```

Daily usage pattern (essentially - what [update.sh](update.sh) does)

Cron configuration:

```cronexp
*/15 11,12,13,14,15,16,17,18 * * * cd /var/www/nordpool.didnt.work && ./import.sh
```

Sqlite database schema:

```sql
CREATE TABLE spot_prices
(
    ts_start   timestamp      not null,
    ts_end     timesamp       not null,
    value      decimal(10, 2) not null,
    created_at timestamp default current_timestamp
);
CREATE UNIQUE INDEX index_ts ON spot_prices (ts_start, ts_end);
```
