<?php
$ret = require 'functions.php';
if (!$ret) {
    return false;
}

try {
    $request = new Request;
    $router = new Router();
    $view = new View();

    // RSS routes
    $router->get('rss', function (Request $request, array $params) use ($view) {
        handleRss($request, $params, $view);
    });

    $router->get('{country}/rss', function (Request $request, array $params) use ($view) {
        handleRss($request, $params, $view);
    });

    // Main routes with redirect for old ?rss parameter
    $router->get('', function (Request $request, array $params) use ($view) {
        if ($request->has('rss')) {
            header('Location: /rss', true, 301);
            exit;
        }
        handleIndex($request, $params, $view);
    });

    $router->get('{country}', function (Request $request, array $params) use ($view) {
        if ($request->has('rss')) {
            $country = strtolower($params['country'] ?? 'lv');
            header("Location: /$country/rss", true, 301);
            exit;
        }
        handleIndex($request, $params, $view);
    });

    $router->dispatch($request);
} catch (Exception) {
    abort();
}

/**
 * @throws \Exception
 */
function handleRss(Request $request, array $params, View $view): void
{
    $countryConfig = Config::getCountries();
    $translations = Config::getTranslations();

    $country = strtoupper($params['country'] ?? 'lv');
    if (!isset($countryConfig[$country])) {
        $country = 'LV';
    }

    $locale = new AppLocale($countryConfig[$country], $translations);
    $vat = (float)$locale->get('vat');

    $tz_local = new DateTimeZone($locale->get('timezone'));
    $local_tomorrow_start = new DateTimeImmutable('tomorrow', $tz_local);
    $local_tomorrow_end = new DateTimeImmutable('today', $tz_local)->modify('+2 day');
    $current_time = new DateTimeImmutable($request->get('now', 'now'), $tz_local);

    $DB = new PDO('sqlite:../nordpool.db');
    $priceRepo = new PriceRepository($DB);

    $data = $priceRepo->getPrices($local_tomorrow_start, $local_tomorrow_end, strtoupper($country));
    if (count($data) < 5) {
        $data = [];
    }

    header('Content-Type: application/xml; charset=utf-8');
    $view->render('rss', [
        'local_tomorrow_start' => $local_tomorrow_start,
        'country' => $country,
        'current_time' => $current_time,
        'data' => $data,
        'tz_local' => $tz_local,
        'vat' => $vat,
    ]);
}

/**
 * @throws Exception
 */
function handleIndex(Request $request, array $params, View $view): void
{
    $countryConfig = Config::getCountries();
    $translations = Config::getTranslations();

    $country = strtoupper($params['country'] ?? 'lv');
    if (!isset($countryConfig[$country])) {
        $country = 'LV';
    }

    $resolution = $request->get('res') == '60' ? 60 : 15;
    $locale = new AppLocale($countryConfig[$country], $translations);
    $vat = (float)$locale->get('vat');

    $tz_local = new DateTimeZone($locale->get('timezone'));
    $local_start = new DateTimeImmutable('today', $tz_local);
    $local_tomorrow_end = $local_start->modify('+2 day');
    $current_time = new DateTimeImmutable($request->get('now', 'now'), $tz_local);

    // Handle cache invalidation
    $mtime = stat('../nordpool.db')['mtime'] ?? 0;
    $cmtime = Cache::get('last_db_mtime', 0);

    if ($cmtime === 0 || $mtime === 0 || (int)$mtime !== (int)$cmtime || $request->has('purge')) {
        Cache::clear();
        Cache::set('last_db_mtime', $mtime);
    }

    // Check cache
    $with_vat = $request->has('vat');
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

    // Fetch and process prices
    $DB = new PDO('sqlite:../nordpool.db');
    $priceRepo = new PriceRepository($DB);

    $rows = $priceRepo->getPrices($local_start, $local_tomorrow_end, $locale->get('code'));

    $collection = new PriceCollection($rows);
    $prices = $collection->toGrid($tz_local, $resolution === 60, $with_vat ? 1 + $vat : 1);

    $today = $prices[$current_time->format('Y-m-d')] ?? [];
    $tomorrow = $prices[$current_time->modify('+1 day')->format('Y-m-d')] ?? [];

    // Calculate statistics
    $today_flat = $today ? array_merge(...array_map('array_values', $today)) : [];
    $tomorrow_flat = $tomorrow ? array_merge(...array_map('array_values', $tomorrow)) : [];

    $expected_count = $resolution === 60 ? 24 : 96;
    $today_avg = count($today_flat) === $expected_count ? array_sum($today_flat) / count($today_flat) : null;
    $tomorrow_avg = count($tomorrow_flat) === $expected_count ? array_sum($tomorrow_flat) / count($tomorrow_flat) : null;

    $today_max = $today_flat ? max($today_flat) : 0;
    $today_min = $today_flat ? min($today_flat) : 0;
    $tomorrow_max = $tomorrow_flat ? max($tomorrow_flat) : 0;
    $tomorrow_min = $tomorrow_flat ? min($tomorrow_flat) : 0;

    $quarters_per_hour = $resolution == 15 ? 4 : 1;

    // Render view
    $view->render('index', [
        'locale' => $locale,
        'countryConfig' => $countryConfig,
        'country' => $country,
        'resolution' => $resolution,
        'with_vat' => $with_vat,
        'vat' => $vat,
        'current_time' => $current_time,
        'viewHelper' => new ViewHelper(),
        'today' => $today,
        'tomorrow' => $tomorrow,
        'today_avg' => $today_avg,
        'tomorrow_avg' => $tomorrow_avg,
        'today_max' => $today_max,
        'today_min' => $today_min,
        'tomorrow_max' => $tomorrow_max,
        'tomorrow_min' => $tomorrow_min,
        'quarters_per_hour' => $quarters_per_hour,
    ]);

    $html = ob_get_clean();
    echo $html;
    Cache::set($cache_key, $html);
}
