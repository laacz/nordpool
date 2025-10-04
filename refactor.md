# Refactoring Plan: public/index.php

## Goals

- Transform 990-line monolith into maintainable, testable code
- **Zero visual changes** - UI stays exactly the same
- **Zero URL changes** - all routes work identically
- **100% backwards compatibility** - existing functionality preserved
- Code you'd be proud to show anyone

## Principles

- One small step at a time
- Test after each step
- Verify in browser after each step
- No frameworks, no over-engineering
- Simple, clear, maintainable
- Do that in separate git branch called `refactory`.
- Commit after each step. Use one line commit messages `refactor: ...` and don't add anything else. Even claude references.

---

## Step 0: Setup Pest Testing Framework

### Goal

Get testing infrastructure working before any refactoring.

### Tasks

1. Add Pest to composer.json:

   ```bash
   composer require pestphp/pest --dev --with-all-dependencies
   ```

2. Initialize Pest:

   ```bash
   ./vendor/bin/pest --init
   ```

3. Create `tests/Pest.php` for shared setup

4. Write first sanity test in `tests/SanityTest.php`:

   ```php
   test('math works', function () {
       expect(1 + 1)->toBe(2);
   });
   ```

5. Run tests: `./vendor/bin/pest`

### Verification

- ✅ Pest runs successfully
- ✅ Green test output
- ✅ Application still works in browser

### Dependencies

None - this is the foundation.

---

## Step 1: Extract Request Handling

### Goal

Replace direct `$_GET`, `$_SERVER` access with testable Request object.

### Why This Step?

- Currently: 15+ direct global accesses scattered throughout
- Hard to test code that depends on superglobals
- This unblocks testing all subsequent code

### Tasks

1. Create `src/Request.php`:

   ```php
   <?php
   class Request {
       public function __construct(
           private array $get = [],
           private array $server = []
       ) {
           $this->get = $get ?: $_GET;
           $this->server = $server ?: $_SERVER;
       }

       public function get(string $key, mixed $default = null): mixed {
           return $this->get[$key] ?? $default;
       }

       public function server(string $key, mixed $default = null): mixed {
           return $this->server[$key] ?? $default;
       }

       public function uri(): string {
           return trim($this->server['REQUEST_URI'] ?? '', '/');
       }

       public function path(): string {
           return explode('?', $this->uri())[0];
       }

       public function has(string $key): bool {
           return isset($this->get[$key]);
       }
   }
   ```

2. Write `tests/RequestTest.php`:

   ```php
   test('gets query parameter', function () {
       $request = new Request(['foo' => 'bar'], []);
       expect($request->get('foo'))->toBe('bar');
   });

   test('returns default for missing parameter', function () {
       $request = new Request([], []);
       expect($request->get('missing', 'default'))->toBe('default');
   });

   test('parses URI path', function () {
       $request = new Request([], ['REQUEST_URI' => '/lv/something?foo=bar']);
       expect($request->path())->toBe('lv/something');
   });

   test('checks parameter existence', function () {
       $request = new Request(['vat' => ''], []);
       expect($request->has('vat'))->toBeTrue();
       expect($request->has('missing'))->toBeFalse();
   });
   ```

3. Update `public/index.php`:
   - Add at top: `$request = new Request();`
   - Replace `$_SERVER['REQUEST_URI']` with `$request->uri()`
   - Replace `$_GET['now']` with `$request->get('now')`
   - Replace `$_GET['res']` with `$request->get('res')`
   - Replace `$_GET['vat']` with `$request->has('vat')`
   - Replace `$_GET['rss']` with `$request->has('rss')`
   - Replace `$_GET['purge']` with `$request->has('purge')`
   - Replace `$_SERVER['HTTP_HOST']` with `$request->server('HTTP_HOST')`

### Verification

- ✅ `./vendor/bin/pest` passes
- ✅ Homepage loads: http://localhost:8000/
- ✅ Country routes work: http://localhost:8000/lt, http://localhost:8000/ee
- ✅ VAT toggle works: `?vat`
- ✅ Resolution works: `?res=60`
- ✅ RSS works: `?rss`
- ✅ Cache purge works: `?purge`

### Rollback Strategy

If anything breaks, revert index.php changes and keep tests for later.

---

## Step 2: Extract Database Access Layer

### Goal

Move all SQL queries into a dedicated repository class.

### Why This Step?

- Currently: SQL scattered in multiple places (RSS section, main query)
- Hard to test business logic that's coupled to database
- Need to prepare data layer before extracting business logic

### Tasks

1. Create `src/PriceRepository.php`:

   ```php
   <?php
   class PriceRepository {
       public function __construct(private PDO $db) {}

       public function getPricesForDateRange(
           string $fromDate,
           string $toDate,
           string $country,
           int $resolution = 15
       ): array {
           $sql = "
               SELECT *
               FROM price_indices
               WHERE country = :country
                 AND ts_start >= DATE(:from, '-2 day')
                 AND ts_start <= DATE(:to, '+3 day')
                 AND resolution_minutes = :resolution
               ORDER BY ts_start DESC
           ";

           $stmt = $this->db->prepare($sql);
           $stmt->execute([
               'country' => $country,
               'from' => $fromDate,
               'to' => $toDate,
               'resolution' => $resolution,
           ]);

           return $stmt->fetchAll(PDO::FETCH_ASSOC);
       }

       public function getTomorrowPrices(
           string $country,
           string $tomorrowDate
       ): array {
           $sql = "
               SELECT ts_start, ts_end, value, resolution_minutes as resolution
               FROM price_indices
               WHERE country = :country
                 AND ts_start >= DATE(:tomorrow)
               ORDER BY ts_start ASC
           ";

           $stmt = $this->db->prepare($sql);
           $stmt->execute([
               'country' => $country,
               'tomorrow' => $tomorrowDate,
           ]);

           return $stmt->fetchAll(PDO::FETCH_ASSOC);
       }
   }
   ```

2. Write `tests/PriceRepositoryTest.php`:

   ```php
   test('fetches prices for date range', function () {
       $pdo = new PDO('sqlite::memory:');
       $pdo->exec('CREATE TABLE price_indices (
           country TEXT,
           ts_start TEXT,
           ts_end TEXT,
           value REAL,
           resolution_minutes INTEGER
       )');
       $pdo->exec("INSERT INTO price_indices VALUES
           ('LV', '2025-10-04 10:00:00', '2025-10-04 10:15:00', 123.45, 15)
       ");

       $repo = new PriceRepository($pdo);
       $prices = $repo->getPricesForDateRange('2025-10-04', '2025-10-04', 'LV', 15);

       expect($prices)->toHaveCount(1);
       expect($prices[0]['value'])->toBe(123.45);
   });

   test('uses prepared statements to prevent SQL injection', function () {
       $pdo = new PDO('sqlite::memory:');
       $pdo->exec('CREATE TABLE price_indices (
           country TEXT, ts_start TEXT, ts_end TEXT, value REAL, resolution_minutes INTEGER
       )');

       $repo = new PriceRepository($pdo);

       // Should not throw or cause issues with SQL injection attempt
       $prices = $repo->getPricesForDateRange(
           '2025-10-04',
           '2025-10-04',
           "LV' OR '1'='1",
           15
       );

       expect($prices)->toBeArray();
   });
   ```

3. Update `public/index.php`:
   - After `$DB = new PDO(...)` on line 36 (RSS section):
     - Add: `$priceRepo = new PriceRepository($DB);`
     - Replace lines 37-43 SQL with: `$data = $priceRepo->getTomorrowPrices(strtoupper($country), $sql_time_tomorrow);`
   - After `$DB = new PDO(...)` on line 101 (main section):
     - Add: `$priceRepo = new PriceRepository($DB);`
     - Replace lines 103-111 SQL with: `$rows = $priceRepo->getPricesForDateRange($sql_time, $sql_time, $locale->get('code'), $resolution);`
     - Replace `foreach ($DB->query($sql) as $row)` with `foreach ($rows as $row)`

### Verification

- ✅ `./vendor/bin/pest` passes
- ✅ Homepage shows correct prices
- ✅ RSS feed shows correct data: `/?rss`
- ✅ Different countries work: `/lt`, `/ee`
- ✅ Resolution toggle works: `?res=60`
- ✅ No SQL errors in logs

### Rollback Strategy

Revert index.php changes, keep repository class and tests for later.

---

## Step 3: Extract Business Logic (Price Service)

### Goal

Move price calculations and transformations into pure, testable functions.

### Why This Step?

- Currently: Data transformation logic mixed with presentation (lines 123-175)
- This is the core logic that MUST be correct
- Pure functions are easiest to test thoroughly

### Tasks

1. Create `src/PriceService.php`:

   ```php
   <?php
   class PriceService {
       public function transformPrices(
           array $rows,
           DateTimeZone $fromTz,
           DateTimeZone $toTz,
           int $resolution,
           float $vatMultiplier = 1.0
       ): array {
           $prices = [];

           foreach ($rows as $row) {
               try {
                   $start = new DateTime($row['ts_start'], $fromTz);
                   $end = new DateTime($row['ts_end'], $fromTz);
                   $start->setTimeZone($toTz);
                   $end->setTimeZone($toTz);

                   $hour = (int)$start->format('H');
                   $minute = (int)$start->format('i');
                   $quarter = $resolution == 15 ? (int)($minute / 15) : 0;

                   $prices[$start->format('Y-m-d')][$hour][$quarter] =
                       round($vatMultiplier * ((float)$row['value']) / 1000, 4);
               } catch (Exception $e) {
                   continue;
               }
           }

           return $prices;
       }

       public function flattenPrices(array $dayPrices): array {
           $flat = [];
           foreach ($dayPrices as $hour => $quarters) {
               foreach ($quarters as $quarter => $value) {
                   $flat[] = $value;
               }
           }
           return $flat;
       }

       public function calculateAverage(array $values, int $expectedCount): ?float {
           if (count($values) !== $expectedCount) {
               return null;
           }
           return count($values) > 0 ? array_sum($values) / count($values) : null;
       }

       public function getMin(array $values): float {
           return count($values) ? min($values) : 0;
       }

       public function getMax(array $values): float {
           return count($values) ? max($values) : 0;
       }
   }
   ```

2. Write `tests/PriceServiceTest.php`:

   ```php
   test('transforms prices correctly', function () {
       $service = new PriceService();
       $rows = [
           [
               'ts_start' => '2025-10-04 10:00:00',
               'ts_end' => '2025-10-04 10:15:00',
               'value' => 150.0,
           ],
       ];

       $prices = $service->transformPrices(
           $rows,
           new DateTimeZone('Europe/Berlin'),
           new DateTimeZone('Europe/Riga'),
           15,
           1.0
       );

       expect($prices)->toHaveKey('2025-10-04');
       expect($prices['2025-10-04'][11][0])->toBe(0.15); // 150/1000, +1h timezone
   });

   test('applies VAT correctly', function () {
       $service = new PriceService();
       $rows = [
           ['ts_start' => '2025-10-04 10:00:00', 'ts_end' => '2025-10-04 10:15:00', 'value' => 100.0],
       ];

       $prices = $service->transformPrices(
           $rows,
           new DateTimeZone('Europe/Berlin'),
           new DateTimeZone('Europe/Riga'),
           15,
           1.21  // 21% VAT
       );

       expect($prices['2025-10-04'][11][0])->toBe(0.121);
   });

   test('flattens prices correctly', function () {
       $service = new PriceService();
       $dayPrices = [
           0 => [0 => 0.10, 1 => 0.11, 2 => 0.12, 3 => 0.13],
           1 => [0 => 0.20, 1 => 0.21, 2 => 0.22, 3 => 0.23],
       ];

       $flat = $service->flattenPrices($dayPrices);

       expect($flat)->toBe([0.10, 0.11, 0.12, 0.13, 0.20, 0.21, 0.22, 0.23]);
   });

   test('calculates average only with complete data', function () {
       $service = new PriceService();

       expect($service->calculateAverage([1, 2, 3, 4], 4))->toBe(2.5);
       expect($service->calculateAverage([1, 2, 3], 4))->toBeNull();
   });

   test('handles empty arrays safely', function () {
       $service = new PriceService();

       expect($service->flattenPrices([]))->toBe([]);
       expect($service->getMin([]))->toBe(0);
       expect($service->getMax([]))->toBe(0);
   });
   ```

3. Update `public/index.php`:
   - After creating `$priceRepo`: `$priceService = new PriceService();`
   - Replace lines 123-138 (foreach loop) with:
     ```php
     $vatMultiplier = $with_vat ? 1 + $vat : 1;
     $prices = $priceService->transformPrices($rows, $tz_cet, $tz_riga, $resolution, $vatMultiplier);
     ```
   - Replace lines 144-156 (flatten today/tomorrow) with:
     ```php
     $today_flat = $priceService->flattenPrices($today);
     $tomorrow_flat = $priceService->flattenPrices($tomorrow);
     ```
   - Replace lines 159-165 (avg/min/max) with:
     ```php
     $today_avg = $priceService->calculateAverage($today_flat, $expected_count);
     $tomorrow_avg = $priceService->calculateAverage($tomorrow_flat, $expected_count);
     $today_max = $priceService->getMax($today_flat);
     $today_min = $priceService->getMin($today_flat);
     $tomorrow_max = $priceService->getMax($tomorrow_flat);
     $tomorrow_min = $priceService->getMin($tomorrow_flat);
     ```

### Verification

- ✅ `./vendor/bin/pest` passes
- ✅ Prices display correctly
- ✅ Averages calculate correctly
- ✅ Color gradients work (based on min/max)
- ✅ VAT calculations correct: `?vat` vs without
- ✅ 15min vs 1h resolution: `?res=60`

### Rollback Strategy

Keep the service and tests, revert index.php changes.

---

## Step 4: Extract RSS Controller

### Goal

Separate RSS generation into its own controller.

### Why This Step?

- RSS is completely different output format from HTML
- Currently mixed in same file with early return
- Can test RSS generation independently

### Tasks

1. Create `src/RssController.php`:

   ```php
   <?php
   class RssController {
       public function __construct(
           private PriceRepository $repo,
           private DateTimeImmutable $currentTime
       ) {}

       public function generateFeed(
           string $country,
           string $tomorrowDate,
           float $vat,
           DateTimeZone $cetTz,
           DateTimeZone $rigaTz
       ): string {
           $data = $this->repo->getTomorrowPrices(strtoupper($country), $tomorrowDate);

           ob_start();
           ?>
           <feed xmlns="http://www.w3.org/2005/Atom">
               <title type="text">Nordpool spot prices tomorrow (<?=substr($tomorrowDate, 0, 10)?>) for <?=strtoupper($country)?></title>
               <updated><?= $this->currentTime->format('Y-m-d\TH:i:sP') ?></updated>
               <link rel="alternate" type="text/html" href="https://nordpool.didnt.work"/>
               <id>https://nordpool.didnt.work/feed</id>
               <?php foreach ($data as $row) {
                   $ts_start = new DateTime($row['ts_start'], $cetTz);
                   $ts_start->setTimezone($rigaTz);
                   $ts_end = new DateTime($row['ts_end'], $cetTz);
                   $ts_end->setTimezone($rigaTz);
                   $value = (float)$row['value'] / 1000;
                   $resolution = $row['resolution'];
               ?>
               <entry>
                   <id><?=strtoupper($country).'-'.$resolution.'-'.$ts_start->getTimestamp().'-'.$ts_end->getTimestamp()?></id>
                   <ts_start><?= $ts_start->format('Y-m-d\TH:i:sP') ?></ts_start>
                   <ts_end><?= $ts_end->format('Y-m-d\TH:i:sP') ?></ts_end>
                   <resolution><?= $resolution ?></resolution>
                   <price><?= htmlspecialchars($value) ?></price>
                   <price_vat><?= htmlspecialchars($value * (1+$vat)) ?></price_vat>
               </entry>
               <?php } ?>
           </feed>
           <?php
           return ob_get_clean();
       }
   }
   ```

2. Write `tests/RssControllerTest.php`:

   ```php
   test('generates valid RSS feed', function () {
       $pdo = new PDO('sqlite::memory:');
       $pdo->exec('CREATE TABLE price_indices (
           country TEXT, ts_start TEXT, ts_end TEXT, value REAL, resolution_minutes INTEGER
       )');
       $pdo->exec("INSERT INTO price_indices VALUES
           ('LV', '2025-10-05 10:00:00', '2025-10-05 10:15:00', 150.0, 15)
       ");

       $repo = new PriceRepository($pdo);
       $controller = new RssController(
           $repo,
           new DateTimeImmutable('2025-10-04 15:00:00')
       );

       $feed = $controller->generateFeed(
           'LV',
           '2025-10-05 00:00:00',
           0.21,
           new DateTimeZone('Europe/Berlin'),
           new DateTimeZone('Europe/Riga')
       );

       expect($feed)->toContain('<feed xmlns="http://www.w3.org/2005/Atom">');
       expect($feed)->toContain('<title type="text">Nordpool spot prices tomorrow');
       expect($feed)->toContain('<price>0.15</price>');
       expect($feed)->toContain('<price_vat>0.1815</price_vat>'); // 0.15 * 1.21
   });

   test('includes correct timezone conversion', function () {
       $pdo = new PDO('sqlite::memory:');
       $pdo->exec('CREATE TABLE price_indices (
           country TEXT, ts_start TEXT, ts_end TEXT, value REAL, resolution_minutes INTEGER
       )');
       $pdo->exec("INSERT INTO price_indices VALUES
           ('LV', '2025-10-05 10:00:00', '2025-10-05 10:15:00', 100.0, 15)
       ");

       $repo = new PriceRepository($pdo);
       $controller = new RssController(
           $repo,
           new DateTimeImmutable('2025-10-04 15:00:00')
       );

       $feed = $controller->generateFeed(
           'LV',
           '2025-10-05 00:00:00',
           0.21,
           new DateTimeZone('Europe/Berlin'),
           new DateTimeZone('Europe/Riga')
       );

       // Berlin 10:00 = Riga 11:00 (usually)
       expect($feed)->toContain('2025-10-05T11:00:00');
   });
   ```

3. Update `public/index.php`:
   - Replace lines 34-72 (RSS section) with:

     ```php
     if ($request->has('rss')) {
         $DB = new PDO('sqlite:../nordpool.db');
         $priceRepo = new PriceRepository($DB);
         $rssController = new RssController($priceRepo, $current_time);

         header('Content-Type: application/xml; charset=utf-8');
         echo $rssController->generateFeed(
             $country,
             $sql_time_tomorrow,
             $vat,
             $tz_cet,
             $tz_riga
         );
         return;
     }
     ```

### Verification

- ✅ `./vendor/bin/pest` passes
- ✅ RSS feed works: `/?rss`
- ✅ RSS feed for other countries: `/lt?rss`, `/ee?rss`
- ✅ RSS validates at https://validator.w3.org/feed/
- ✅ RSS includes VAT prices
- ✅ Timestamps in correct timezone

### Rollback Strategy

Keep controller and tests, revert index.php RSS section.

---

## Step 5: Extract View Helpers

### Goal

Move presentation formatting functions into dedicated class.

### Why This Step?

- `format()` and `getColorPercentage()` are used heavily in views
- Color calculation has complex logic that needs testing
- Currently uses global variable (bad practice)

### Tasks

1. Create `src/ViewHelper.php`:

   ```php
   <?php
   class ViewHelper {
       private array $percentColors = [
           ['pct' => 0.0, 'color' => ['r' => 0x00, 'g' => 0x88, 'b' => 0x00]],
           ['pct' => 0.5, 'color' => ['r' => 0xaa, 'g' => 0xaa, 'b' => 0x00]],
           ['pct' => 1.0, 'color' => ['r' => 0xaa, 'g' => 0x00, 'b' => 0x00]],
       ];

       public function format(float $number): string {
           $num = number_format($number, 4);
           return substr($num, 0, strpos($num, '.') + 3) .
               '<span class="extra-decimals">' . substr($num, -2) . '</span>';
       }

       public function getColorPercentage(float $value, float $min, float $max): string {
           if ($value === -9999.0) {
               return '#fff';
           }

           $pct = ($max - $min) == 0 ? 0 : ($value - $min) / ($max - $min);

           for ($i = 1; $i < count($this->percentColors) - 1; $i++) {
               if ($pct < $this->percentColors[$i]['pct']) {
                   break;
               }
           }

           $lower = $this->percentColors[$i - 1];
           $upper = $this->percentColors[$i];
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

       public function getLegendColors(): array {
           return $this->percentColors;
       }
   }
   ```

2. Write `tests/ViewHelperTest.php`:

   ```php
   test('formats price with split decimals', function () {
       $helper = new ViewHelper();
       $formatted = $helper->format(0.1234);

       expect($formatted)->toBe('0.12<span class="extra-decimals">34</span>');
   });

   test('formats whole numbers correctly', function () {
       $helper = new ViewHelper();
       $formatted = $helper->format(1.0);

       expect($formatted)->toContain('1.00');
   });

   test('calculates green color for minimum value', function () {
       $helper = new ViewHelper();
       $color = $helper->getColorPercentage(0.10, 0.10, 0.20);

       expect($color)->toBe('rgb(0,136,0)'); // Green
   });

   test('calculates red color for maximum value', function () {
       $helper = new ViewHelper();
       $color = $helper->getColorPercentage(0.20, 0.10, 0.20);

       expect($color)->toBe('rgb(170,0,0)'); // Red
   });

   test('calculates yellow for middle value', function () {
       $helper = new ViewHelper();
       $color = $helper->getColorPercentage(0.15, 0.10, 0.20);

       expect($color)->toBe('rgb(85,119,0)'); // Yellowish
   });

   test('handles equal min and max', function () {
       $helper = new ViewHelper();
       $color = $helper->getColorPercentage(0.10, 0.10, 0.10);

       expect($color)->toBe('rgb(0,136,0)'); // Green (0%)
   });

   test('returns white for sentinel value', function () {
       $helper = new ViewHelper();
       $color = $helper->getColorPercentage(-9999, 0, 1);

       expect($color)->toBe('#fff');
   });
   ```

3. Update `public/functions.php`:
   - Remove `$percentColors` global variable (lines 38-45)
   - Remove `format()` function (lines 30-35)
   - Remove `getColorPercentage()` function (lines 47-75)

4. Update `public/index.php`:
   - After creating services: `$viewHelper = new ViewHelper();`
   - Replace all `format($value)` calls with `$viewHelper->format($value)`
   - Replace all `getColorPercentage(...)` calls with `$viewHelper->getColorPercentage(...)`
   - In CSS section (lines 315-322), replace `$percentColors[0]` and `$percentColors[2]` with:
     ```php
     <?php $legendColors = $viewHelper->getLegendColors(); ?>
     .good {
         background-color: rgb(<?= $legendColors[0]['color']['r'] ?>, <?= $legendColors[0]['color']['g'] ?>, <?= $legendColors[0]['color']['b'] ?>);
         color: #fff;
     }
     .bad {
         background-color: rgb(<?= $legendColors[2]['color']['r'] ?>, <?= $legendColors[2]['color']['g'] ?>, <?= $legendColors[2]['color']['b'] ?>);
         color: #fff;
     }
     ```

### Verification

- ✅ `./vendor/bin/pest` passes
- ✅ Prices format correctly with split decimals
- ✅ Color gradients display correctly
- ✅ Legend shows correct green/red colors
- ✅ No PHP warnings about undefined functions or variables

### Rollback Strategy

Keep ViewHelper and tests, restore functions.php functions and global, revert index.php.

---

## Step 6: Extract Configuration

### Goal

Move static configuration data into dedicated class.

### Why This Step?

- Country config and translations are static data
- Currently functions return arrays (fine, but can be better organized)
- Easier to extend with new countries/languages

### Tasks

1. Create `src/Config.php`:

   ```php
   <?php
   class Config {
       public static function getCountries(?string $country = null): array {
           $countries = [
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
               return $countries;
           }
           return $countries[$country] ?? $countries['LV'];
       }

       public static function getTranslations(): array {
           return [
               'Primitīvs grafiks' => [
                   'LV' => 'Primitīvs grafiks',
                   'LT' => 'Paprastas grafikas',
                   'EE' => 'Lihtne joonis',
               ],
               // ... (copy all translations from functions.php)
           ];
       }
   }
   ```

2. Write `tests/ConfigTest.php`:

   ```php
   test('returns all countries', function () {
       $countries = Config::getCountries();

       expect($countries)->toHaveKeys(['LV', 'LT', 'EE']);
   });

   test('returns specific country config', function () {
       $lv = Config::getCountries('LV');

       expect($lv['code'])->toBe('LV');
       expect($lv['vat'])->toBe(0.21);
   });

   test('returns default country for invalid code', function () {
       $default = Config::getCountries('XX');

       expect($default['code'])->toBe('LV');
   });

   test('returns translations for all supported languages', function () {
       $translations = Config::getTranslations();

       expect($translations)->toHaveKey('Šodien');
       expect($translations['Šodien'])->toHaveKeys(['LV', 'LT', 'EE']);
   });
   ```

3. Update `public/functions.php`:
   - Keep `getCountryConfig()` and `getTranslations()` functions for backwards compatibility
   - Make them call `Config::getCountries()` and `Config::getTranslations()`

   ```php
   function getCountryConfig(?string $country = null): array {
       return Config::getCountries($country);
   }

   function getTranslations(): array {
       return Config::getTranslations();
   }
   ```

### Verification

- ✅ `./vendor/bin/pest` passes
- ✅ All countries work: `/`, `/lt`, `/ee`
- ✅ Translations display correctly
- ✅ VAT rates correct per country

### Rollback Strategy

Keep Config class and tests, keep old functions in place (they're just wrappers).

---

## Step 7: Move Existing Classes to src/

### Goal

Reorganize existing classes (AppLocale, Cache, Lock) into src/ directory.

### Why This Step?

- Currently in functions.php (which is getting too large)
- Need consistent organization before adding autoloader
- These classes are already well-structured

### Tasks

1. Create `src/Locale.php`:
   - Copy `AppLocale` class from functions.php
   - Update constructor to use `Config::getCountries()` and `Config::getTranslations()`

   ```php
   <?php
   class Locale {
       // ... (same as AppLocale, renamed)
       public function __construct(
           public ?array  $config,
           public ?array  $translations,
           public ?string $country = 'LV',
       ) {
           if ($config === null || $this->translations === null) {
               $this->config = Config::getCountries($country);
               $this->translations = Config::getTranslations();
           }
           // ... rest of constructor
       }
   }
   ```

2. Create `src/Cache.php`:
   - Copy `Cache` class from functions.php exactly as-is

3. Create `src/Lock.php`:
   - Copy `Lock` class from functions.php exactly as-is

4. Write `tests/LocaleTest.php`:

   ```php
   test('formats dates according to locale', function () {
       $locale = new Locale(
           Config::getCountries('LV'),
           Config::getTranslations()
       );

       $date = new DateTimeImmutable('2025-10-04 15:30:00');
       $formatted = $locale->formatDate($date, 'd. MMM');

       expect($formatted)->toContain('04');
   });

   test('translates messages', function () {
       $locale = new Locale(
           Config::getCountries('LV'),
           Config::getTranslations()
       );

       expect($locale->msg('Šodien'))->toBe('Šodien');
   });

   test('translates to Lithuanian', function () {
       $locale = new Locale(
           Config::getCountries('LT'),
           Config::getTranslations()
       );

       expect($locale->msg('Šodien'))->toBe('Šiandien');
   });

   test('generates correct routes', function () {
       $lv = new Locale(Config::getCountries('LV'), Config::getTranslations());
       $lt = new Locale(Config::getCountries('LT'), Config::getTranslations());

       expect($lv->route('/?vat'))->toBe('/?vat');
       expect($lt->route('/?vat'))->toBe('/lt/?vat');
   });
   ```

5. Write `tests/CacheTest.php`:

   ```php
   beforeEach(function () {
       Cache::clear();
   });

   test('stores and retrieves values', function () {
       Cache::set('test', 'value');
       expect(Cache::get('test'))->toBe('value');
   });

   test('returns default for missing keys', function () {
       expect(Cache::get('missing', 'default'))->toBe('default');
   });

   test('clears all cache', function () {
       Cache::set('key1', 'value1');
       Cache::set('key2', 'value2');
       Cache::clear();

       expect(Cache::get('key1'))->toBeNull();
       expect(Cache::get('key2'))->toBeNull();
   });
   ```

6. Update `public/functions.php`:
   - Keep `AppLocale` as alias: `class_alias(Locale::class, 'AppLocale');`
   - Remove original class definitions (they'll be autoloaded)

7. Update `public/index.php`:
   - Replace `new AppLocale(...)` with `new Locale(...)` (or keep AppLocale via alias)

### Verification

- ✅ `./vendor/bin/pest` passes
- ✅ Translations work correctly
- ✅ Cache works (check with `?purge`)
- ✅ All countries display correctly

### Rollback Strategy

Keep new files, restore class definitions in functions.php.

---

## Step 8: Add Simple Autoloader

### Goal

Eliminate manual require statements with PSR-4 style autoloader.

### Why This Step?

- Currently: Need to manually include each new class
- Autoloader makes it seamless
- Standard practice, no magic

### Tasks

1. Update `public/functions.php` at the top:

   ```php
   <?php

   // Simple PSR-4 autoloader
   spl_autoload_register(function ($class) {
       $file = __DIR__ . '/../src/' . $class . '.php';
       if (file_exists($file)) {
           require_once $file;
       }
   });

   // CLI server static file handling
   if (php_sapi_name() == 'cli-server') {
       $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
       if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
           return false;
       }
   }

   // Backwards compatibility aliases
   class_alias(Locale::class, 'AppLocale');

   // Keep utility functions
   function dd(...$vars): void { /* ... */ }
   function abort(int $code = 500, string $message = ''): void { /* ... */ }

   // Backwards compatibility wrappers
   function getCountryConfig(?string $country = null): array {
       return Config::getCountries($country);
   }

   function getTranslations(): array {
       return Config::getTranslations();
   }
   ```

2. Update `public/index.php`:
   - Remove any manual `require` statements for src/ classes
   - Keep `require 'functions.php'` at the top

3. Write `tests/AutoloaderTest.php`:

   ```php
   test('autoloads classes from src directory', function () {
       // Autoloader is already registered via functions.php
       // These should work without explicit requires

       expect(class_exists('Request'))->toBeTrue();
       expect(class_exists('PriceRepository'))->toBeTrue();
       expect(class_exists('PriceService'))->toBeTrue();
       expect(class_exists('ViewHelper'))->toBeTrue();
       expect(class_exists('Config'))->toBeTrue();
   });
   ```

### Verification

- ✅ `./vendor/bin/pest` passes
- ✅ Application works without errors
- ✅ No "Class not found" warnings
- ✅ All pages load correctly

### Rollback Strategy

Restore manual require statements if autoloader causes issues.

---

## Step 9: Refactor Table Rendering (Eliminate Duplication)

### Goal

Build ONE table structure, use CSS for responsive layout.

### Why This Step?

- Currently: Desktop table + 2 mobile tables = massive duplication
- Same data rendered 3 times with slightly different markup
- Hard to maintain, easy to introduce bugs

### Tasks

1. Create `src/TableBuilder.php`:

   ```php
   <?php
   class TableBuilder {
       public function __construct(private ViewHelper $viewHelper) {}

       public function buildTableData(
           array $today,
           array $tomorrow,
           float $today_min,
           float $today_max,
           float $tomorrow_min,
           float $tomorrow_max,
           int $quartersPerHour
       ): array {
           $rows = [];

           for ($hour = 0; $hour < 24; $hour++) {
               $hourLabel = sprintf('%02d-%02d', $hour, ($hour + 1) % 24);

               $todayCells = $this->buildCells(
                   $today[$hour] ?? [],
                   $today_min,
                   $today_max,
                   $quartersPerHour,
                   'today'
               );

               $tomorrowCells = $this->buildCells(
                   $tomorrow[$hour] ?? [],
                   $tomorrow_min,
                   $tomorrow_max,
                   $quartersPerHour,
                   'tomorrow'
               );

               $rows[] = [
                   'hour' => $hourLabel,
                   'hour_number' => $hour,
                   'today' => $todayCells,
                   'tomorrow' => $tomorrowCells,
               ];
           }

           return $rows;
       }

       private function buildCells(
           array $quarters,
           float $min,
           float $max,
           int $quartersPerHour,
           string $day
       ): array {
           $cells = [];
           $q = 0;

           while ($q < $quartersPerHour) {
               $value = $quarters[$q] ?? null;

               // Count consecutive quarters with same value (for colspan)
               $colspan = 1;
               $next_q = $q + 1;
               while ($next_q < $quartersPerHour) {
                   $next_value = $quarters[$next_q] ?? null;
                   if ($value !== null && $next_value !== null && $value === $next_value) {
                       $colspan++;
                       $next_q++;
                   } else {
                       break;
                   }
               }

               $cells[] = [
                   'value' => $value,
                   'formatted' => isset($value) ? $this->viewHelper->format($value) : '-',
                   'color' => $this->viewHelper->getColorPercentage($value ?? -9999, $min, $max),
                   'colspan' => $colspan,
                   'quarter' => $q,
                   'day' => $day,
               ];

               $q = $next_q;
           }

           return $cells;
       }
   }
   ```

2. Create unified table view in index.php (replace lines 488-735):

   ```php
   <?php
   $tableBuilder = new TableBuilder($viewHelper);
   $tableRows = $tableBuilder->buildTableData(
       $today,
       $tomorrow,
       $today_min,
       $today_max,
       $tomorrow_min,
       $tomorrow_max,
       $quarters_per_hour
   );
   ?>

   <table id="price-table">
       <thead>
           <tr>
               <th></th>
               <th colspan="<?=$quarters_per_hour?>" class="today-header">
                   <?=$locale->msg('Šodien')?>
                   <span class="help"><?=$locale->formatDate($current_time, 'd. MMM')?></span><br/>
                   <small><?=$locale->msg('Vidēji')?> <span><?=$today_avg ? $viewHelper->format($today_avg) : '—'?></span></small>
               </th>
               <th colspan="<?=$quarters_per_hour?>" class="tomorrow-header">
                   <?=$locale->msg('Rīt')?>
                   <span class="help"><?=$locale->formatDate($current_time->modify('+1 day'), 'd. MMM')?></span><br/>
                   <small><?=$locale->msg('Vidēji')?> <span><?=$tomorrow_avg ? $viewHelper->format($tomorrow_avg) : '—'?></span></small>
               </th>
           </tr>
           <tr>
               <th>🕑</th>
               <?php if ($resolution == 15) { ?>
                   <?php for ($i = 0; $i < 2; $i++) { ?>
                       <th>:00</th><th>:15</th><th>:30</th><th>:45</th>
                   <?php } ?>
               <?php } else { ?>
                   <th>:00</th><th>:00</th>
               <?php } ?>
           </tr>
       </thead>
       <tbody>
           <?php foreach ($tableRows as $row): ?>
               <tr data-hours="<?=$row['hour_number']?>">
                   <th><?=$row['hour']?></th>
                   <?php foreach ($row['today'] as $cell): ?>
                       <td class="price today quarter-<?=$cell['quarter']?>"
                           data-quarter="<?=$cell['quarter']?>"
                           <?php if ($cell['colspan'] > 1) { ?>colspan="<?=$cell['colspan']?>"<?php } ?>
                           style="background-color: <?=$cell['color']?>">
                           <?=$cell['formatted']?>
                       </td>
                   <?php endforeach; ?>
                   <?php foreach ($row['tomorrow'] as $cell): ?>
                       <td class="price tomorrow quarter-<?=$cell['quarter']?>"
                           data-quarter="<?=$cell['quarter']?>"
                           <?php if ($cell['colspan'] > 1) { ?>colspan="<?=$cell['colspan']?>"<?php } ?>
                           style="<?=isset($cell['value']) ? '' : 'text-align: center; '?>background-color: <?=$cell['color']?>">
                           <?=$cell['formatted']?>
                       </td>
                   <?php endforeach; ?>
               </tr>
           <?php endforeach; ?>
       </tbody>
   </table>

   <div id="mobile-day-toggle">
       <button data-day="today" class="active"><?=$locale->msg('Šodien')?></button>
       <button data-day="tomorrow"><?=$locale->msg('Rīt')?></button>
   </div>
   ```

3. Update CSS (replace desktop-table/mobile-tables styles):

   ```css
   #price-table {
     table-layout: fixed;
     width: 100%;
     border-collapse: collapse;
   }

   #mobile-day-toggle {
     display: none;
     margin: 1em 0;
     text-align: center;
   }

   /* Desktop: show both columns */
   @media (min-width: 769px) {
     #price-table td.today,
     #price-table td.tomorrow,
     #price-table th.today-header,
     #price-table th.tomorrow-header {
       display: table-cell;
     }
     #mobile-day-toggle {
       display: none;
     }
   }

   /* Mobile: toggle between days */
   @media (max-width: 768px) {
     body:not(.res-60) #mobile-day-toggle {
       display: block;
     }

     /* Default: show today */
     body:not(.res-60):not(.show-tomorrow) #price-table td.tomorrow,
     body:not(.res-60):not(.show-tomorrow) #price-table th.tomorrow-header {
       display: none;
     }

     /* When toggled: show tomorrow */
     body:not(.res-60).show-tomorrow #price-table td.today,
     body:not(.res-60).show-tomorrow #price-table th.today-header {
       display: none;
     }

     /* 60min resolution: show both on mobile too */
     body.res-60 #mobile-day-toggle {
       display: none;
     }
   }
   ```

4. Update JavaScript for mobile toggle:

   ```javascript
   document.querySelectorAll("#mobile-day-toggle button").forEach((btn) => {
     btn.addEventListener("click", (e) => {
       const day = e.target.dataset.day;
       document.body.classList.toggle("show-tomorrow", day === "tomorrow");
       document.querySelectorAll("#mobile-day-toggle button").forEach((b) => {
         b.classList.toggle("active", b === e.target);
       });
     });
   });
   ```

5. Write `tests/TableBuilderTest.php`:

   ```php
   test('builds table data structure', function () {
       $viewHelper = new ViewHelper();
       $builder = new TableBuilder($viewHelper);

       $today = [0 => [0 => 0.10, 1 => 0.11, 2 => 0.12, 3 => 0.13]];
       $tomorrow = [0 => [0 => 0.20, 1 => 0.20, 2 => 0.21, 3 => 0.22]];

       $rows = $builder->buildTableData($today, $tomorrow, 0.10, 0.13, 0.20, 0.22, 4);

       expect($rows)->toHaveCount(24);
       expect($rows[0]['hour'])->toBe('00-01');
       expect($rows[0]['today'])->toHaveCount(4);
       expect($rows[0]['tomorrow'][0]['colspan'])->toBe(2); // 0.20 repeats
   });

   test('handles missing data gracefully', function () {
       $viewHelper = new ViewHelper();
       $builder = new TableBuilder($viewHelper);

       $rows = $builder->buildTableData([], [], 0, 0, 0, 0, 4);

       expect($rows)->toHaveCount(24);
       expect($rows[0]['today'][0]['formatted'])->toBe('-');
   });

   test('calculates colspan correctly', function () {
       $viewHelper = new ViewHelper();
       $builder = new TableBuilder($viewHelper);

       $today = [0 => [0 => 0.10, 1 => 0.10, 2 => 0.10, 3 => 0.15]];

       $rows = $builder->buildTableData($today, [], 0.10, 0.15, 0, 0, 4);

       expect($rows[0]['today'])->toHaveCount(2);
       expect($rows[0]['today'][0]['colspan'])->toBe(3); // First 3 quarters same
       expect($rows[0]['today'][1]['colspan'])->toBe(1); // Last quarter different
   });
   ```

### Verification

- ✅ `./vendor/bin/pest` passes
- ✅ Desktop: both columns visible side-by-side
- ✅ Mobile: toggle between today/tomorrow works
- ✅ 60min resolution: shows both on mobile
- ✅ Colors, formatting, colspan all correct
- ✅ Current hour/quarter highlighting works
- ✅ Chart toggle buttons update chart

### Rollback Strategy

Keep TableBuilder, restore old table HTML if CSS/JS issues occur.

---

## Step 10: Final Cleanup - Slim Down index.php

### Goal

Reduce index.php to just wiring and flow control (~100 lines).

### Why This Step?

- All logic now extracted and tested
- index.php should just: bootstrap → route → render
- Final file will be maintainable and clear

### Tasks

1. Review `public/index.php` and organize into clear sections:

   ```php
   <?php
   // 1. Bootstrap
   $ret = require 'functions.php';
   if (!$ret) return false;

   // 2. Setup
   $request = new Request();
   $countryConfig = Config::getCountries();
   $translations = Config::getTranslations();

   // 3. Timezone & Time
   $tz_riga = new DateTimeZone('Europe/Riga');
   $tz_cet = new DateTimeZone('Europe/Berlin');
   $current_time = new DateTimeImmutable($request->get('now', 'now'), $tz_riga);
   $current_time_cet = $current_time->setTimezone($tz_cet);

   // 4. Route Parsing
   $parts = explode('/', $request->path());
   $country = strtoupper($parts[0] ?? 'lv');
   if (!isset($countryConfig[$country])) $country = 'LV';

   $locale = new Locale($countryConfig[$country], $translations);
   $vat = $locale->get('vat');
   $resolution = $request->get('res') == '60' ? 60 : 15;

   // 5. RSS Route
   if ($request->has('rss')) {
       $DB = new PDO('sqlite:../nordpool.db');
       $priceRepo = new PriceRepository($DB);
       $rssController = new RssController($priceRepo, $current_time);

       $sql_time_tomorrow = (new DateTimeImmutable(date('Y-m-d'), $tz_riga))
           ->modify('+1 day')
           ->setTimeZone($tz_cet)
           ->format('Y-m-d H:00:00');

       header('Content-Type: application/xml; charset=utf-8');
       echo $rssController->generateFeed($country, $sql_time_tomorrow, $vat, $tz_cet, $tz_riga);
       return;
   }

   // 6. Cache Management
   $mtime = stat('../nordpool.db')['mtime'] ?? 0;
   $cmtime = Cache::get('last_db_mtime', 0);
   if ($cmtime === 0 || $mtime === 0 || (int)$mtime !== (int)$cmtime || $request->has('purge')) {
       Cache::clear();
       Cache::set('last_db_mtime', $mtime);
   }

   // 7. Setup Services
   $viewHelper = new ViewHelper();
   $priceService = new PriceService();
   $tableBuilder = new TableBuilder($viewHelper);

   // 8. Check Cache
   $with_vat = $request->has('vat');
   $cache_key = 'prices_' . $locale->get('code') . '_' . $current_time->format('Ymd_Hi')
       . '_' . ($with_vat ? 'vat' : 'novat') . '_' . $resolution;

   if (!ob_start('ob_gzhandler')) ob_start();

   $html = Cache::get($cache_key);
   if ($html) {
       header('X-Cache: HIT');
       echo $html;
       exit;
   }

   // 9. Fetch & Process Data
   $DB = new PDO('sqlite:../nordpool.db');
   $priceRepo = new PriceRepository($DB);
   $sql_time = $current_time_cet->format('Y-m-d H:i:s');

   $rows = $priceRepo->getPricesForDateRange($sql_time, $sql_time, $locale->get('code'), $resolution);

   $vatMultiplier = $with_vat ? 1 + $vat : 1;
   $prices = $priceService->transformPrices($rows, $tz_cet, $tz_riga, $resolution, $vatMultiplier);

   $today = $prices[$current_time->format('Y-m-d')] ?? [];
   $tomorrow = $prices[$current_time->modify('+1 day')->format('Y-m-d')] ?? [];

   $quarters_per_hour = $resolution == 15 ? 4 : 1;
   $expected_count = 24 * $quarters_per_hour;

   $today_flat = $priceService->flattenPrices($today);
   $tomorrow_flat = $priceService->flattenPrices($tomorrow);

   $today_avg = $priceService->calculateAverage($today_flat, $expected_count);
   $tomorrow_avg = $priceService->calculateAverage($tomorrow_flat, $expected_count);
   $today_max = $priceService->getMax($today_flat);
   $today_min = $priceService->getMin($today_flat);
   $tomorrow_max = $priceService->getMax($tomorrow_flat);
   $tomorrow_min = $priceService->getMin($tomorrow_flat);

   $tableRows = $tableBuilder->buildTableData(
       $today, $tomorrow,
       $today_min, $today_max,
       $tomorrow_min, $tomorrow_max,
       $quarters_per_hour
   );

   // 10. Prepare Chart Data
   $legend = [];
   $values = ['today' => [], 'tomorrow' => []];
   for ($hour = 0; $hour < 24; $hour++) {
       for ($q = 0; $q < $quarters_per_hour; $q++) {
           $legend[] = sprintf('%02d:%02d', $hour, $resolution == 15 ? $q * 15 : 0);
           $values['today'][] = $today[$hour][$q] ?? 0;
           $values['tomorrow'][] = $tomorrow[$hour][$q] ?? 0;
       }
   }
   $legend[] = '00:00';
   $values['today'][] = $tomorrow[0][0] ?? 0;

   ksort($today);
   ksort($tomorrow);

   // 11. Render View
   include __DIR__ . '/view.php';

   // 12. Cache Output
   $html = ob_get_clean();
   echo $html;
   Cache::set($cache_key, $html);
   ```

2. Create `public/view.php` - extract all HTML:
   - Move entire HTML section (<!doctype> through </html>)
   - Keep it as-is, just in separate file
   - All variables already prepared in index.php

3. Update `public/functions.php`:
   - Keep only: autoloader, backwards compatibility aliases, utility functions
   - Should be ~40 lines max

4. Final file structure review:

   ```
   src/
     Request.php          ✓ HTTP abstraction
     PriceRepository.php  ✓ Database queries
     PriceService.php     ✓ Business logic
     RssController.php    ✓ RSS generation
     TableBuilder.php     ✓ Table data preparation
     ViewHelper.php       ✓ Formatting
     Config.php           ✓ Configuration
     Locale.php           ✓ Localization
     Cache.php            ✓ Caching
     Lock.php             ✓ Locking

   public/
     functions.php        ~40 lines (bootstrap)
     index.php            ~100 lines (flow control)
     view.php             HTML template

   tests/
     [All test files]     ✓ Comprehensive coverage
   ```

### Verification

- ✅ `./vendor/bin/pest` passes all tests
- ✅ Homepage works perfectly: http://localhost:8000/
- ✅ All countries: `/lt`, `/ee`
- ✅ All params: `?vat`, `?res=60`, `?rss`, `?purge`
- ✅ Cache works (X-Cache: HIT header)
- ✅ Visual output identical to before
- ✅ RSS feed validates
- ✅ No performance degradation
- ✅ Code is clean, maintainable, testable

### Rollback Strategy

If anything breaks, we have git history and tests to guide us back.

---

## Final Verification Checklist

### Functional Testing

- [ ] Homepage loads correctly
- [ ] LV, LT, EE country pages work
- [ ] VAT toggle works (`?vat`)
- [ ] Resolution toggle works (`?res=60`)
- [ ] RSS feed works and validates
- [ ] Cache purge works (`?purge`)
- [ ] Mobile responsive tables work
- [ ] Chart displays and toggles correctly
- [ ] Current hour highlighting works
- [ ] Color gradients display correctly
- [ ] All translations correct

### Technical Verification

- [ ] `./vendor/bin/pest` - all tests pass
- [ ] No PHP warnings or errors
- [ ] No JavaScript console errors
- [ ] Cache headers working (X-Cache: HIT)
- [ ] Page load time same or better
- [ ] Database queries optimized (prepared statements)
- [ ] No SQL injection vulnerabilities
- [ ] No XSS vulnerabilities (htmlspecialchars used)

### Code Quality

- [ ] index.php under 120 lines
- [ ] No duplicate code
- [ ] All functions under 50 lines
- [ ] Classes have single responsibility
- [ ] No global variables (except necessary $_GET/$\_SERVER via Request)
- [ ] All business logic tested
- [ ] All edge cases covered in tests

### Backwards Compatibility

- [ ] All URLs work identically
- [ ] Visual output unchanged
- [ ] RSS feed format unchanged
- [ ] Cache behavior unchanged
- [ ] Performance same or better

---

## Post-Refactoring: Next Steps

### Optional Improvements (Not Part of This Plan)

1. Add database migration system
2. Add API endpoints for price data
3. Add more countries
4. Add CSV export functionality improvements
5. Add service worker for offline support
6. Add price alerts feature
7. Add historical price comparisons

### Maintenance

- Run `./vendor/bin/pest` before any changes
- Add tests for new features
- Keep index.php slim - extract to services
- Update tests when requirements change

---

## Success Criteria

✅ **You can show this code to anyone with pride**

- Clean separation of concerns
- Well-tested business logic
- No security vulnerabilities
- Maintainable and extensible
- No visual or functional regressions
- Professional code organization

The refactoring is complete when:

1. All tests pass
2. All functionality works
3. Code is <500 lines total in src/
4. index.php is ~100 lines
5. Every class is independently testable
6. You'd be happy to hand this off to another developer
