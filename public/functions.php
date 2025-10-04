<?php

// Simple PSR-4 autoloader
spl_autoload_register(function ($class) {
    $file = __DIR__ . '/../src/' . $class . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

if (php_sapi_name() == 'cli-server') {
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
        return false;
    }
}

function dd(...$vars): void
{
    $vars = func_get_args();
    foreach ($vars as $var) {
        echo '<pre>';
        var_dump($var);
        echo '</pre>';
    }
    die(1);
}

function abort(int $code = 500, string $message = ''): void
{
    http_response_code($code);
    if ($message) {
        echo $message;
    }
    die(1);
}

function format($number): string
{
    $num = number_format($number, 4);
    return substr($num, 0, strpos($num, '.') + 3) .
        '<span class="extra-decimals">' . substr($num, -2) . '</span>';
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

function getTranslations(): array
{
    return [
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
            'LV' => 'Dati par rītdienu parādās agrā pēcpusdienā vai arī tad, kad parādās. Avots: Nordpool day-ahead stundas spotu cenas, LV. Krāsa atspoguļo cenu sāļumu konkrētajā dienā, nevis visā tabulā. Attēlotais ir Latvijas laiks. Dati pieejami arī kā %s, kā %s vai %s. Dati tiek atjaunoti reizi dienā ap 12:00 ziemā un ap 11:00 vasarā.<br/>
        Kontaktiem un jautājumiem: <a href="mailto:apps@didnt.work">apps@didnt.work</a>.',
            'LT' => 'Ryto duomenys pasirodo ankstyvą popietę arba kai tik jie pasirodo. Šaltinis: Nordpool day-ahead valandos spot kainos, LT. Spalva atspindi kainų druskingumą konkrečią dieną, o ne visoje lentelėje. Rodomas Lietuvos laikas. Duomenys taip pat prieinami kaip %s, kaip %s, ir kaip %s. Duomenys atnaujinami kartą per dieną apie 12:00 žiemą ir apie 11:00 vasarą.<br/>
        Kontaktams ir klausimams: <a href="mailto:apps@didnt.work">apps@didnt.work</a> (pageidautina latviškai arba angliškai).',
            'EE' => 'Homme andmed ilmuvad varakult pärastlõunal või kui need ilmuvad. Allikas: Nordpool day-ahed tundide spot hinnad, EE. Värv peegeldab hinna soolsust konkreetsel päeval, mitte kogu tabelis. Kuvatakse Eesti aeg. Andmed on saadaval ka %s, %s ja %s kujul. Andmeid uuendatakse üks kord päevas umbes 12:00 paiku talvel ja umbes 11:00 suvel.<br/>
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
        'subtitle' => [
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
        ],
        '15min notice' => [
            'LV' => 'Sākot ar 1. oktobri, biržas cenas tiek noteiktas ar 15 minūšu soli. Iepriekš solis bija stunda. Tas nekur nav pazudis. Saite ir augšā.',
            'LT' => 'Alates 1. oktoobrist määratakse börsihinnad 15-minutilise sammuga. Varem oli samm tund. See pole kuhugi kadunud. Link on üleval.',
            'EE' => 'Nuo spalio 1 d. biržos kainos nustatomos 15 minučių intervalu. Anksčiau intervalas buvo valanda. Tai niekur nedingo. Nuoroda yra viršuje.',
        ],
        'Resolution' => [
            'LV' => 'Uzskaites solis',
            'LT' => 'Apskaitos žingsnis',
            'EE' => 'Raamatupidamise samm',
        ],
        'show 1h' => [
            'LV' => 'rādīt 1h',
            'LT' => 'rodyti 1h',
            'EE' => 'näita 1h',
        ],
        'show 15min' => [
            'LV' => 'rādīt 15min',
            'LT' => 'rodyti 15min',
            'EE' => 'näita 15min',
        ],
        '1h average' => [
            'LV' => '1h vidējie dati',
            'LT' => '1h vidutiniai duomenys',
            'EE' => '1h keskmised andmed',
        ],
        '15min data' => [
            'LV' => '15min dati',
            'LT' => '15min duomenys',
            'EE' => '15min andmed',
        ],
    ];
}

function getCountryConfig(?string $country = null): array
{
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

    if ($country === null) {
        return $countryConfig;
    }
    return $countryConfig[$country] ?? $countryConfig['LV'];
}

class AppLocale
{
    private IntlDateFormatter $dateFormatter;

    public function __construct(
        public ?array  $config,
        public ?array  $translations,
        public ?string $country = 'LV',
    )
    {
        if ($config === null || $this->translations === null) {
            $this->config = getCountryConfig($country);
            $this->translations = getTranslations();
        }
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

class Cache {
    const string DIR = __DIR__ . '/../cache/';

    public static function get(string $key, mixed $default = null): mixed
    {
        $file = self::DIR . md5($key) . '.cache';
        if (file_exists($file) && (time() - filemtime($file) < 3600)) {
            return unserialize(file_get_contents($file));
        }
        return $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $file = self::DIR . md5($key) . '.cache';
        file_put_contents($file, serialize($value), LOCK_EX);
    }

    public static function delete(string $key): void
    {
        $file = self::DIR . md5($key) . '.cache';
        if (file_exists($file)) {
            unlink($file);
        }
    }

    public static function clear(): void
    {
        $files = glob(self::DIR . '*.cache');
        foreach ($files as $file) {
            unlink($file);
        }
    }
}

class Lock {
    const string DIR = __DIR__ . '/../cache/';

    private string $file;
    private $handle;

    public function __construct(string $key)
    {
        $this->file = self::DIR . md5($key) . '.lock';
        $this->handle = fopen($this->file, 'w+');
    }

    public function __destruct()
    {
        $this->unlock();
        fclose($this->handle);
        if (file_exists($this->file)) {
            unlink($this->file);
        }
    }

    public function lock(): bool
    {
        return flock($this->handle, LOCK_EX);
    }

    public function unlock(): bool
    {
        return flock($this->handle, LOCK_UN);
    }
}
