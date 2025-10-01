<?php
require 'functions.php';

if (php_sapi_name() == 'cli-server') {
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
        return false;
    }
}


$path = $_SERVER['REQUEST_URI'] ?? '';
$path = explode('?', $path)[0];
$parts = explode('/', $path);
$country = strtoupper($parts[1] ?? 'lv');

$DB = new PDO('sqlite:../nordpool.db');

$prices = [];
$tz_riga = new DateTimeZone('Europe/Riga');
$tz_cet = new DateTimeZone('Europe/Berlin');

$with_vat = isset($_GET['vat']);

if (!isset($countryConfig[$country])) {
    $country = 'LV';
}

$locale = new AppLocale($countryConfig[$country], $translations);

/** @var float $vat */
$vat = $locale->get('vat');

$current_time = new DateTimeImmutable('now', $tz_riga);
if (isset($_GET['now'])) {
    $current_time = new DateTimeImmutable($_GET['now'], $tz_riga);
}

$current_time_cet = $current_time->setTimezone($tz_cet);
$sql_time = $current_time_cet->format('Y-m-d H:i:s');

$sql = "
SELECT *
  FROM price_indices
 WHERE country = " . $DB->quote($locale->get('code')) . "
   AND ts_start >= DATE(" . $DB->quote($sql_time) . ", '-2 day')
   AND ts_start <= DATE(" . $DB->quote($sql_time) . ", '+3 day')
   AND resolution_minutes = 15
ORDER BY ts_start DESC
";

// $sql = "
// SELECT *
//   FROM spot_prices
//  WHERE country = " . $DB->quote($locale->get('code')) . "
//    AND ts_start >= DATE(" . $DB->quote($sql_time) . ", '-2 day')
//    AND ts_start <= DATE(" . $DB->quote($sql_time) . ", '+3 day')
// ORDER BY ts_start DESC
// ";


foreach ($DB->query($sql) as $row) {
    try {
        $start = new DateTime($row['ts_start'], $tz_cet);
        $end = new DateTime($row['ts_end'], $tz_cet);
        $start->setTimeZone($tz_riga);
        $end->setTimeZone($tz_riga);

        $hour = (int)$start->format('H');
        $minute = (int)$start->format('i');
        $quarter = (int)($minute / 15); // 0, 1, 2, or 3

        $prices[$start->format('Y-m-d')][$hour][$quarter] = round(($with_vat ? 1 + $vat : 1) * ((float)$row['value']) / 1000, 4);
    } catch (Exception $e) {
        continue;
    }
}

$today = $prices[$current_time->format('Y-m-d')] ?? [];
$tomorrow = $prices[$current_time->modify('+1 day')->format('Y-m-d')] ?? [];

// Flatten today and tomorrow for calculations
$today_flat = [];
foreach ($today as $hour => $quarters) {
    foreach ($quarters as $quarter => $value) {
        $today_flat[] = $value;
    }
}

$tomorrow_flat = [];
foreach ($tomorrow as $hour => $quarters) {
    foreach ($quarters as $quarter => $value) {
        $tomorrow_flat[] = $value;
    }
}

$today_avg = count($today_flat) === 96 ? array_sum($today_flat) / count($today_flat) : null;
$tomorrow_avg = count($tomorrow_flat) === 96 ? array_sum($tomorrow_flat) / count($tomorrow_flat) : null;

$today_max = count($today_flat) ? max($today_flat) : 0;
$today_min = count($today_flat) ? min($today_flat) : 0;
$tomorrow_max = count($tomorrow_flat) ? max($tomorrow_flat) : 0;
$tomorrow_min = count($tomorrow_flat) ? min($tomorrow_flat) : 0;

$now_hour = (int)$current_time->format('H');
$now_quarter = (int)((int)$current_time->format('i') / 15);

foreach ($prices as $k => $day) {
    ksort($prices[$k]);
}

$hours = array_keys($today);
asort($hours);

?>
<!doctype html>
<html lang="<?= strtolower($locale->get('code_lc')) ?>">

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
    <title><?= $locale->msg('title') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fira+Sans:wght@100;400;700&display=swap" rel="stylesheet">
    <script src="/echarts.min.js"></script>

    <style>
        body {
            font-family: 'fira sans', sans-serif;
        }

        header h1 {
            font-size: 2rem;
            text-align: left;
            white-space: nowrap;
        }

        footer {
            border-top: 1px solid #aaa;
            padding: 0 .5rem;
            margin-top: 2em;
        }

        #app footer p {
            font-size: smaller;
            text-align: left;
        }

        #legend {
            text-align: center;
            font-size: smaller;
        }

        .help {
            font-weight: 400;
            display: block;
            font-size: 80%;
        }

        body {
            font-family: sans-serif;
            font-size: 16px;
        }


        #app {
            width: 40rem;
            max-width: 95%;
            margin: 0 auto;
        }

        #app p {
            line-height: 1.5;
        }

        table {
            border-collapse: collapse;
            margin: 0 auto;
            /*width: 100%;*/
            width: auto;
        }

        th,
        td {
            padding: 5px 10px;
            border: 2px solid #fff;
        }

        table tbody tr th {
            width: 1%;
            white-space: nowrap;
        }

        tbody td {
            width: 50%;
        }

        tr.now {
            outline: 3px solid #f00;
        }

        td.now-quarter {
            outline: 3px solid #ff0;
            outline-offset: -3px;
        }

        .price {
            text-align: right;
            color: #fff;
            font-family: 'consolas', monospace;
        }

        th small span {
            font-family: 'consolas', monospace;
        }

        .legend {
            display: inline-block;
            padding: 5px 10px;
        }

        .good {
            background-color: rgb(<?= $percentColors[0]['color']['r'] ?>, <?= $percentColors[0]['color']['g'] ?>, <?= $percentColors[0]['color']['b'] ?>);
            color: #fff;
        }

        .bad {
            background-color: rgb(<?= $percentColors[2]['color']['r'] ?>, <?= $percentColors[2]['color']['g'] ?>, <?= $percentColors[2]['color']['b'] ?>);
            color: #fff;
        }

        .extra-decimals {
            opacity: .4;
            font-size:70%;
        }

        header {
            display: grid;
            grid-template-columns: 1fr auto;
        }

        header p {
            text-align: right;
            font-size: smaller;
        }

        .flag {
            height: 1.5em;
            margin: 0 .2em;
        }

        #chart {
            height: 400px;
            margin: 0 auto;
        }

        #chart-selector {
            margin: 1em 0;
            display: grid;
            grid-template-columns: 1fr auto;
        }

        /* nice buttons */
        #chart-selector a {
            display: inline-block;
            text-align: center;
            font-size: 1rem;
            font-weight: 600;
            border-radius: 8px;
            margin: 0 .3em;
            padding: 0.5em 1em;
            background-color: #f0f0f0;
            color: #333;
            text-decoration: none;
            transition: background-color 0.3s, color 0.3s;
        }

        #chart-selector a:hover,
        #chart-selector a[data-current] {
            background-color: #333;
            color: #fff;
        }

        /* Mobile responsive tables */
        .mobile-tables {
            display: none;
        }

        .mobile-table {
            margin-bottom: 2em;
        }

        .mobile-table.hidden {
            display: none;
        }

        #mobile-selector {
            margin: 1em 0;
            text-align: center;
        }

        #mobile-selector a {
            display: inline-block;
            text-align: center;
            font-size: 1rem;
            font-weight: 600;
            border-radius: 8px;
            margin: 0 .3em;
            padding: 0.5em 1em;
            background-color: #f0f0f0;
            color: #333;
            text-decoration: none;
            transition: background-color 0.3s, color 0.3s;
        }

        #mobile-selector a:hover,
        #mobile-selector a[data-current] {
            background-color: #333;
            color: #fff;
        }

        @media (max-width: 768px) {
            .desktop-table {
                display: none;
            }

            .mobile-tables {
                display: block;
            }

            #chart-selector {
                display: none;
            }
        }
    </style>
</head>

<body>

<div id="app">

    <header>
        <h1>
            🔌🏷️ <br/>€/kWh
        </h1>
        <p>
            <?php foreach ($countryConfig as $code => $config) { ?>
                <a class="flag" href="/<?= $config['code_lc'] === 'lv' ? '' : $config['code_lc'] ?>"><img
                            src="/<?= $config['code_lc'] ?>.svg" alt="<?= $config['name'] ?>" width="32"
                            height="32"/></a>
            <?php } ?>
            <br/>
            <?= $locale->msg('subtitle') ?><br/>
            <?php if ($with_vat) { ?>
                <?= $locale->msg('it is with VAT') ?> <?= round($vat * 100) ?>% (<a
                        href="<?= $locale->route('/') ?>"><?= $locale->msg('show without VAT') ?></a>)
            <?php } else { ?>
                <?= $locale->msg('it is without VAT') ?> <?= round($vat * 100) ?>% (<a
                        href="<?= $locale->route('/?vat') ?>"><?= $locale->msg('show with VAT') ?></a>)
            <?php } ?>
        </p>
    </header>

    <?php if (!str_starts_with($_SERVER['HTTP_HOST'] ?? '', 'localhost') && $locale->get('code') === 'LV') { ?>
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
            <th colspan="5"><?= $locale->msg('Šodien') ?>
                <span class="help"><?= $locale->formatDate($current_time, 'd. MMM') ?></span><br/>
                <small><?= $locale->msg('Vidēji') ?> <span><?= $today_avg ? format($today_avg) : '—' ?></span></small>
            </th>
            <th colspan="4"><?= $locale->msg('Rīt') ?>
                <span
                        class="help"><?= $locale->formatDate($current_time->modify('+1 day'), 'd. MMM') ?></span><br/>
                <small><?= $locale->msg('Vidēji') ?> <span><?= $tomorrow_avg ? format($tomorrow_avg) : '—' ?></span></small>
            </th>
        </tr>
        <tr>
            <th>🕑</th>
            <th>:00</th>
            <th>:15</th>
            <th>:30</th>
            <th>:45</th>
            <th>:00</th>
            <th>:15</th>
            <th>:30</th>
            <th>:45</th>
        </tr>
        </thead>
        <tbody>
        <?php
        $legend = [];
        $values['today'] = [];
        $values['tomorrow'] = [];
        ?>
        <?php for ($hour = 0; $hour < 24; $hour++) {
            $hour_label = sprintf('%02d', $hour) . '-' . sprintf('%02d', ($hour + 1) % 24);
            ?>
            <tr data-hours="<?= $hour ?>">
                <th><?= $hour_label ?></th>
                <?php for ($q = 0; $q < 4; $q++) {
                    $value = $today[$hour][$q] ?? null;
                    $legend[] = sprintf('%02d:%02d', $hour, $q * 15);
                    $values['today'][] = $value ?? 0;
                    ?>
                    <td class="price quarter-<?= $q ?>" data-quarter="<?= $q ?>"
                        style="background-color: <?= getColorPercentage($value ?? -9999, $today_min, $today_max) ?>">
                        <?= isset($value) ? format($value) : '-' ?>
                    </td>
                <?php } ?>
                <?php for ($q = 0; $q < 4; $q++) {
                    $value = $tomorrow[$hour][$q] ?? null;
                    $values['tomorrow'][] = $value ?? 0;
                    ?>
                    <td class="price quarter-<?= $q ?>" data-quarter="<?= $q ?>"
                        style="<?= isset($value) ? '' : 'text-align: center; ' ?>background-color: <?= getColorPercentage($value ?? -9999, $tomorrow_min, $tomorrow_max) ?>">
                        <?= isset($value) ? format($value) : '-' ?>
                    </td>
                <?php } ?>
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
        <div id="mobile-selector">
            <a href="#" data-day="today" data-current><?= $locale->msg('Šodien') ?></a>
            <a href="#" data-day="tomorrow"><?= $locale->msg('Rīt') ?></a>
        </div>
        <table class="mobile-table" data-day="today">
            <thead>
            <tr>
                <th colspan="5"><?= $locale->msg('Šodien') ?>
                    <span class="help"><?= $locale->formatDate($current_time, 'd. MMM') ?></span><br/>
                    <small><?= $locale->msg('Vidēji') ?> <span><?= $today_avg ? format($today_avg) : '—' ?></span></small>
                </th>
            </tr>
            <tr>
                <th>🕑</th>
                <th>:00</th>
                <th>:15</th>
                <th>:30</th>
                <th>:45</th>
            </tr>
            </thead>
            <tbody>
            <?php for ($hour = 0; $hour < 24; $hour++) {
                $hour_label = sprintf('%02d', $hour) . '-' . sprintf('%02d', ($hour + 1) % 24);
                ?>
                <tr data-hours="<?= $hour ?>" data-day="today">
                    <th><?= $hour_label ?></th>
                    <?php for ($q = 0; $q < 4; $q++) {
                        $value = $today[$hour][$q] ?? null;
                        ?>
                        <td class="price quarter-<?= $q ?>" data-quarter="<?= $q ?>"
                            style="background-color: <?= getColorPercentage($value ?? -9999, $today_min, $today_max) ?>">
                            <?= isset($value) ? format($value) : '-' ?>
                        </td>
                    <?php } ?>
                </tr>
            <?php } ?>
            </tbody>
        </table>

        <table class="mobile-table hidden" data-day="tomorrow">
            <thead>
            <tr>
                <th colspan="5"><?= $locale->msg('Rīt') ?>
                    <span class="help"><?= $locale->formatDate($current_time->modify('+1 day'), 'd. MMM') ?></span><br/>
                    <small><?= $locale->msg('Vidēji') ?> <span><?= $tomorrow_avg ? format($tomorrow_avg) : '—' ?></span></small>
                </th>
            </tr>
            <tr>
                <th>🕑</th>
                <th>:00</th>
                <th>:15</th>
                <th>:30</th>
                <th>:45</th>
            </tr>
            </thead>
            <tbody>
            <?php for ($hour = 0; $hour < 24; $hour++) {
                $hour_label = sprintf('%02d', $hour) . '-' . sprintf('%02d', ($hour + 1) % 24);
                ?>
                <tr data-hours="<?= $hour ?>" data-day="tomorrow">
                    <th><?= $hour_label ?></th>
                    <?php for ($q = 0; $q < 4; $q++) {
                        $value = $tomorrow[$hour][$q] ?? null;
                        ?>
                        <td class="price quarter-<?= $q ?>" data-quarter="<?= $q ?>"
                            style="<?= isset($value) ? '' : 'text-align: center; ' ?>background-color: <?= getColorPercentage($value ?? -9999, $tomorrow_min, $tomorrow_max) ?>">
                            <?= isset($value) ? format($value) : '-' ?>
                        </td>
                    <?php } ?>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>

    <p id="legend">
        <span class="legend bad"><?= $locale->msg('Izvairāmies tērēt elektrību') ?></span> <span
                class="legend good"><?= $locale->msg('Krājam burciņā') ?></span>
    </p>

    <div id="chart-selector">
        <h2><?= $locale->msg('Primitīvs grafiks') ?></h2>
        <p>
            <a href="#" data-day="today" data-current><?= $locale->msg('Šodien') ?></a>
            <a href="#" data-day="tomorrow"><?= $locale->msg('Rīt') ?></a>
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
                        data: [{type: 'average', name: '<?=$locale->msg('Vidēji')?>'}],
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
                <?= $locale->msg('Price shown includes VAT') ?>
                (<a href="<?= $locale->route('/') ?>"><?= $locale->msg('show without VAT') ?></a>).
            <?php } else { ?>
                <?= $locale->msg('Price shown is without VAT') ?>
                (<a href="<?= $locale->route('/?vat') ?>"><?= $locale->msg('show with VAT') ?></a>).
            <?php } ?>

            <?= $locale->msgf(
                'disclaimer',
                '<a href="/nordpool-' . $locale->get('code_lc') . '-15.csv">' .
                $locale->msg('normal CSV') .
                '</a>',
                '<a href="/nordpool-' . $locale->get('code_lc') . '-excel-15.csv">' .
                $locale->msg('Excel CSV') .
                '</a>'
            ) ?>
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
