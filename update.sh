./nordpool update >/dev/null && (
    ./nordpool update-indices
    ./nordpool csv >public/nordpool.csv.new
    ./nordpool excel >public/nordpool-excel.csv.new

    ./nordpool csv lt >public/nordpool-lt.csv.new
    ./nordpool csv ee >public/nordpool-ee.csv.new
    ./nordpool csv lv >public/nordpool-lv.csv.new

    ./nordpool csv lt 15 >public/nordpool-lt-15.csv.new
    ./nordpool csv ee 15 >public/nordpool-ee-15.csv.new
    ./nordpool csv lv 15 >public/nordpool-lv-15.csv.new

    ./nordpool excel lt >public/nordpool-lt-excel.csv.new
    ./nordpool excel ee >public/nordpool-ee-excel.csv.new
    ./nordpool excel lv >public/nordpool-lv-excel.csv.new

    ./nordpool excel lt 15 >public/nordpool-lt-excel-15.csv.new
    ./nordpool excel ee 15 >public/nordpool-ee-excel-15.csv.new
    ./nordpool excel lv 15 >public/nordpool-lv-excel-15.csv.new

    mv public/nordpool.csv.new public/nordpool.csv
    mv public/nordpool-excel.csv.new public/nordpool-excel.csv

    mv public/nordpool-lt.csv.new public/nordpool-lt.csv
    mv public/nordpool-ee.csv.new public/nordpool-ee.csv
    mv public/nordpool-lv.csv.new public/nordpool-lv.csv

    mv public/nordpool-lt-excel.csv.new public/nordpool-lt-excel.csv
    mv public/nordpool-ee-excel.csv.new public/nordpool-ee-excel.csv
    mv public/nordpool-lv-excel.csv.new public/nordpool-lv-excel.csv

    mv public/nordpool-lt-15.csv.new public/nordpool-lt-15.csv
    mv public/nordpool-ee-15.csv.new public/nordpool-ee-15.csv
    mv public/nordpool-lv-15.csv.new public/nordpool-lv-15.csv

    mv public/nordpool-lt-excel-15.csv.new public/nordpool-lt-excel-15.csv
    mv public/nordpool-ee-excel-15.csv.new public/nordpool-ee-excel-15.csv
    mv public/nordpool-lv-excel-15.csv.new public/nordpool-lv-excel-15.csv
)
