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
    <link rel="manifest"
          href="/<?=strtolower($country)?>/manifest<?=$request->getCurrentQueryString($with_vat, $resolution)?>"/>
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title><?=$locale->msg('title')?></title>

    <link rel="icon" type="image/svg+xml" href="/favicon-dark.svg" media="(prefers-color-scheme: dark)"/>
    <link rel="icon" type="image/svg+xml" href="/favicon-light.svg" media="(prefers-color-scheme: light)"/>
    <link rel="apple-touch-icon" type="image/svg+xml" href="/favicon-dark.svg" media="(prefers-color-scheme: dark)"/>
    <link rel="apple-touch-icon" type="image/svg+xml" href="/favicon-light.svg" media="(prefers-color-scheme: light)"/>

    <link rel="alternate" type="application/rss+xml" title="nordpool.didnt.work RSS feed"
          href="/<?=strtolower($country)?>?rss"/>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <meta name="description" content="<?=$locale->msg('meta description')?>">
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
    <script>
        // Theme initialization - MUST run before body renders to prevent flicker
        (function() {
            const storage = window.localStorage;
            const savedTheme = storage.getItem('theme-preference');
            const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

            let themeClass = 'light-mode';
            if (savedTheme === 'dark') {
                themeClass = 'dark-mode';
            } else if (savedTheme === 'light') {
                themeClass = 'light-mode';
            } else if (systemPrefersDark) {
                themeClass = 'dark-mode';
            }

            document.documentElement.className = themeClass;
        })();
    </script>
    <script src="/echarts.min.js"></script>
    <link rel="stylesheet" href="/style.css?<?=crc32(file_get_contents(__DIR__ . '/../public/style.css'))?>">
</head>

<body<?=$resolution == 60 ? ' class="res-60"' : ''?>>

<div id="app">

    <header>
        <h1>
            <svg id="logo" viewBox="100 100 950 950" xmlns="http://www.w3.org/2000/svg">
                <style>
                #bg, #cable-shadow { fill: #fff; }
                #bars rect { fill: #73e69b; }
                #cable path{ fill: #003352; stroke: #003352; }

                html.dark-mode #bg, html.dark-mode #cable-shadow,
                body.dark-mode #bg, body.dark-mode #cable-shadow { fill: #003352; }
                html.dark-mode #bars rect,
                body.dark-mode #bars rect { fill: #73e69b; }
                html.dark-mode #cable path,
                body.dark-mode #cable path{ fill: #fff; stroke: #fff; }
                </style>
                <rect width="1139" height="1139" id="bg"/>
                <g id="bars">
                    <rect x="230" y="588" width="139" height="235"/>
                    <rect x="420" y="454" width="139" height="369"/>
                    <rect x="610" y="318" width="139" height="505"/>
                </g>

                <path d="M120 807.5L301 717.5L490 787.5L841 579V532L490 740.5L301 670.5L120 760.5V807.5Z"
                      id="cable-shadow"/>
                <g id="cable">
                    <!-- cable -->
                    <path d="M120 762.5L301 672.5L490 742.5L841 534V487L490 695.5L301 625.5L120 715.5V762.5Z"/>
                    <!-- plug -->
                    <g>
                        <path d="M978.001 550C966.477 557.452 941.844 574.812 928.346 577.286C914.848 579.761 900.994 579.553 887.577 576.674C874.159 573.794 861.44 568.301 850.145 560.506C838.851 552.711 829.202 542.768 821.75 531.245C814.298 519.721 809.189 506.842 806.714 493.344C804.24 479.846 804.448 465.993 807.327 452.575C810.206 439.157 815.7 426.438 823.495 415.144C831.289 403.849 859.976 388.452 871.5 381L926.5 468.5L978.001 550Z"
                        />
                        <path d="M947.5 504L1018.5 460M947.5 504L1018.5 460" stroke-width="35" stroke-linecap="round"/>
                        <path d="M898 430L969 386M898 430L969 386" stroke-width="35" stroke-linecap="round"/>
                    </g>
                </g>
            </svg>
        </h1>
        <p>
            <?php foreach ($countryConfig as $code => $config) { ?>
                <a class="flag" href="/<?=$config['code_lc'] === 'lv' ? '' : $config['code_lc']?>"><img
                            src="/<?=$config['code_lc']?>.svg" alt="<?=$config['name']?>" width="32"
                            height="32"/></a>
            <?php } ?>
            <a href="#" id="theme-toggle" class="flag" aria-label="Toggle theme">
                <svg class="theme-icon" width="32" height="32" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <!-- Moon icon (default for light mode) -->
                    <path class="moon-icon" d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" fill="currentColor" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <!-- Sun icon (hidden by default) -->
                    <g class="sun-icon" style="display: none;">
                        <circle cx="12" cy="12" r="5" fill="currentColor" stroke="currentColor" stroke-width="2"/>
                        <line x1="12" y1="1" x2="12" y2="3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        <line x1="12" y1="21" x2="12" y2="23" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        <line x1="4.22" y1="4.22" x2="5.64" y2="5.64" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        <line x1="18.36" y1="18.36" x2="19.78" y2="19.78" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        <line x1="1" y1="12" x2="3" y2="12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        <line x1="21" y1="12" x2="23" y2="12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        <line x1="4.22" y1="19.78" x2="5.64" y2="18.36" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        <line x1="18.36" y1="5.64" x2="19.78" y2="4.22" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </g>
                </svg>
            </a>
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
                <?php for ($threshold = 5; $threshold < 66; $threshold += 5) { ?>
                    <option value="<?=$threshold?>"><?=number_format($threshold / 100, 2, '.', '')?>+€</option>
                <?php } ?>
            </select>
        </p>
    </header>

        <?php if (date('Y-m-d') < '2025-10-08') { ?>
        <div class="notice info">
            <p><?=$locale->msg('15min notice')?></p>
        </div>
    <?php } ?>

    <?php if (date('Y-m-d') < '2025-10-22') { ?>
        <div class="notice success">
            <p><?=$locale->msg('notification announcement')?></p>
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
                        <?=isset($value) ? $viewHelper->format($value) : ''?>
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
                        <?=isset($value) ? $viewHelper->format($value) : ''?>
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
                            <?=isset($value) ? $viewHelper->format($value) : ''?>
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
        // Apply theme to body as well (html already has it from head script)
        (function initTheme() {
            const themeClass = document.documentElement.className;
            if (themeClass) {
                document.body.classList.add(themeClass);
            }
        })();

        const chart = echarts.init(document.getElementById('chart'));

        // Function to get theme-aware chart options
        function getChartOption(data) {
            const isDark = document.body.classList.contains('dark-mode');
            const textColor = isDark ? '#e8f4f8' : '#333';
            const lineColor = isDark ? '#003a58' : '#ccc';
            const maxColor = isDark ? '#7a0000' : '#a00';
            const minColor = isDark ? '#006600' : '#0a0';

            return {
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
                        color: textColor,
                        formatter: function (value) {
                            return value.split(':')[0];
                        },
                        interval: function (index, value) {
                            // Show label only when minutes are :00
                            return value.endsWith(':00');
                        }
                    },
                    axisLine: {
                        lineStyle: {
                            color: lineColor
                        }
                    },
                    splitLine: {
                        show: true,
                        interval: 0,
                        lineStyle: {
                            type: 'dashed',
                            color: lineColor
                        },
                    },
                },
                yAxis: {
                    type: 'value',
                    axisLabel: {
                        color: textColor,
                        formatter: function (value) {
                            return parseFloat(value).toFixed(2)
                        }
                    },
                    axisLine: {
                        lineStyle: {
                            color: lineColor
                        }
                    },
                    splitLine: {
                        lineStyle: {
                            color: lineColor
                        }
                    }
                },
                series: [
                    {
                        name: '€/kWh',
                        type: 'line',
                        step: 'end',
                        symbol: 'none',
                        data: data,
                        lineStyle: {
                            color: isDark ? '#4d9eff' : '#4a65ba'
                        },
                        markPoint: {
                            data: [
                                {
                                    type: 'max',
                                    name: 'Max',
                                    symbolOffset: [0, -10],
                                    itemStyle: {
                                        color: maxColor,
                                    }
                                },
                                {
                                    type: 'min',
                                    name: 'Min',
                                    symbolOffset: [0, 10],
                                    itemStyle: {
                                        color: minColor,
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
                            lineStyle: {
                                color: isDark ? '#4d9eff' : '#4a65ba'
                            },
                            label: {
                                show: true,
                                position: "insideStartTop",
                                backgroundColor: isDark ? "rgba(77, 158, 255, .3)" : "rgba(74, 101, 186, .3)",
                                color: textColor,
                                padding: [3, 3],
                            }
                        }
                    },
                ]
            };
        }

        chart.setOption(getChartOption(<?= json_encode(array_values($values['today'])) ?>));

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

            // Color gradient configuration - light theme colors
            const percentColorsLight = [
                {pct: 0.0, color: {r: 0x00, g: 0x88, b: 0x00}},
                {pct: 0.5, color: {r: 0xAA, g: 0xAA, b: 0x00}},
                {pct: 1.0, color: {r: 0xAA, g: 0x00, b: 0x00}}
            ];

            // Color gradient configuration - dark theme colors (darker/muted)
            const percentColorsDark = [
                {pct: 0.0, color: {r: 0x00, g: 0x66, b: 0x00}},  // Darker green
                {pct: 0.5, color: {r: 0x7a, g: 0x66, b: 0x00}},  // Muted yellow
                {pct: 1.0, color: {r: 0x7a, g: 0x00, b: 0x00}}   // Darker red
            ];

            // Get current color scheme based on theme
            function getPercentColors() {
                const isDark = document.body.classList.contains('dark-mode');
                return isDark ? percentColorsDark : percentColorsLight;
            }

            // Port of PHP getColorPercentage function
            function getColorPercentage(value, min, max) {
                if (isNaN(value)) {
                    return 'transparent';
                }

                const percentColors = getPercentColors();
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
                const isDark = document.body.classList.contains('dark-mode');

                document.querySelectorAll('td.price').forEach(cell => {
                    const value = parseFloat(cell.getAttribute('data-value'));

                    let color;
                    if (threshold > 0) {
                        // binary coloring with theme-aware colors
                        const thresholdValue = threshold / 100;
                        if (isNaN(value)) {
                            color = '#fff';
                        } else if (value < thresholdValue) {
                            color = isDark ? 'rgb(0,102,0)' : 'rgb(0,136,0)'; // Green
                        } else {
                            color = isDark ? 'rgb(122,0,0)' : 'rgb(170,0,0)'; // Red
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
                chart.setOption(getChartOption(dataset[day]));
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

                chart.setOption(getChartOption(dataset[day]));

                document.querySelectorAll('#mobile-selector a').forEach((el) => {
                    el.removeAttribute('data-current');
                })
                e.target.setAttribute('data-current', true);
            }))

            // Theme toggle functionality
            const themeToggle = document.getElementById('theme-toggle');
            const moonIcon = themeToggle.querySelector('.moon-icon');
            const sunIcon = themeToggle.querySelector('.sun-icon');

            function updateThemeIcon() {
                const isDark = document.body.classList.contains('dark-mode');
                if (isDark) {
                    // Show sun icon (to switch to light mode)
                    moonIcon.style.display = 'none';
                    sunIcon.style.display = 'block';
                } else {
                    // Show moon icon (to switch to dark mode)
                    moonIcon.style.display = 'block';
                    sunIcon.style.display = 'none';
                }
            }

            // Set initial icon
            updateThemeIcon();

            themeToggle.addEventListener('click', (e) => {
                e.preventDefault();
                const isDark = document.body.classList.contains('dark-mode');

                if (isDark) {
                    // Switch to light mode
                    document.documentElement.className = 'light-mode';
                    document.body.className = 'light-mode';
                    storage.setItem('theme-preference', 'light');
                } else {
                    // Switch to dark mode
                    document.documentElement.className = 'dark-mode';
                    document.body.className = 'dark-mode';
                    storage.setItem('theme-preference', 'dark');
                }

                updateThemeIcon();
                applyColors(); // Reapply heatmap colors for new theme

                // Update chart with current data - use notMerge to force full redraw
                const currentData = chart.getOption().series[0].data;
                chart.setOption(getChartOption(currentData), { notMerge: true });
            });
        })
    </script>
</div>
</body>

</html>
