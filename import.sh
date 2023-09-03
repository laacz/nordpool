#!/bin/bash

end_date="2020-01-01"
if [ "$(uname -s)" == "Darwin" ]; then
  date=$(date -j +%Y-%m-%d)
else
  date=$(date +%Y-%m-%d)
fi

# Loop until the end date is reached
while true; do
    # Exit the loop if the date is before the end date
    if [[ "$date" < "$end_date" ]]; then
        break
    fi

    echo "$date"
    go run main.go "$date"

    # Subtract 7 days
    if [ "$(uname -s)" == "Darwin" ]; then
      date=$(date -j -v -7d -f "%Y-%m-%d" "$date" +%Y-%m-%d)
    else
      date=$(date -d "$date -7 day" +%Y-%m-%d)
    fi
done