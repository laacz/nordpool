./nordpool update && \
    ./nordpool generate >public/index.html.new && \
    mv public/index.html.new public/index.html && \
    ./nordpool csv >public/nordpool.csv.new && \
    ./nordpool excel >public/nordpool-excel.csv.new && \
    mv public/nordpool.csv.new public/nordpool.csv && \
    mv public/nordpool-excel.csv.new public/nordpool-excel.csv
