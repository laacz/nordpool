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
          href="/<?=strtolower($country)?>?rss"/>
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
            <br/>
            <?=$locale->msg('Brīdinājums')?>:
            <select id="heatmap-threshold">
                <option value="0"><?=$locale->msg('automātiski')?></option>
                <?php for ($threshold = 5; $threshold < 66; $threshold+=5) { ?>
                    <option value="<?=$threshold?>"><?=number_format($threshold/100, 2, '.', '')?>+€</option>
                <?php } ?>
            </select>
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
                <small><?=$locale->msg('Vidēji')?>
                    <span><?=$today_avg ? $viewHelper->format($today_avg) : '—'?></span></small>
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
                        data-start="<?=$current_time->format('Y-m-d')?> <?=sprintf('%02d:%02d', $hour, $q * 15)?>"
                        data-end="<?=$current_time->format('Y-m-d')?> <?=sprintf('%02d:%02d', $hour, $next_q * 15)?>"
                        data-value="<?=$value ?? ''?>"
                        data-min="<?=$today_min?>"
                        data-max="<?=$today_max?>"
                        <?php if ($colspan > 1) { ?>colspan="<?=$colspan?>"<?php } ?>>
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
                        data-start="<?=$current_time->modify('+1 day')->format('Y-m-d')?> <?=sprintf('%02d:%02d', $hour, $q * 15)?>"
                        data-end="<?=$current_time->format('Y-m-d')?> <?=sprintf('%02d:%02d', $hour, $next_q * 15)?>"
                        data-value="<?=$value ?? ''?>"
                        data-min="<?=$tomorrow_min?>"
                        data-max="<?=$tomorrow_max?>"
                        <?php if ($colspan > 1) { ?>colspan="<?=$colspan?>"<?php } ?>
                        <?php if (!isset($value)) { ?>style="text-align: center;"<?php } ?>>
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
                            data-start="<?=$current_time->format('Y-m-d')?> <?=sprintf('%02d:%02d', $hour, $q * 15)?>"
                            data-end="<?=$current_time->format('Y-m-d')?> <?=sprintf('%02d:%02d', $hour, $next_q * 15)?>"
                            data-value="<?=$value ?? ''?>"
                            data-min="<?=$today_min?>"
                            data-max="<?=$today_max?>"
                            <?php if ($colspan > 1) { ?>colspan="<?=$colspan?>"<?php } ?>>
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
                            data-start="<?=$current_time->modify('+1 day')->format('Y-m-d')?> <?=sprintf('%02d:%02d', $hour, $q * 15)?>"
                            data-end="<?=$current_time->format('Y-m-d')?> <?=sprintf('%02d:%02d', $hour, $next_q * 15)?>"
                            data-value="<?=$value ?? ''?>"
                            data-min="<?=$tomorrow_min?>"
                            data-max="<?=$tomorrow_max?>"
                            <?php if ($colspan > 1) { ?>colspan="<?=$colspan?>"<?php } ?>
                            <?php if (!isset($value)) { ?>style="text-align: center;"<?php } ?>>
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
                        return value.split(':')[0];
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
                    '<a href="/' . strtolower($country) . '?rss">rss</a>'
            )?>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const storage = window.localStorage;
            const heatmapThresholdSelect = document.getElementById('heatmap-threshold');

            // Color gradient configuration (matches PHP ViewHelper)
            const percentColors = [
                {pct: 0.0, color: {r: 0x00, g: 0x88, b: 0x00}},
                {pct: 0.5, color: {r: 0xAA, g: 0xAA, b: 0x00}},
                {pct: 1.0, color: {r: 0xAA, g: 0x00, b: 0x00}}
            ];

            // Port of PHP getColorPercentage function
            function getColorPercentage(value, min, max) {
                if (value === -9999) {
                    return '#fff';
                }

                let pct = (max - min) === 0 ? 0 : (value - min) / (max - min);
                // Clamp percentage to [0, 1] range to handle threshold overrides
                pct = Math.max(0, Math.min(1, pct));

                let i = 1;
                for (; i < percentColors.length - 1; i++) {
                    if (pct < percentColors[i].pct) {
                        break;
                    }
                }

                const lower = percentColors[i - 1];
                const upper = percentColors[i];
                const range = upper.pct - lower.pct;
                const rangePct = (pct - lower.pct) / range;
                const pctLower = 1 - rangePct;
                const pctUpper = rangePct;

                const color = {
                    r: Math.floor(lower.color.r * pctLower + upper.color.r * pctUpper),
                    g: Math.floor(lower.color.g * pctLower + upper.color.g * pctUpper),
                    b: Math.floor(lower.color.b * pctLower + upper.color.b * pctUpper)
                };

                return `rgb(${color.r},${color.g},${color.b})`;
            }

            // Apply colors to all price cells
            function applyColors() {
                const threshold = parseInt(heatmapThresholdSelect.value, 10);

                document.querySelectorAll('td.price').forEach(cell => {
                    const value = parseFloat(cell.getAttribute('data-value'));

                    let color;
                    if (threshold > 0) {
                        // binary coloring
                        const thresholdValue = threshold / 100; 
                        if (isNaN(value)) {
                            color = '#fff';
                        } else if (value < thresholdValue) {
                            color = 'rgb(0,136,0)'; // Green
                        } else {
                            color = 'rgb(170,0,0)'; // Red
                        }
                    } else {
                        // gradient coloring
                        const min = parseFloat(cell.getAttribute('data-min'));
                        const max = parseFloat(cell.getAttribute('data-max'));
                        color = getColorPercentage(value, min, max);
                    }

                    cell.style.backgroundColor = color;
                });
            }

            heatmapThresholdSelect.addEventListener('change', (e) => {
                const value = parseInt(e.target.value, 10);
                if (!isNaN(value)) {
                    storage.setItem('heatmap-threshold', value.toString());
                    applyColors();
                }
            });

            const savedThreshold = parseInt(storage.getItem('heatmap-threshold'), 10);
            if (!isNaN(savedThreshold)) {
                heatmapThresholdSelect.value = savedThreshold.toString();
            }

            applyColors();

            // now let's highlight the hour and quarter continuously
            let lastCurrentTime = null;
            (function updateNow() {
                const now = new Date();
                const currentHours = now.getHours();
                const currentMinutes = now.getMinutes();
                const currentQuarter = Math.floor(currentMinutes / 15);

                // Format current datetime as "YYYY-MM-DD HH:MM" (rounded down to quarter)
                const year = now.getFullYear();
                const month = String(now.getMonth() + 1).padStart(2, '0');
                const day = String(now.getDate()).padStart(2, '0');
                const quarterMinutes = String(currentQuarter * 15).padStart(2, '0');
                const currentTime = `${year}-${month}-${day} ${String(currentHours).padStart(2, '0')}:${quarterMinutes}`;

                if (lastCurrentTime !== currentTime) {
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
                        for (const cell of allCells) {
                            const start = cell.getAttribute('data-start');
                            const end = cell.getAttribute('data-end');
                            if (start && end && currentTime >= start && currentTime < end) {
                                cell.classList.add('now-quarter');
                                break;
                            }
                        }
                    }

                    // mobile tables
                    const mobileRow = document.querySelector('.mobile-table tr[data-hours="' + currentHours + '"][data-day="today"]');
                    if (mobileRow) {
                        mobileRow.classList.add('now');
                        const quarterCells = mobileRow.querySelectorAll('td.price');
                        for (const cell of quarterCells) {
                            const start = cell.getAttribute('data-start');
                            const end = cell.getAttribute('data-end');
                            if (start && end && currentTime >= start && currentTime < end) {
                                cell.classList.add('now-quarter');
                                break;
                            }
                        }
                    }

                    lastCurrentTime = currentTime;
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
