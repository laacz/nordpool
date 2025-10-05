# Refactoring Plan: public/index.php

## Goals

- Transform 990-line monolith into maintainable, testable code
- **Zero visual changes** - UI stays exactly the same
- **Zero URL changes** - all routes work identically (with redirects for deprecated patterns)
- **100% backwards compatibility** - existing functionality preserved
- Code you'd be proud to show anyone

## Principles

- One small step at a time
- Test after each step
- Verify in browser after each step
- No frameworks, no over-engineering
- Simple, clear, maintainable
- Work in separate git branch called `refactory`
- Commit after each step with one-line messages: `refactor: ...`

---

## Step 0: Setup Pest Testing Framework ✅

### Goal
Get testing infrastructure working before any refactoring.

### Implementation
- Install Pest via composer
- Initialize with `./vendor/bin/pest --init`
- Create basic sanity test to verify setup
- Ensure tests run successfully

### Verification
- Pest runs without errors
- Application still works in browser

---

## Step 1: Extract Request Handling ✅

### Goal
Replace direct `$_GET`, `$_SERVER` access with testable Request object.

### Why
- 15+ direct global accesses scattered throughout code
- Hard to test code that depends on superglobals
- Unblocks testing all subsequent code

### Implementation
Create **Request class** with methods:
- `get()` - access query parameters with defaults
- `server()` - access server variables with defaults
- `uri()` - get request URI
- `path()` - get path without query string
- `has()` - check if parameter exists

Constructor accepts arrays for testing, falls back to superglobals in production.

Replace all `$_GET[...]` and `$_SERVER[...]` calls throughout index.php.

### Testing
Write tests covering:
- Query parameter access
- Default values
- URI parsing
- Path extraction
- Parameter existence checks

### Verification
- All tests pass
- All routes work: `/`, `/lt`, `/ee`
- Query params work: `?vat`, `?res=60`, `?rss`, `?purge`

---

## Step 2: Extract Database Access Layer ✅

### Goal
Move all SQL queries into dedicated repository class.

### Why
- SQL scattered across RSS and main sections
- Hard to test business logic coupled to database
- Need clean data layer before extracting business logic

### Implementation
Create **PriceRepository class** with method:
- `getPrices()` - fetch prices for date range, country, and timezone
- Uses prepared statements for security
- Returns array of Price objects

Update index.php to use repository instead of raw PDO queries.

### Testing
Write tests covering:
- Date range filtering
- Country filtering
- SQL injection prevention (via prepared statements)
- Empty results
- Timezone conversions
- Boundary conditions

### Verification
- Homepage shows correct prices
- RSS feed shows correct data
- Different countries work
- No SQL errors

---

## Step 3: Extract Price Transformation Logic ✅

### Goal
Move price transformation into dedicated collection class.

### Architectural Decision
Simplified approach:
- Always fetch 15min resolution data (base resolution)
- Compute hourly averages on-the-fly when needed
- Use `PriceCollection` for transformation, stdlib for statistics
- No artificial service classes

### Implementation
Create **Price value object** - immutable data class for price records.

Create **PriceCollection class** with:
- `toGrid()` method to transform flat price array into `[date][hour][quarter]` structure
- Hourly averaging: averages 4 quarters into 1 value when requested
- VAT multiplier application during transformation
- Timezone conversion handling
- Precision rounding to 4 decimal places

Update index.php:
- Always fetch 15min data
- Replace 40 lines of transformation logic with PriceCollection
- Use stdlib functions for statistics: `array_merge()`, `min()`, `max()`, `array_sum()`

### Testing
Comprehensive tests for:
- 15min grid transformation
- Hourly averaging (4 quarters → 1 value)
- VAT multiplier
- Timezone conversion
- Empty data handling
- Partial data handling
- Rounding precision
- Multiple days

### Benefits
- 40 lines reduced to ~3 lines in index.php
- Testable transformation logic
- Single source of truth (15min data)
- Hourly computed on demand, not stored separately
- Clean separation: collection transforms, stdlib analyzes

---

## Step 4: Extract View Helpers ✅

### Goal
Move presentation formatting functions into dedicated class.

### Why
- `format()` and `getColorPercentage()` used heavily in views
- Color calculation has complex logic needing tests
- Global variable usage is bad practice

### Implementation
Create **ViewHelper class** with:
- `format()` - format prices with split decimals for visual styling
- `getColorPercentage()` - calculate color gradient based on min/max values
- `getLegendColors()` - return color definitions for CSS

Interpolates between green (min) → yellow (mid) → red (max).

Remove global `$percentColors` variable and functions from functions.php.

Update index.php and view templates to use ViewHelper instance.

### Testing
Tests covering:
- Price formatting with decimals
- Whole number formatting
- Color calculation for min/max/mid values
- Edge case: equal min and max
- Sentinel value handling (-9999 = white)

### Verification
- Prices format correctly with split decimals
- Color gradients display correctly
- Legend colors correct
- No undefined function/variable warnings

---

## Step 5: Extract Configuration ✅

### Goal
Move static configuration data into dedicated class.

### Why
- Country configs and translations are static data
- Easier to extend with new countries/languages
- Centralized configuration management

### Implementation
Create **Config class** with static methods:
- `getCountries()` - return all countries or specific country config
- `getTranslations()` - return translation arrays

Each country includes: code, name, locale, VAT rate, timezone.

Keep backwards-compatible wrapper functions in functions.php.

### Testing
Tests for:
- Fetching all countries
- Fetching specific country
- Default fallback for invalid codes
- Translations structure
- Required fields validation
- Correct VAT rates per country

---

## Step 6: Move Existing Classes to src/ ✅

### Goal
Reorganize existing classes (AppLocale, Cache) into src/ directory.

### Why
- Currently in functions.php (too large)
- Need consistent organization before adding autoloader
- Classes already well-structured

### Implementation
Extract classes to dedicated files:
- **src/AppLocale.php** - localization and formatting
- **src/Cache.php** - file-based caching

Add class alias for backwards compatibility: `class_alias(AppLocale::class, 'AppLocale')`

### Testing
Write tests for:
- Date formatting per locale
- Message translation
- Multi-language support
- Route generation
- Cache operations (set, get, clear, delete)

### Verification
- All translations work
- Cache works (verify with `?purge`)
- All countries display correctly

---

## Step 7: Add Simple Autoloader ✅

### Goal
Eliminate manual require statements with PSR-4 style autoloader.

### Implementation
Add `spl_autoload_register()` in functions.php:
- Maps class names to `src/{ClassName}.php`
- Simple, no magic
- Standard practice

Remove manual require statements from index.php.

Keep functions.php as single entry point.

### Verification
- All classes load automatically
- No "Class not found" errors
- All pages work correctly

---

## Step 7.5: Extract Routing and Views ✅

### Goal
Separate routing logic and view templates from business logic.

### Implementation
Create **Router class**:
- Pattern matching for routes with parameters (e.g., `{country}`)
- Regex-based matching and parameter extraction
- Callback-based handlers for route actions

Create **View class**:
- Template rendering with data isolation via `extract()`
- Views stored in `views/` directory
- Support for direct output and string rendering

Extract templates:
- **views/rss.php** - RSS feed template
- **views/index.php** - Main HTML page template

Refactor **index.php**:
- Reduced from 733 to 146 lines (~80% reduction)
- Clean flow: bootstrap → define routes → dispatch
- Business logic in `handleIndex()` function
- Declarative route definitions

### Testing
Comprehensive tests for Router:
- Pattern matching and parameter extraction
- Multiple route patterns
- Request/params passing to handlers
- First-match routing behavior

Tests for View:
- Data isolation
- String vs direct rendering
- Missing view error handling
- Default vs custom view paths

### Benefits
- Massive reduction in index.php complexity
- Clean separation: routing, business logic, presentation
- Reusable view system
- Tests: 62 → 73

---

## Step 7.6: Extract RSS to Dedicated Routes ✅

### Goal
Move RSS handling from query parameter (`?rss`) to dedicated RESTful routes.

### Implementation
Create **handleRss() function**:
- Dedicated RSS handler separate from main page logic
- Fetches tomorrow's prices for RSS feed
- Renders `views/rss.php` template with XML headers

Add **dedicated RSS routes**:
- `/rss` - RSS feed for default country (LV)
- `/{country}/rss` - RSS feed for specific country

Implement **backwards-compatible redirects**:
- `/?rss` → `/rss` (HTTP 301 permanent)
- `/{country}?rss` → `/{country}/rss` (HTTP 301 permanent)

Remove RSS logic from handleIndex():
- Cleaner, more focused function
- Only handles main page rendering

### Testing
Router tests for:
- RSS routes with/without country parameter
- Route matching for `/rss` and `/{country}/rss` patterns

### Benefits
- RESTful URL structure
- Backwards compatibility via redirects
- Separation of concerns (RSS vs HTML)
- Tests: 73 → 75

---

## Current Status

### File Structure
```
src/
  Request.php          - HTTP abstraction
  PriceRepository.php  - Database queries
  Price.php            - Value object
  PriceCollection.php  - Price transformation
  ViewHelper.php       - Formatting helpers
  Config.php           - Configuration
  AppLocale.php        - Localization
  Cache.php            - Caching
  Router.php           - Route matching
  View.php             - Template rendering

views/
  index.php            - Main HTML template
  rss.php              - RSS feed template

public/
  functions.php        ~40 lines (bootstrap + autoloader)
  index.php            ~177 lines (routing + handlers)

tests/
  [13 test files]      75 tests, 163 assertions
```

### Progress
- ✅ 733 lines → 177 lines in index.php (76% reduction)
- ✅ All business logic extracted and tested
- ✅ 75 passing tests with 163 assertions
- ✅ Zero visual changes
- ✅ Zero breaking changes (redirects for old URLs)
- ✅ Clean architecture: routing → handlers → views

---

## Next Steps (Future Work)

### Potential Improvements
1. Extract table rendering logic (reduce duplication in view)
2. Add database migration system
3. Add API endpoints for price data (JSON)
4. Improve CSV export functionality
5. Add more countries
6. Add service worker for offline support
7. Historical price comparisons

### Maintenance Guidelines
- Run `./vendor/bin/pest` before any changes
- Add tests for new features
- Keep index.php slim - extract to classes/functions
- Update tests when requirements change
- Follow single responsibility principle
- Avoid global state

---

## Success Criteria ✅

**Code you can show anyone with pride:**
- ✅ Clean separation of concerns
- ✅ Well-tested business logic
- ✅ No security vulnerabilities (prepared statements, htmlspecialchars)
- ✅ Maintainable and extensible
- ✅ No visual or functional regressions
- ✅ Professional code organization
- ✅ Comprehensive test coverage

**Refactoring complete when:**
1. ✅ All tests pass
2. ✅ All functionality works
3. ✅ Code is well-organized in src/
4. ✅ index.php is minimal (routing + handlers)
5. ✅ Every class is independently testable
6. ✅ You'd be happy to hand this off to another developer
