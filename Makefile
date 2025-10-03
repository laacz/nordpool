NAME = nordpool

build:
	templ generate && go build -o nordpool
