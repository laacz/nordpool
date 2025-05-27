<?php

function format($number): string
{
    $num = number_format($number, 4);
    return substr($num, 0, strpos($num, '.') + 3) . '<span class="extra-decimals">' . substr($num, -2) . '</span>';
}

# 19720c, 36720c, 720c0c
$percentColors = [
    // ['pct' => 0.0, 'color' => ['r' => 0x00, 'g' => 0xff, 'b' => 0]],
    // ['pct' => 0.5, 'color' => ['r' => 0xff, 'g' => 0xff, 'b' => 0]],
    // ['pct' => 1.0, 'color' => ['r' => 0xff, 'g' => 0x00, 'b' => 0]],
    ['pct' => 0.0, 'color' => ['r' => 0x00, 'g' => 0x88, 'b' => 0x00]],
    ['pct' => 0.5, 'color' => ['r' => 0xaa, 'g' => 0xaa, 'b' => 0x00]],
    ['pct' => 1.0, 'color' => ['r' => 0xaa, 'g' => 0x00, 'b' => 0x00]],
];

function getColorPercentage($value, $min, $max): string
{
    if ($value === -9999) {
        return '#fff';
    }

    $pct = ($max - $min) == 0 ? 0 : ($value - $min) / ($max - $min);

    global $percentColors;

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

$countryConfig = [
    'LV' => [
        'code_lc' => 'lv',
        'code' => 'LV',
        'name' => 'Latvija',
        'flag' => '🇱🇻',
        'locale' => 'lv_LV',
        'vat' => 0.21,
    ],
    'LT' => [
        'code_lc' => 'lt',
        'code' => 'LT',
        'name' => 'Lietuva',
        'flag' => '🇱🇹',
        'locale' => 'lt_LT',
        'vat' => 0.21,
    ],
    'EE' => [
        'code_lc' => 'ee',
        'code' => 'EE',
        'name' => 'Eesti',
        'flag' => '🇪🇪',
        'locale' => 'et_EE',
        'vat' => 0.20,
    ],
];

$translations = [
    'Primitīvs grafiks' => [
        'LV' => 'Primitīvs grafiks',
        'LT' => 'Paprastas grafikas',
        'EE' => 'Lihtne joonis',
    ],
    'normal CSV' => [
        'LV' => 'parasts CSV',
        'LT' => 'įprastas CSV',
        'EE' => 'tavalise CSV',
    ],
    'Excel CSV' => [
        'LV' => 'Excel\'im piemērots CSV',
        'LT' => 'Excel\'ui tinkamas CSV',
        'EE' => 'Excel\'ile sobiva CSV',
    ],

    'disclaimer' => [
        'LV' => 'Dati par rītdienu parādās agrā pēcpusdienā vai arī tad, kad parādās. Avots: Nordpool day-ahead stundas spotu cenas, LV. Krāsa atspoguļo cenu sāļumu konkrētajā dienā, nevis visā tabulā. Attēlotais ir Latvijas laiks. Dati pieejami arī kā %s un kā %s. Dati tiek atjaunoti reizi dienā ap 12:00 ziemā un ap 11:00 vasarā.<br/>
        Kontaktiem un jautājumiem: <a href="mailto:apps@didnt.work">apps@didnt.work</a>.',
        'LT' => 'Ryto duomenys pasirodo ankstyvą popietę arba kai tik jie pasirodo. Šaltinis: Nordpool day-ahead valandos spot kainos, LT. Spalva atspindi kainų druskingumą konkrečią dieną, o ne visoje lentelėje. Rodomas Lietuvos laikas. Duomenys taip pat prieinami kaip %s ir kaip %s. Duomenys atnaujinami kartą per dieną apie 12:00 žiemą ir apie 11:00 vasarą.<br/>
        Kontaktams ir klausimams: <<a href="mailto:apps@didnt.work">apps@didnt.work</a> (pageidautina latviškai arba angliškai).',
        'EE' => 'Homme andmed ilmuvad varakult pärastlõunal või kui need ilmuvad. Allikas: Nordpool day-ahed tundide spot hinnad, EE. Värv peegeldab hinna soolsust konkreetsel päeval, mitte kogu tabelis. Kuvatakse Eesti aeg. Andmed on saadaval ka %s ja %s kujul. Andmeid uuendatakse üks kord päevas umbes 12:00 paiku talvel ja umbes 11:00 suvel.<br/>
        Kontaktide ja küsimuste jaoks: <a href="mailto:apps@didnt.work">apps@didnt.work</a> (eelistatavalt läti või inglise keeles).',
    ],
    'Price shown is without VAT' => [
        'LV' => 'Atspoguļotā cena ir bez PVN',
        'LT' => 'Rodoma kaina be PVM',
        'EE' => 'Näidatud hind on ilma käibemaksuta',
    ],
    'Atspoguļotā cena iekļauj PVN (rādīt bez PVN)' => [
        'LV' => 'Atspoguļotā cena iekļauj PVN',
        'LT' => 'Rodoma kaina su PVM',
        'EE' => 'Näidatud hind on käibemaksuga',
    ],
    'subtitle'  => [
        'LV' => 'Nordpool elektrības biržas SPOT cenas šodienai un rītdienai Latvijā.',
        'LT' => 'Nordpool elektros biržos SPOT kainos šiandien ir rytoj Lietuvoje',
        'EE' => 'Nordpooli elektribörsi SPOT hinnad tänaseks ja homseks Eestis',
    ],
    'it is without VAT' => [
        'LV' => 'Tās ir <strong>bez PVN</strong>',
        'LT' => 'Jie yra <strong>be PVM</strong>',
        'EE' => 'Need on <strong>ilma käibemaksuta</strong>',
    ],
    'it is with VAT' => [
        'LV' => 'Tā ir <strong>ar PVN</strong>',
        'LT' => 'Tai <strong>aipima PVM</strong>',
        'EE' => 'Need <stgrong>on käibemaksuga</strong>',
    ],
    'show with VAT' => [
        'LV' => 'rādīt ar PVN',
        'LT' => 'rodyti su PVM',
        'EE' => 'näita KM-ga',
    ],
    'show without VAT' => [
        'LV' => 'rādīt bez PVN',
        'LT' => 'rodyti be PVM',
        'EE' => 'näita ilma KM-ta',
    ],
    'Izvairāmies tērēt elektrību' => [
        'LV' => 'Izvairāmies tērēt elektrību',
        'LT' => 'Venkime švaistyti elektros energiją',
        'EE' => 'Vältige elektri raiskamist',
    ],
    'Krājam burciņā' => [
        'LV' => 'Krājam burciņā',
        'LT' => 'Kaupkime stiklainėje',
        'EE' => 'Kogume purki',
    ],
    'title' => [
        'LV' => 'Nordpool elektrības cenas (day-ahead, hourly, LV)',
        'LT' => 'Nordpool elektros kainos (day-ahead, hourly, LT)',
        'EE' => 'Nordpool elektrihinnad (day-ahead, hourly, EE)',
    ],
    'Šodien' => [
        'LV' => 'Šodien',
        'LT' => 'Šiandien',
        'EE' => 'Täna',
    ],
    'Rīt' => [
        'LV' => 'Rīt',
        'LT' => 'Rytoj',
        'EE' => 'Homme',
    ],
    'Vidēji' => [
        'LV' => 'Vidēji',
        'LT' => 'Vidutiniškai',
        'EE' => 'Keskmine',
    ]
];


class AppLocale
{
    private IntlDateFormatter $dateFormatter;

    public function __construct(
        public array $config,
        public array $translations,
    ) {
        $this->dateFormatter = IntlDateFormatter::create(
            $this->config['locale'],
            IntlDateFormatter::FULL,
            IntlDateFormatter::NONE,
            'Europe/Riga',
            null,
            'd.m.Y H:i'
        );
    }

    /*
    * Format a date/time string according to the specified format and locale in the $country['locale'].
    * Format string is
    * based on ICU DateTime format.
    *
    * @param mixed $time The date/time to format. Can be a DateTime object, timestamp, or string.
    * @param string $format The format string. Default is 'd.m.Y H:i'.
    * @return string The formatted date/time string.
    */
    public function formatDate(mixed $time, string $format = 'd.m.Y H:i'): string|false
    {
        if (!$this->dateFormatter->setPattern($format)) {
            return false;
        }

        return $this->dateFormatter->format($time);
    }

    public function get(string $key, mixed $default = null): string
    {
        return $this->config[$key] ?? $default;
    }

    function msg(string $msg): string
    {
        return $this->translations[$msg][$this->config['code']] ?? $msg;
    }

    public function msgf(string $msg, ...$args): string
    {
        return vsprintf($this->msg($msg), $args);
    }

    function route(string $route): string
    {
        $lang = match ($this->config['code']) {
            'LV' => '',
            default => $this->config['code_lc'] . '/',
        };
        return '/' . $lang . ltrim($route, '/');
    }
}
