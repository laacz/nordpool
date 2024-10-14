./nordpool update && \
    ./nordpool csv >public/nordpool.csv.new && \
    ./nordpool excel >public/nordpool-excel.csv.new && \
    mv public/nordpool.csv.new public/nordpool.csv && \
    mv public/nordpool-excel.csv.new public/nordpool-excel.csv

