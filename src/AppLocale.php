<?php

class AppLocale
{
    private IntlDateFormatter $dateFormatter;

    public function __construct(
        public ?array $config,
        public ?array $translations,
        public ?string $country = 'LV',
    ) {
        if ($config === null || $this->translations === null) {
            $this->config = Config::getCountries($country);
            $this->translations = Config::getTranslations();
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

    /**
     * Format a date/time string according to the specified format and locale in the $country['locale'].
     * Format string is based on ICU DateTime format.
     */
    public function formatDate(mixed $time, string $format = 'd.m.Y H:i'): string|false
    {
        if (!$this->dateFormatter->setPattern($format)) {
            return false;
        }

        return $this->dateFormatter->format($time);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    public function msg(string $msg): string
    {
        return $this->translations[$msg][$this->config['code']] ?? $msg;
    }

    public function msgf(string $msg, ...$args): string
    {
        return vsprintf($this->msg($msg), $args);
    }

    public function route(string $route): string
    {
        $lang = match ($this->config['code']) {
            'LV' => '',
            default => $this->config['code_lc'] . '/',
        };

        return '/' . $lang . ltrim($route, '/');
    }
}
