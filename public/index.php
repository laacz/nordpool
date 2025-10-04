<?php
$ret = require 'functions.php';
if (!$ret) {
    return false;
}

$request = new Request;
$countryConfig = getCountryConfig();
$translations = getTranslations();

$parts = explode('/', $request->path());
$country = strtoupper($parts[0] ?? 'lv');
if (!isset($countryConfig[$country])) {
    $country = 'LV';
}

$locale = new AppLocale($countryConfig[$country], $translations);

$tz_local = new DateTimeZone($locale->get('timezone'));

$local_start = new DateTimeImmutable('today', $tz_local);
$local_tomorrow_start = new DateTimeImmutable('tomorrow', $tz_local);
$local_tomorrow_end = $local_start->modify('+2 day');

$current_time = new DateTimeImmutable($request->get('now', 'now'), $tz_local);

$resolution = $request->get('res') == '60' ? 60 : 15;

/** @var float $vat */
$vat = $locale->get('vat');

$viewHelper = new ViewHelper();

if ($request->has('rss')) {
    $DB = new PDO('sqlite:../nordpool.db');
    $priceRepo = new PriceRepository($DB);

    // Always fetch 15min data, include both resolutions in RSS
    $data = $priceRepo->getPrices($local_tomorrow_start, $local_tomorrow_end, strtoupper($country), 15);
    if (count($data) < 5) {
        $data = [];
    }

    header('Content-Type: application/xml; charset=utf-8');
    ?>
    <feed>
        <title type="text">Nordpool spot prices tomorrow (<?=$local_tomorrow_start->format('Y-m-d')?>)
            for <?=$country?></title>
        <updated><?=$current_time->format('Y-m-d\TH:i:sP')?></updated>
        <link rel="alternate" type="text/html" href="https://nordpool.didnt.work"/>
        <id>https://nordpool.didnt.work/feed</id>
        <?php foreach ($data as $price) {
            $ts_start = $price->startDate->setTimezone($tz_local);
            $ts_end = $price->endDate->setTimezone($tz_local);
            ?>
            <entry>
                <id><?=$country . '-' . $price->resolution . '-' . $ts_start->getTimestamp() . '-' . $ts_end->getTimestamp()?></id>
                <ts_start><?=$ts_start->format('Y-m-d\TH:i:sP')?></ts_start>
                <ts_end><?=$ts_end->format('Y-m-d\TH:i:sP')?></ts_end>
                <resolution><?=$price->resolution?></resolution>
                <price><?=htmlspecialchars($price->price)?></price>
                <price_vat><?=htmlspecialchars($price->price * (1 + $vat))?></price_vat>
            </entry>
        <?php } ?>
    </feed>
    <?php

    return;
}

$mtime = stat('../nordpool.db')['mtime'] ?? 0;
$cmtime = Cache::get('last_db_mtime', 0);

if ($cmtime === 0 || $mtime === 0 || (int)$mtime !== (int)$cmtime || $request->has('purge')) {
    Cache::clear();
    Cache::set('last_db_mtime', $mtime);
}

$prices = [];

$with_vat = $request->has('vat');
$quarters_per_hour = $resolution == 15 ? 4 : 1;

$cache_key = 'prices_' . $locale->get('code') . '_' . $current_time->format('Ymd_Hi') . '_' . ($with_vat ? 'vat' : 'novat') . '_' . $resolution;

$html = Cache::get($cache_key);

if (!ob_start('ob_gzhandler')) {
    ob_start();
}
if ($html) {
    header('X-Cache: HIT');
    echo $html;
    exit;
}

$DB = new PDO('sqlite:../nordpool.db');
$priceRepo = new PriceRepository($DB);

// Always fetch 15min data - compute hourly on the fly if needed
$rows = $priceRepo->getPrices($local_start, $local_tomorrow_end, $locale->get('code'), 15);

$collection = new PriceCollection($rows);
$prices = $collection->toGrid($tz_local, $resolution === 60, $with_vat ? 1 + $vat : 1);

$today = $prices[$current_time->format('Y-m-d')] ?? [];
$tomorrow = $prices[$current_time->modify('+1 day')->format('Y-m-d')] ?? [];

// Flatten for statistics
$today_flat = $today ? array_merge(...array_map('array_values', $today)) : [];
$tomorrow_flat = $tomorrow ? array_merge(...array_map('array_values', $tomorrow)) : [];

$expected_count = $resolution === 60 ? 24 : 96;
$today_avg = count($today_flat) === $expected_count ? array_sum($today_flat) / count($today_flat) : null;
$tomorrow_avg = count($tomorrow_flat) === $expected_count ? array_sum($tomorrow_flat) / count($tomorrow_flat) : null;

$today_max = $today_flat ? max($today_flat) : 0;
$today_min = $today_flat ? min($today_flat) : 0;
$tomorrow_max = $tomorrow_flat ? max($tomorrow_flat) : 0;
$tomorrow_min = $tomorrow_flat ? min($tomorrow_flat) : 0;

$now_hour = (int)$current_time->format('H');
$now_quarter = (int)((int)$current_time->format('i') / 15);

foreach ($prices as $k => $day) {
    ksort($prices[$k]);
}

$hours = array_keys($today);
asort($hours);

?>
    <!doctype html>
    <html lang="<?=strtolower($locale->get('code_lc'))?>">

    <head>
        <!-- Google tag (gtag.js) -->
        <script async src="https://www.googletagmanager.com/gtag/js?id=G-CRFT0MS7XN"></script>
        <script>
            window.dataLayer = window.dataLayer || [];

            function gtag() {
                dataLayer.push(arguments);
            }

            gtag('js', new Date());
            gtag('config', 'G-CRFT0MS7XN');
        </script>
        <meta charset="UTF-8">
        <meta name="viewport"
              content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
        <meta http-equiv="X-UA-Compatible" content="ie=edge">
        <title><?=$locale->msg('title')?></title>
        <link rel="alternate" type="application/rss+xml" title="nordpool.didnt.work RSS feed"
              href="https://nordpool.didnt.work/<?=strtolower($country)?>?rss"/>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Fira+Sans:wght@100;400;700&display=swap" rel="stylesheet">
        <style>
            <?php $legendColors = $viewHelper->getLegendColors(); ?>
            .good {
                background-color: rgb(<?=$legendColors[0]['color']['r']?>, <?=$legendColors[0]['color']['g']?>, <?=$legendColors[0]['color']['b']?>);
                color: #fff;
            }

            .bad {
                background-color: rgb(<?=$legendColors[2]['color']['r']?>, <?=$legendColors[2]['color']['g']?>, <?=$legendColors[2]['color']['b']?>);
                color: #fff;
            }

        </style>
        <script src="/echarts.min.js"></script>
        <link rel="stylesheet" href="/style.css">
    </head>

    <body<?=$resolution == 60 ? ' class="res-60"' : ''?>>

    <div id="app">

        <header>
            <h1>
                🔌🏷️ <br/>€/kWh
            </h1>
            <p>
                <?php foreach ($countryConfig as $code => $config) { ?>
                    <a class="flag" href="/<?=$config['code_lc'] === 'lv' ? '' : $config['code_lc']?>"><img
                                src="/<?=$config['code_lc']?>.svg" alt="<?=$config['name']?>" width="32"
                                height="32"/></a>
                <?php } ?>
                <br/>
                <?=$locale->msg('subtitle')?><br/>
                <?php if ($with_vat) { ?>
                    <?=$locale->msg('it is with VAT')?> <?=round($vat * 100)?>% (<a
                            href="<?=$locale->route('/') . ($resolution == 60 ? '?res=60' : '')?>"><?=$locale->msg('show without VAT')?></a>)
                <?php } else { ?>
                    <?=$locale->msg('it is without VAT')?> <?=round($vat * 100)?>% (<a
                            href="<?=$locale->route('/?vat') . ($resolution == 60 ? '&res=60' : '')?>"><?=$locale->msg('show with VAT')?></a>)
                <?php } ?>
                <br/>
                <?php if ($resolution == 15) { ?>
                    <?=$locale->msg('Resolution')?>: <strong>15min</strong> (<a
                            href="<?=$locale->route('/' . ($with_vat ? '?vat&res=60' : '?res=60'))?>"><?=$locale->msg('show 1h')?></a>)
                <?php } else { ?>
                    <?=$locale->msg('Resolution')?>: <strong>1h</strong> (<a
                            href="<?=$locale->route('/' . ($with_vat ? '?vat' : ''))?>"><?=$locale->msg('show 15min')?></a>)
                <?php } ?>
            </p>
        </header>

        <?php if (date('Y-m-d') < '2025-10-08') { ?>
            <div class="notice">
                <p><?=$locale->msg('15min notice')?></p>
            </div>
        <?php } ?>


        <?php if (!str_starts_with($_SERVER['HTTP_HOST'] ?? '', 'localhost') && $locale->get('code') === 'LV' && !isset($_GET['no-ads'])) { ?>
            <script async
                    src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-4590024878280519"
                    crossorigin="anonymous"></script>
            <!-- nordpool header -->
            <ins class="adsbygoogle" style="display:block" data-ad-client="ca-pub-4590024878280519"
                 data-ad-slot="9106834831" data-ad-format="auto" data-full-width-responsive="true"></ins>
            <script>
                (adsbygoogle = window.adsbygoogle || []).push({});
            </script>
        <?php } ?>

        <!-- Desktop table -->
        <table class="desktop-table">
            <thead>
            <tr>
                <th></th>
                <th colspan="<?=$quarters_per_hour?>"><?=$locale->msg('Šodien')?>
                    <span class="help"><?=$locale->formatDate($current_time, 'd. MMM')?></span><br/>
                    <small><?=$locale->msg('Vidēji')?> <span><?=$today_avg ? $viewHelper->format($today_avg) : '—'?></span></small>
                </th>
                <th colspan="<?=$quarters_per_hour?>"><?=$locale->msg('Rīt')?>
                    <span
                            class="help"><?=$locale->formatDate($current_time->modify('+1 day'), 'd. MMM')?></span><br/>
                    <small><?=$locale->msg('Vidēji')?>
                        <span><?=$tomorrow_avg ? $viewHelper->format($tomorrow_avg) : '—'?></span></small>
                </th>
            </tr>
            <tr>
                <th>🕑</th>
                <?php if ($resolution == 15) { ?>
                    <th>:00</th>
                    <th>:15</th>
                    <th>:30</th>
                    <th>:45</th>
                    <th>:00</th>
                    <th>:15</th>
                    <th>:30</th>
                    <th>:45</th>
                <?php } else { ?>
                    <th>:00</th>
                    <th>:00</th>
                <?php } ?>
            </tr>
            </thead>
            <tbody>
            <?php
            // Build legend and values arrays first
            $legend = [];
            $values['today'] = [];
            $values['tomorrow'] = [];
            for ($hour = 0; $hour < 24; $hour++) {
                for ($q = 0; $q < $quarters_per_hour; $q++) {
                    if ($resolution == 15) {
                        $legend[] = sprintf('%02d:%02d', $hour, $q * 15);
                    } else {
                        $legend[] = sprintf('%02d:00', $hour);
                    }
                    $values['today'][] = $today[$hour][$q] ?? 0;
                    $values['tomorrow'][] = $tomorrow[$hour][$q] ?? 0;
                }
            }
            ?>
            <?php for ($hour = 0; $hour < 24; $hour++) {
                $hour_label = sprintf('%02d', $hour) . '-' . sprintf('%02d', ($hour + 1) % 24);
                ?>
                <tr data-hours="<?=$hour?>">
                    <th><?=$hour_label?></th>
                    <?php
                    // Process quarters for today with colspan logic
                    $q = 0;
                    while ($q < $quarters_per_hour) {
                        $value = $today[$hour][$q] ?? null;

                        // Count consecutive quarters with same value
                        $colspan = 1;
                        $next_q = $q + 1;
                        while ($next_q < $quarters_per_hour) {
                            $next_value = $today[$hour][$next_q] ?? null;
                            if ($value !== null && $next_value !== null && $value === $next_value) {
                                $colspan++;
                                $next_q++;
                            } else {
                                break;
                            }
                        }
                        ?>
                        <td class="price today quarter-<?=$q?>" data-quarter="<?=$q?>"
                            <?php if ($colspan > 1) { ?>colspan="<?=$colspan?>"<?php } ?>
                            style="background-color: <?=$viewHelper->getColorPercentage($value ?? -9999, $today_min, $today_max)?>">
                            <?=isset($value) ? $viewHelper->format($value) : '-'?>
                        </td>
                        <?php
                        $q = $next_q;
                    }
                    ?>
                    <?php
                    // Process quarters for tomorrow with colspan logic
                    $q = 0;
                    while ($q < $quarters_per_hour) {
                        $value = $tomorrow[$hour][$q] ?? null;

                        // Count consecutive quarters with same value
                        $colspan = 1;
                        $next_q = $q + 1;
                        while ($next_q < $quarters_per_hour) {
                            $next_value = $tomorrow[$hour][$next_q] ?? null;
                            if ($value !== null && $next_value !== null && $value === $next_value) {
                                $colspan++;
                                $next_q++;
                            } else {
                                break;
                            }
                        }
                        ?>
                        <td class="price tomorrow quarter-<?=$q?>" data-quarter="<?=$q?>"
                            <?php if ($colspan > 1) { ?>colspan="<?=$colspan?>"<?php } ?>
                            style="<?=isset($value) ? '' : 'text-align: center; '?>background-color: <?=$viewHelper->getColorPercentage($value ?? -9999, $tomorrow_min, $tomorrow_max)?>">
                            <?=isset($value) ? $viewHelper->format($value) : '-'?>
                        </td>
                        <?php
                        $q = $next_q;
                    }
                    ?>
                </tr>
            <?php } ?>

            <?php
            // Add final point for chart continuity
            $legend[] = '00:00';
            $values['today'][] = $tomorrow[0][0] ?? 0;
            ?>
            </tbody>
        </table>

        <!-- Mobile tables -->
        <div class="mobile-tables">
            <?php if ($resolution == 15) { ?>
                <div id="mobile-selector">
                    <a href="#" data-day="today" data-current><?=$locale->msg('Šodien')?></a>
                    <a href="#" data-day="tomorrow"><?=$locale->msg('Rīt')?></a>
                </div>
            <?php } ?>
            <table class="mobile-table" data-day="today">
                <thead>
                <tr>
                    <th colspan="<?=$quarters_per_hour + 1?>"><?=$locale->msg('Šodien')?>
                        <span class="help"><?=$locale->formatDate($current_time, 'd. MMM')?></span><br/>
                        <small><?=$locale->msg('Vidēji')?>
                            <span><?=$today_avg ? $viewHelper->format($today_avg) : '—'?></span></small>
                    </th>
                </tr>
                <tr>
                    <th>🕑</th>
                    <?php if ($resolution == 15) { ?>
                        <th>:00</th>
                        <th>:15</th>
                        <th>:30</th>
                        <th>:45</th>
                    <?php } else { ?>
                        <th>:00</th>
                    <?php } ?>
                </tr>
                </thead>
                <tbody>
                <?php for ($hour = 0; $hour < 24; $hour++) {
                    $hour_label = sprintf('%02d', $hour) . '-' . sprintf('%02d', ($hour + 1) % 24);
                    ?>
                    <tr data-hours="<?=$hour?>" data-day="today">
                        <th><?=$hour_label?></th>
                        <?php
                        // Process quarters for today with colspan logic
                        $q = 0;
                        while ($q < $quarters_per_hour) {
                            $value = $today[$hour][$q] ?? null;

                            // Count consecutive quarters with same value
                            $colspan = 1;
                            $next_q = $q + 1;
                            while ($next_q < $quarters_per_hour) {
                                $next_value = $today[$hour][$next_q] ?? null;
                                if ($value !== null && $next_value !== null && $value === $next_value) {
                                    $colspan++;
                                    $next_q++;
                                } else {
                                    break;
                                }
                            }
                            ?>
                            <td class="price quarter-<?=$q?>" data-quarter="<?=$q?>"
                                <?php if ($colspan > 1) { ?>colspan="<?=$colspan?>"<?php } ?>
                                style="background-color: <?=$viewHelper->getColorPercentage($value ?? -9999, $today_min, $today_max)?>">
                                <?=isset($value) ? $viewHelper->format($value) : '-'?>
                            </td>
                            <?php
                            $q = $next_q;
                        }
                        ?>
                    </tr>
                <?php } ?>
                </tbody>
            </table>

            <table class="mobile-table<?=$resolution == 15 ? ' hidden' : ''?>" data-day="tomorrow">
                <thead>
                <tr>
                    <th colspan="<?=$quarters_per_hour + 1?>"><?=$locale->msg('Rīt')?>
                        <span class="help"><?=$locale->formatDate($current_time->modify('+1 day'), 'd. MMM')?></span><br/>
                        <small><?=$locale->msg('Vidēji')?>
                            <span><?=$tomorrow_avg ? $viewHelper->format($tomorrow_avg) : '—'?></span></small>
                    </th>
                </tr>
                <tr>
                    <th>🕑</th>
                    <?php if ($resolution == 15) { ?>
                        <th>:00</th>
                        <th>:15</th>
                        <th>:30</th>
                        <th>:45</th>
                    <?php } else { ?>
                        <th>:00</th>
                    <?php } ?>
                </tr>
                </thead>
                <tbody>
                <?php for ($hour = 0; $hour < 24; $hour++) {
                    $hour_label = sprintf('%02d', $hour) . '-' . sprintf('%02d', ($hour + 1) % 24);
                    ?>
                    <tr data-hours="<?=$hour?>" data-day="tomorrow">
                        <th><?=$hour_label?></th>
                        <?php
                        // Process quarters for tomorrow with colspan logic
                        $q = 0;
                        while ($q < $quarters_per_hour) {
                            $value = $tomorrow[$hour][$q] ?? null;

                            // Count consecutive quarters with same value
                            $colspan = 1;
                            $next_q = $q + 1;
                            while ($next_q < $quarters_per_hour) {
                                $next_value = $tomorrow[$hour][$next_q] ?? null;
                                if ($value !== null && $next_value !== null && $value === $next_value) {
                                    $colspan++;
                                    $next_q++;
                                } else {
                                    break;
                                }
                            }
                            ?>
                            <td class="price quarter-<?=$q?>" data-quarter="<?=$q?>"
                                <?php if ($colspan > 1) { ?>colspan="<?=$colspan?>"<?php } ?>
                                style="<?=isset($value) ? '' : 'text-align: center; '?>background-color: <?=$viewHelper->getColorPercentage($value ?? -9999, $tomorrow_min, $tomorrow_max)?>">
                                <?=isset($value) ? $viewHelper->format($value) : '-'?>
                            </td>
                            <?php
                            $q = $next_q;
                        }
                        ?>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>

        <p id="legend">
            <span class="legend bad"><?=$locale->msg('Izvairāmies tērēt elektrību')?></span> <span
                    class="legend good"><?=$locale->msg('Krājam burciņā')?></span>
        </p>

        <div id="chart-selector">
            <h2><?=$locale->msg('Primitīvs grafiks')?></h2>
            <p>
                <a href="#" data-day="today" data-current><?=$locale->msg('Šodien')?></a>
                <a href="#" data-day="tomorrow"><?=$locale->msg('Rīt')?></a>
            </p>
        </div>

        <div id="chart"></div>
        <script>
            const chart = echarts.init(document.getElementById('chart'));
            const option = {
                animation: false,
                renderer: 'svg',
                legend: {
                    show: false
                },
                grid: {
                    top: 50,
                    left: 40,
                    right: 10,
                    bottom: 20
                },
                title: {
                    show: false,
                },
                tooltip: {
                    trigger: 'axis',
                    formatter: function (params) {
                        let timeLabel = params[0].name;
                        let value = parseFloat(params[0].value);
                        let strValue = value.toString().padEnd(4, '0').substring(0, 4);

                        strValue += '<small>';
                        strValue += value.toString().substring(4).padEnd(2, '0');
                        strValue += '</small> €/kWh'

                        let [hour, minute] = timeLabel.split(':').map(n => parseInt(n, 10));
                        let endMinute = (minute + 15) % 60;
                        let endHour = (minute + 15 >= 60) ? (hour + 1) % 24 : hour;

                        return `
                        ${timeLabel} - ${('' + endHour).padStart(2, '0')}:${('' + endMinute).padStart(2, '0')}<br/>
                        ${strValue}
                        `;
                    },
                    axisPointer: {
                        type: 'cross',
                        snap: true,
                    },
                },
                xAxis: {
                    type: 'category',
                    data: <?= json_encode(array_values($legend)) ?>,
                    boundaryGap: false,
                    axisLabel: {
                        formatter: function (value) {
                            let hour = value.split(':')[0];
                            return hour;
                        },
                        interval: function (index, value) {
                            // Show label only when minutes are :00
                            return value.endsWith(':00');
                        }
                    },
                    splitLine: {
                        show: true,
                        interval: 0,
                        lineStyle: {
                            type: 'dashed',
                        },
                    },
                },
                yAxis: {
                    type: 'value',
                    axisLabel: {
                        formatter: function (value) {
                            return parseFloat(value).toFixed(2)
                        }
                    },
                },
                series: [
                    {
                        name: '€/kWh',
                        type: 'line',
                        step: 'end',
                        symbol: 'none',
                        data: <?= json_encode(array_values($values['today'])) ?>,
                        markPoint: {
                            data: [
                                {
                                    type: 'max',
                                    name: 'Max',
                                    symbolOffset: [0, -10],
                                    itemStyle: {
                                        color: '#a00',
                                    }
                                },
                                {
                                    type: 'min',
                                    name: 'Min',
                                    symbolOffset: [0, 10],
                                    itemStyle: {
                                        color: '#0a0',
                                    }
                                }
                            ],
                            symbol: 'rect',
                            symbolSize: [40, 15],
                            label: {
                                color: '#fff',
                                formatter: function (value) {
                                    return (Math.round(parseFloat(value.value) * 100) / 100).toFixed(2)
                                }
                            }
                        },
                        markLine: {
                            data: [{type: 'average', name: '<?= $locale->msg('Vidēji')?>'}],
                            symbol: 'none',
                            label: {
                                show: true,
                                position: "insideStartTop",
                                backgroundColor: "rgba(74, 101, 186, .3)",
                                padding: [3, 3],
                                // shadowColor: "ff0000",
                            }
                        }
                    },
                ]
            };

            chart.setOption(option);

            const dataset = {
                'today': <?= json_encode(array_values($values['today'])) ?>,
                'tomorrow': <?= json_encode(array_values($values['tomorrow'])) ?>,
            };
        </script>

        <footer>
            <p>
                <?php if ($with_vat) { ?>
                    <?=$locale->msg('Price shown includes VAT')?>
                    (<a href="<?=$locale->route('/')?>"><?=$locale->msg('show without VAT')?></a>).
                <?php } else { ?>
                    <?=$locale->msg('Price shown is without VAT')?>
                    (<a href="<?=$locale->route('/?vat')?>"><?=$locale->msg('show with VAT')?></a>).
                <?php } ?>

                <?=$locale->msgf(
                        'disclaimer',
                        $locale->msg('normal CSV') .
                        ' (<a href="/nordpool-' . $locale->get('code_lc') . '.csv">' . $locale->msg('15min data') . '</a>, ' .
                        '<a href="/nordpool-' . $locale->get('code_lc') . '-1h.csv">' . $locale->msg('1h average') . '</a>)',
                        $locale->msg('Excel CSV') .
                        ' (<a href="/nordpool-' . $locale->get('code_lc') . '-excel.csv">' . $locale->msg('15min data') . '</a>, ' .
                        '<a href="/nordpool-' . $locale->get('code_lc') . '-1h-excel.csv">' . $locale->msg('1h average') . '</a>)',
                        '<a href="https://nordpool.didnt.work/' . strtolower($country) . '?rss">rss</a>'
                )?>
        </footer>

        <script>
            document.addEventListener('DOMContentLoaded', () => {
                // now let's highlight the hour and quarter continuously
                let hours = null;
                let quarter = null;
                (function updateNow() {
                    const now = new Date();
                    const currentHours = now.getHours();
                    const currentQuarter = Math.floor(now.getMinutes() / 15);

                    if (hours !== currentHours || quarter !== currentQuarter) {
                        Array.from(document.querySelectorAll('[data-hours]')).forEach((row) => {
                            row.classList.remove('now');
                        })
                        Array.from(document.querySelectorAll('td.price')).forEach((cell) => {
                            cell.classList.remove('now-quarter');
                        })

                        // desktop table
                        const desktopRow = document.querySelector('.desktop-table tr[data-hours="' + currentHours + '"]');
                        if (desktopRow) {
                            desktopRow.classList.add('now');
                            const allCells = desktopRow.querySelectorAll('td.price');
                            if (allCells[currentQuarter]) {
                                allCells[currentQuarter].classList.add('now-quarter');
                            }
                        }

                        // mobile tables
                        const mobileRow = document.querySelector('.mobile-table tr[data-hours="' + currentHours + '"][data-day="today"]');
                        if (mobileRow) {
                            mobileRow.classList.add('now');
                            const quarterCells = mobileRow.querySelectorAll('td.price');
                            if (quarterCells[currentQuarter]) {
                                quarterCells[currentQuarter].classList.add('now-quarter');
                            }
                        }

                        hours = currentHours;
                        quarter = currentQuarter;
                    }
                    setTimeout(updateNow, 1000);
                })()

                document.querySelectorAll('#chart-selector a').forEach(element => element.addEventListener('click', (e) => {
                    e.preventDefault();
                    const day = e.target.dataset.day;
                    chart.setOption({
                        series: [{
                            data: dataset[day],
                        }]
                    });
                    document.querySelectorAll('#chart-selector a').forEach((el) => {
                        el.removeAttribute('data-current');
                    })
                    e.target.setAttribute('data-current', true);
                }))

                // mobile selector
                document.querySelectorAll('#mobile-selector a').forEach(element => element.addEventListener('click', (e) => {
                    e.preventDefault();
                    const day = e.target.dataset.day;

                    document.querySelectorAll('.mobile-table').forEach((table) => {
                        if (table.dataset.day === day) {
                            table.classList.remove('hidden');
                        } else {
                            table.classList.add('hidden');
                        }
                    })

                    chart.setOption({
                        series: [{
                            data: dataset[day],
                        }]
                    });

                    document.querySelectorAll('#mobile-selector a').forEach((el) => {
                        el.removeAttribute('data-current');
                    })
                    e.target.setAttribute('data-current', true);
                }))
            })
        </script>
    </div>
    </body>

    </html>
<?php
$html = ob_get_clean();
echo $html;
Cache::set($cache_key, $html);
