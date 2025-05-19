<?php
require('functions.php');

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

$vat = $locale->get('vat');

$current_time = new DateTimeImmutable('now', $tz_riga);
if (isset($_GET['now'])) {
    $current_time = new DateTimeImmutable($_GET['now'], $tz_riga);
}

$current_time_cet = $current_time->setTimezone($tz_cet);
$sql_time = $current_time_cet->format('Y-m-d H:i:s');

$sql = "
SELECT * 
  FROM spot_prices 
 WHERE country = " . $DB->quote($locale->get('code')) . " 
   AND ts_start >= DATE(" . $DB->quote($sql_time) . ", '-2 day')
   AND ts_start <= DATE(" . $DB->quote($sql_time) . ", '+3 day')
ORDER BY ts_start DESC
";

foreach ($DB->query($sql) as $row) {
    try {
        $start = new DateTime($row['ts_start'], $tz_cet);
        $end = new DateTime($row['ts_end'], $tz_cet);
        $start->setTimeZone($tz_riga);
        $end->setTimeZone($tz_riga);
        $prices[$start->format('Y-m-d')][$start->format('H') . '-' . $end->format('H')] = round(($with_vat ? 1 + $vat : 1) * ((float)$row['value']) / 1000, 4);
    } catch (Exception $e) {
        continue;
    }
}

$today = $prices[$current_time->format('Y-m-d')] ?? [];
$tomorrow = $prices[$current_time->modify('+1 day')->format('Y-m-d')] ?? [];

$today_avg = count($today) === 24 ? array_sum($today) / count($today) : null;
$tomorrow_avg = count($tomorrow) === 24 ? array_sum($tomorrow) / count($tomorrow) : null;

$today_max = count($today) ? max($today) : 0;
$today_min = count($today) ? min($today) : 0;
$tomorrow_max = count($tomorrow) ? max($tomorrow) : 0;
$tomorrow_min = count($tomorrow) ? min($tomorrow) : 0;

$now = $current_time->format('H') . '-' . $current_time->modify('+1 hour')->format('H');

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

    <table>
        <thead>
        <tr>
            <th>🕑</th>
            <th><?= $locale->msg('Šodien') ?>
                <span class="help"><?= $locale->formatDate($current_time, 'd. MMM') ?></span><br/>
                <small><?= $locale->msg('Vidēji') ?> <span><?= $today_avg ? format($today_avg) : '—' ?></span></small>
                </small>
            </th>
            <th><?= $locale->msg('Rīt') ?>
                <span
                        class="help"><?= $locale->formatDate($current_time->modify('+1 day'), 'd. MMM') ?></span><br/>
                <small><?= $locale->msg('Vidēji') ?> <span><?= $tomorrow_avg ? format($tomorrow_avg) : '—' ?></span>
                </small>
            </th>
        </tr>
        </thead>
        <tbody>
        <?php
        $legend = [];
        $values['today'] = [];
        $values['tomorrow'] = [];
        ?>
        <?php foreach ($hours as $hour) {
            $legend[] = explode('-', $hour)[0];
            $values['today'][$hour] = $today[$hour] ?? 0;
            $values['tomorrow'][$hour] = $tomorrow[$hour] ?? 0;
            ?>
            <tr data-hours="<?= (int)$hour ?>">
                <th><?= $hour ?></th>
                <td class="price"
                    style="background-color: <?= getColorPercentage($today[$hour] ?? -9999, $today_min, $today_max) ?>">
                    <?= isset($today[$hour]) ? format($today[$hour]) : '-' ?>
                </td>
                <td class="price"
                    style="<?= isset($tomorrow[$hour]) ? '' : 'text-align: center; ' ?>background-color: <?= getColorPercentage($tomorrow[$hour] ?? -9999, $tomorrow_min, $tomorrow_max) ?>">
                    <?= isset($tomorrow[$hour]) ? format($tomorrow[$hour]) : '-' ?>
                </td>
            </tr>
        <?php } ?>

        <?php
        $legend[] = '00';
        $values['today'][] = $tomorrow['00-01'] ?? 0;
        ?>
        </tbody>
    </table>

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
                    let hour = parseInt(params[0].name, 10);
                    let value = parseFloat(params[0].value);
                    let strValue = value.toString().padEnd(4, '0').substring(0, 4);

                    strValue += '<small>';
                    strValue += value.toString().substring(4).padEnd(2, '0');
                    strValue += '</small> €/kWh'
                    let html = `
                        ${('' + hour).padStart(2, '0')}:00 - ${('' + (hour + 1) % 24).padStart(2, '0')}:00<br/>
                        ${strValue}
                        `;
                    return html;
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
                '<a href="/nordpool-' . $locale->get('code_lc') . '.csv">' .
                $locale->msg('normal CSV') .
                '</a>',
                '<a href="/nordpool-' . $locale->get('code_lc') . '-excel.csv">' .
                $locale->msg('Excel CSV') .
                '</a>'
            ) ?>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // now let's highlight the   hour continuously
            let hours = null;
            (function updateNow() {
                const currentHours = (new Date('<?=$current_time->format('Y-m-d H:i:s')?>')).getHours();
                if (hours !== currentHours) {
                    Array.from(document.querySelectorAll('[data-hours]')).forEach((row) => {
                        row.classList.toggle('now', currentHours === parseInt(row.dataset.hours))
                    })
                    hours = currentHours;
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
        })
    </script>
</div>
</body>

</html>
