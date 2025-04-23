<?php

$DB = new PDO('sqlite:../nordpool.db');

$prices = [];
$tz_riga = new DateTimeZone('Europe/Riga');
$tz_cet = new DateTimeZone('Europe/Berlin');

$vat = isset($_GET['vat']);

function debug(...$vars): void
{
    $vars = func_get_args();
    if (isset($_GET['debug'])) {
        foreach ($vars as $var) {
            echo '<pre>';
            var_dump($var);
            echo '</pre>';
        }
    }
}

foreach ($DB->query("SELECT * FROM spot_prices WHERE ts_start >= DATE(CURRENT_TIMESTAMP, '-30 day') ORDER BY ts_start DESC") as $row) {
    try {
        $start = new DateTime($row['ts_start'], $tz_cet);
        $end = new DateTime($row['ts_end'], $tz_cet);
        $start->setTimeZone($tz_riga);
        $end->setTimeZone($tz_riga);
        $prices[$start->format('Y-m-d')][$start->format('H') . '-' . $end->format('H')] = round(($vat ? 1.21 : 1) * ((float)$row['value']) / 1000, 4);
    } catch (Exception $e) {
        continue;
    }
}

$today = $prices[date('Y-m-d')] ?? [];
$tomorrow = $prices[date('Y-m-d', strtotime('tomorrow'))] ?? [];
$today_avg = count($today) === 24 ? array_sum($today)/count($today) : null;
$tomorrow_avg = count($tomorrow) === 24 ? array_sum($tomorrow)/count($tomorrow) : null;

$today_max = count($today) ? max($today) : 0;
$today_min = count($today) ? min($today) : 0;
$tomorrow_max = count($tomorrow) ? max($tomorrow) : 0;
$tomorrow_min = count($tomorrow) ? min($tomorrow) : 0;

function getColorPercentage($value, $min, $max): string
{
    if (!$value) {
        return '#fff';
    }
    $pct = ($max - $min) == 0 ? 0 : ($value - $min) / ($max - $min);

    $percentColors = [
        ['pct' => 0.0, 'color' => ['r' => 0x00, 'g' => 0xff, 'b' => 0]],
        ['pct' => 0.5, 'color' => ['r' => 0xff, 'g' => 0xff, 'b' => 0]],
        ['pct' => 1.0, 'color' => ['r' => 0xff, 'g' => 0x00, 'b' => 0]],
    ];


    for ($i = 1; $i < count($percentColors) - 1; $i++) {
        if ($pct < $percentColors[$i]['pct']) {
            break;
        }
    }

    $lower = $percentColors[$i - 1];
    $upper = $percentColors[$i];
    $range = $upper['pct'] - $lower['pct'];
    $rangePct = ($pct - $lower['pct']) / $range;
    $pctLower = 1 - $rangePct;
    $pctUpper = $rangePct;
    $color = [
        'r' => floor($lower['color']['r'] * $pctLower + $upper['color']['r'] * $pctUpper),
        'g' => floor($lower['color']['g'] * $pctLower + $upper['color']['g'] * $pctUpper),
        'b' => floor($lower['color']['b'] * $pctLower + $upper['color']['b'] * $pctUpper),
    ];
    return 'rgb(' . implode(',', [$color['r'], $color['g'], $color['b']]) . ')';
}

$months = [
    '',
    'jan',
    'feb',
    'mar',
    'apr',
    'mai',
    'jūn',
    'jūl',
    'aug',
    'sep',
    'okt',
    'nov',
    'dec',
];

function format($number): string
{
    $num = number_format($number, 4);
    return substr($num, 0, strpos($num, '.') + 3) . '<span class="extra-decimals">' . substr($num, -2) . '</span>';
}

$now = date('H') . '-' . date('H', strtotime('+1 hour'));

foreach ($prices as $k => $day) {
    ksort($prices[$k]);
}

$hours = array_keys($today);
asort($hours);

?><!doctype html>
<html lang="en">
<head>
<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-CRFT0MS7XN"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', 'G-CRFT0MS7XN');
</script>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Nordpool elektrības cenas (day-ahead, hourly, LV)</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fira+Sans:wght@100;400;700&display=swap" rel="stylesheet">

    <!--suppress CssUnusedSymbol -->
    <style>

        body {
            font-family: 'fira sans', sans-serif;
        }

        h1 {
            font-size: 2rem;
            text-align: center;
        }

        #app footer p {
            font-size: smaller;
            text-align: left;
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
            text-align: center;
            line-height: 1.5;
        }

        table {
            border-collapse: collapse;
            margin: 0 auto;
            /*width: 100%;*/
            width: auto;
        }

        th, td {
            padding: 5px 10px;
            border: 2px solid #fff;
        }

        table tbody tr th {
            width: 1%;
            white-space: nowrap;
        }

        tr.now {
            outline: 3px solid #f00;
        }

        .price {
            text-align: right;
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
            background-color: #0f0;
        }

        .bad {
            background-color: #f00;
            color: #fff;
        }

        .extra-decimals {
            opacity: .4;
        }
    </style>
</head>
<body>

<div id="app">

    <h1>
        🔌🏷️ €/kWh
    </h1>

    <p>Atspoguļotā elektrības cena biržā ir
        <?php if ($vat) { ?>
            <strong>ar</strong> PVN (<a href="./">te ir bez PVN</a>).
        <?php } else { ?>
            <strong>bez</strong> PVN (<a href="?vat">te ir ar PVN</a>).
        <?php } ?>
    </p>

<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-4590024878280519"
     crossorigin="anonymous"></script>
<!-- nordpool header -->
<ins class="adsbygoogle"
     style="display:block"
     data-ad-client="ca-pub-4590024878280519"
     data-ad-slot="9106834831"
     data-ad-format="auto"
     data-full-width-responsive="true"></ins>
<script>
     (adsbygoogle = window.adsbygoogle || []).push({});
</script>

    <table>
        <thead>
        <tr>
            <th>🕑</th>
            <th>Šodien
                <span class="help"><?=date('d. ') . $months[date('n')]?></span>
                <?php if ($today_avg) { ?>
                <br/><small>Vidēji <span><?=format($today_avg)?></span></small>
                <?php } ?>
            </th>
            <th>Rīt
                <span class="help"><?=date('d. ', strtotime('tomorrow')) . $months[date('n', strtotime('tomorrow'))]?></span>
                <?php if ($tomorrow_avg) { ?>
                <br/><small>Vidēji <span><?=format($tomorrow_avg)?></span></small>
                <?php } ?>
            </th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($hours as $hour) { ?>
            <tr data-hours="<?=(int)$hour?>">
                <th><?=$hour?></th>
                <td class="price"
                    style="background-color: <?=getColorPercentage($today[$hour] ?? 0, $today_min, $today_max)?>"><?=isset($today[$hour]) ? format($today[$hour]) : '-'?></td>
                <td class="price"
                    style="<?=isset($tomorrow[$hour]) ? '' : 'text-align: center; '?>background-color: <?=getColorPercentage($tomorrow[$hour] ?? 0, $tomorrow_min, $tomorrow_max)?>"><?=isset($tomorrow[$hour]) ? format($tomorrow[$hour]) : '-'?></td>
            </tr>
        <?php } ?>
        </tbody>
    </table>

    <footer>
        <p>
            <span class="legend bad">Izvairamies tērēt elektrību</span> <span class="legend good">Krājam burciņā</span>
        </p>
        <p>
            Atspoguļotā cena ir
            <?php if ($vat) { ?>
                ar PVN (<a href="./">var arī bez PVN</a>).
            <?php } else { ?>
                bez PVN (<a href="?vat">var arī ar PVN</a>).
            <?php } ?>
            Dati par rītdienu parādās agrā pēcpusdienā vai arī tad, kad parādās. Avots:
            Nordpool day-ahead stundas spotu
            cenas, LV. Krāsa atspoguļo cenu sāļumu konkrētajā dienā, nevis visā tabulā. Attēlotais ir Latvijas laiks.
            Dati pieejami arī <a href="nordpool.csv">kā parasts CSV</a> un <a href="nordpool-excel.csv">kā Excel'im
                piemērots CSV</a>.
        </p>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            let hours = null;
            (function updateNow() {
                const currentHours = (new Date()).getHours();
                if (hours !== currentHours) {
                    Array.from(document.querySelectorAll('[data-hours]')).forEach((row) => {
                        row.classList.toggle('now', currentHours === parseInt(row.dataset.hours))
                    })
                    hours = currentHours;
                }
                setTimeout(updateNow, 1000);
            })()
        })
    </script>

</div>
</body>
</html>
