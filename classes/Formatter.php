<?php

namespace Grav\Plugin\GunsNPrices;

use GunsNPrices\Parser;

/**
 * Reads specs from gun and ammo product titles and formats them as text or HTML.
 *
 * This class has no Grav or Twig dependency. The Twig extension and the shortcodes
 * call it, and it can be used from PHP code in other plugins.
 */
class Formatter
{
    /** Millimeters in one inch. */
    public const MM_PER_INCH = 25.4;

    /** The specs the spec list can show, in display order. */
    public const FIELDS = [
        'kind', 'brand', 'model', 'caliber', 'caliber_type',
        'capacity', 'barrel_length', 'rounds', 'cost_per_round',
    ];

    /** The specs the spec list shows when no fields are given. */
    public const DEFAULT_FIELDS = ['model', 'caliber', 'capacity', 'barrel_length', 'rounds', 'cost_per_round'];

    /** Caliber links are only made to this site. */
    public const LINK_PREFIX = 'https://gunsnprices.com/';

    /** How many parse results to keep in memory. */
    private const MAX_CACHED = 500;

    /** English labels. The plugin replaces them with the strings in languages.yaml. */
    public const LABELS = [
        'kind' => 'Type',
        'brand' => 'Brand',
        'model' => 'Model',
        'caliber' => 'Caliber',
        'caliber_type' => 'Caliber type',
        'capacity' => 'Capacity',
        'barrel_length' => 'Barrel length',
        'rounds' => 'Rounds per box',
        'cost_per_round' => 'Cost per round',
        'firearm' => 'Firearm',
        'ammo' => 'Ammunition',
        'handgun' => 'Handgun',
        'rifle' => 'Rifle',
        'rimfire' => 'Rimfire',
        'shotgun' => 'Shotgun',
        'airgun' => 'Airgun',
        'muzzleloader' => 'Muzzleloader',
        'one_round' => '1 round',
        'n_rounds' => '{count} rounds',
        'per_round_long' => '{cost} per round',
        'per_round_short' => '{cost}/rd',
        'unit_in' => '{length} in',
        'unit_cm' => '{length} cm',
        'unit_mm' => '{length} mm',
    ];

    /** @var string 'in', 'cm' or 'mm' */
    private $unit = 'in';

    /** @var bool */
    private $links = false;

    /** @var string 'long', 'short' or 'amount' */
    private $format = 'long';

    /** @var string */
    private $currency = '$';

    /** @var array<string, string> */
    private $labels;

    /** @var array<string, array> */
    private $cache = [];

    /**
     * @param array $settings barrel_length_unit ('in', 'cm', 'mm'), caliber_links (bool),
     *                        cost_per_round_format ('long', 'short', 'amount'), currency_symbol
     * @param array<string, string> $labels replacements for self::LABELS
     */
    public function __construct(array $settings = [], array $labels = [])
    {
        $unit = $settings['barrel_length_unit'] ?? 'in';
        $this->unit = in_array($unit, ['in', 'cm', 'mm'], true) ? $unit : 'in';
        $this->links = !empty($settings['caliber_links']);
        $this->format = self::validFormat($settings['cost_per_round_format'] ?? 'long');
        $this->currency = isset($settings['currency_symbol']) ? (string) $settings['currency_symbol'] : '$';
        $this->labels = self::LABELS;
        foreach ($labels as $key => $label) {
            if (isset($this->labels[$key]) && is_string($label) && $label !== '') {
                $this->labels[$key] = $label;
            }
        }
    }

    /**
     * Parses a product title. Results are kept for the rest of the request.
     *
     * @param mixed $title a string, or an object with __toString(); HTML tags are removed
     * @param array $options brand, mpn, sku, price, kind ('firearm' or 'ammo'); see Parser::parseTitle()
     * @return array title, kind, brand, model, caliber, calibers, capacity, barrelLength, rounds, costPerRound
     */
    public function parse($title, array $options = []): array
    {
        $text = self::toText($title);
        $opts = [];
        foreach (['brand', 'mpn', 'sku'] as $key) {
            if (isset($options[$key]) && is_scalar($options[$key]) && (string) $options[$key] !== '') {
                $opts[$key] = (string) $options[$key];
            }
        }
        $kind = self::validKind($options['kind'] ?? null);
        if ($kind !== null) {
            $opts['kind'] = $kind;
        }
        $price = self::toPrice($options['price'] ?? null);
        if ($price !== null) {
            $opts['price'] = $price;
        }

        $key = serialize([$text, $opts]);
        if (!isset($this->cache[$key])) {
            if (count($this->cache) >= self::MAX_CACHED) {
                $this->cache = [];
            }
            $this->cache[$key] = Parser::parseTitle($text, $opts);
        }

        return $this->cache[$key];
    }

    /**
     * Builds the spec list as an HTML definition list.
     *
     * @param mixed $title the product title
     * @param array $options fields (array or comma-separated string), class (extra CSS class),
     *                       and the parse options of parse()
     * @return string HTML, or '' if the title has none of the requested specs
     */
    public function specsHtml($title, array $options = []): string
    {
        $parsed = $this->parse($title, $options);
        $specs = $this->specs($parsed, self::toFields($options['fields'] ?? null));
        if (!$specs) {
            return '';
        }

        $classes = ['gnp-specs', 'gnp-specs--' . $parsed['kind']];
        $extra = self::toText($options['class'] ?? '');
        foreach (preg_split('/\s+/', $extra, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $class) {
            if (preg_match('/^[A-Za-z_][A-Za-z0-9_-]*$/', $class)) {
                $classes[] = $class;
            }
        }

        $html = '<dl class="' . self::e(implode(' ', $classes)) . '">';
        foreach ($specs as $spec) {
            $key = str_replace('_', '-', $spec['key']);
            $html .= '<dt class="gnp-specs__label gnp-specs__label--' . $key . '">' . self::e($spec['label']) . '</dt>';
            $html .= '<dd class="gnp-specs__value gnp-specs__value--' . $key . '">' . $spec['html'] . '</dd>';
        }

        return $html . '</dl>';
    }

    /**
     * Lists the specs found in a parse result.
     *
     * @param array $parsed a result of parse()
     * @param string[] $fields keys of self::FIELDS, in display order
     * @return array<int, array{key: string, label: string, text: string, html: string}>
     */
    public function specs(array $parsed, array $fields = self::DEFAULT_FIELDS): array
    {
        $specs = [];
        foreach ($fields as $key) {
            $value = $this->specValue($parsed, $key);
            if ($value !== null) {
                $specs[] = ['key' => $key, 'label' => $this->labels[$key], 'text' => $value[0], 'html' => $value[1]];
            }
        }

        return $specs;
    }

    /**
     * Formats one spec of a parse result.
     *
     * @return array{0: string, 1: string}|null plain text and HTML, or null if the title does not have the spec
     */
    private function specValue(array $parsed, string $key): ?array
    {
        $text = null;
        switch ($key) {
            case 'kind':
                $text = $this->labels[$parsed['kind']] ?? null;
                break;
            case 'brand':
                $text = $parsed['brand'] !== '' ? $parsed['brand'] : null;
                break;
            case 'model':
                $text = $parsed['model']['model'] ?? null;
                break;
            case 'caliber':
                return $this->calibersValue($parsed['calibers'] ?? []);
            case 'caliber_type':
                $type = $parsed['caliber']['type'] ?? '';
                $text = $type !== '' ? ($this->labels[$type] ?? null) : null;
                break;
            case 'capacity':
                $text = isset($parsed['capacity']) ? $this->rounds((int) $parsed['capacity']) : null;
                break;
            case 'barrel_length':
                $text = isset($parsed['barrelLength']) ? $this->formatBarrelLength((float) $parsed['barrelLength']) : null;
                break;
            case 'rounds':
                $text = isset($parsed['rounds']) ? $this->rounds((int) $parsed['rounds']) : null;
                break;
            case 'cost_per_round':
                $text = isset($parsed['costPerRound']) ? $this->formatCost((float) $parsed['costPerRound']) : null;
                break;
        }
        if ($text === null || $text === '') {
            return null;
        }

        return [(string) $text, self::e((string) $text)];
    }

    /**
     * @param array $calibers calibers from the parser
     * @return array{0: string, 1: string}|null
     */
    private function calibersValue(array $calibers): ?array
    {
        $names = [];
        $html = [];
        foreach ($calibers as $caliber) {
            $name = (string) ($caliber['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $names[] = $name;
            $html[] = $this->caliberHtml($caliber);
        }

        return $names ? [implode(', ', $names), implode(', ', $html)] : null;
    }

    /**
     * A caliber name as HTML: a link to its GunsNPrices.com page if caliber links are on, else plain text.
     */
    public function caliberHtml(array $caliber): string
    {
        $name = self::e((string) ($caliber['name'] ?? ''));
        $url = (string) ($caliber['url'] ?? '');
        if ($this->links && strpos($url, self::LINK_PREFIX) === 0) {
            return '<a class="gnp-caliber" href="' . self::e($url) . '">' . $name . '</a>';
        }

        return $name;
    }

    /**
     * The cost per round of a box of ammunition, as text.
     *
     * @param mixed $subject the product title, or the number of rounds in the box
     * @param mixed $price the price of the box: a number or a string such as "$18.99"
     * @param array $options format ('long', 'short', 'amount'), currency, decimals (2 to 4),
     *                       and the parse options of parse() when $subject is a title
     * @return string for example "$0.38 per round", or '' without a round count or a price
     */
    public function costPerRound($subject, $price, array $options = []): string
    {
        $price = self::toPrice($price);
        if ($price === null) {
            return '';
        }
        if (is_int($subject) || is_float($subject) || (is_string($subject) && is_numeric(trim($subject)))) {
            $rounds = (int) round((float) $subject);
            $cost = $rounds > 0 ? Parser::costPerRound($price, $rounds) : null;
        } else {
            $cost = $this->parse($subject, ['price' => $price] + $options)['costPerRound'];
        }
        if ($cost === null) {
            return '';
        }

        return $this->formatCost(
            (float) $cost,
            $options['format'] ?? null,
            isset($options['currency']) ? (string) $options['currency'] : null,
            (int) ($options['decimals'] ?? 2)
        );
    }

    /**
     * Formats a cost per round.
     *
     * @param float $cost the cost of one round
     * @param string|null $format 'long' ("$0.38 per round"), 'short' ("$0.38/rd") or 'amount' ("$0.38");
     *                            null for the configured format
     * @param string|null $currency the currency symbol; null for the configured one
     * @param int $decimals the most decimal places to show (2 to 4); at least 2 are always shown
     */
    public function formatCost(float $cost, ?string $format = null, ?string $currency = null, int $decimals = 2): string
    {
        $amount = ($currency ?? $this->currency) . self::formatNumber($cost, max(2, min(4, $decimals)), 2);
        switch ($format !== null ? self::validFormat($format) : $this->format) {
            case 'short':
                return strtr($this->labels['per_round_short'], ['{cost}' => $amount]);
            case 'amount':
                return $amount;
        }

        return strtr($this->labels['per_round_long'], ['{cost}' => $amount]);
    }

    /**
     * The display name of a caliber.
     *
     * @param mixed $value a caliber as stores write it, such as "9MM LUGER" or "308 WIN (7.62X51 NATO)"
     * @return string the name, such as "9×19mm Parabellum", or the text unchanged if it is not a known caliber
     */
    public function caliberName($value): string
    {
        $text = self::toText($value);
        $caliber = Parser::normalizeCaliber($text);

        return $caliber !== null ? $caliber['name'] : $text;
    }

    /**
     * The barrel length found in a firearm title, in the configured unit.
     *
     * @return string for example "16.5 in", or '' if the title has none
     */
    public function barrelLength($title): string
    {
        $inches = Parser::barrelLength(self::toText($title));

        return $inches !== null ? $this->formatBarrelLength($inches) : '';
    }

    /**
     * Formats a barrel length given in inches in the configured unit: "16.5 in", "41.9 cm" or "419 mm".
     */
    public function formatBarrelLength(float $inches): string
    {
        switch ($this->unit) {
            case 'mm':
                return strtr($this->labels['unit_mm'], ['{length}' => self::formatNumber($inches * self::MM_PER_INCH, 0)]);
            case 'cm':
                return strtr($this->labels['unit_cm'], ['{length}' => self::formatNumber($inches * self::MM_PER_INCH / 10, 1)]);
        }

        return strtr($this->labels['unit_in'], ['{length}' => self::formatNumber($inches, 2)]);
    }

    /** "1 round", "15 rounds". */
    private function rounds(int $count): string
    {
        return $count === 1 ? $this->labels['one_round'] : strtr($this->labels['n_rounds'], ['{count}' => (string) $count]);
    }

    /**
     * Formats a number with at most $max decimal places and at least $min.
     *
     * Extra trailing zeros are dropped: formatNumber(16.0, 2) is "16", formatNumber(0.3798, 3, 2) is "0.38".
     */
    public static function formatNumber(float $number, int $max, int $min = 0): string
    {
        $text = number_format(round($number, $max), $max, '.', '');
        if (strpos($text, '.') === false) {
            return $text;
        }
        [$int, $dec] = explode('.', $text);
        $dec = rtrim($dec, '0');
        if (strlen($dec) < $min) {
            $dec = str_pad($dec, $min, '0');
        }

        return $dec === '' ? $int : $int . '.' . $dec;
    }

    /**
     * Reads a price: a number, or a string such as "18.99", "$1,049.99" or "USD 18.99".
     *
     * @return float|null the price, or null if it is empty, not a number, or not above zero
     */
    public static function toPrice($value): ?float
    {
        if (is_int($value) || is_float($value)) {
            $price = (float) $value;
        } elseif (is_string($value) || (is_object($value) && method_exists($value, '__toString'))) {
            $text = str_replace(',', '', (string) $value);
            if (!preg_match('/-?(?:\d+(?:\.\d+)?|\.\d+)/', $text, $m)) {
                return null;
            }
            $price = (float) $m[0];
        } else {
            return null;
        }

        return is_finite($price) && $price > 0 ? $price : null;
    }

    /**
     * Turns a value from a template into plain text: objects with __toString() are cast,
     * HTML tags are removed, arrays and other values give ''.
     */
    public static function toText($value): string
    {
        if (is_string($value)) {
            $text = $value;
        } elseif (is_int($value) || is_float($value)) {
            $text = (string) $value;
        } elseif (is_object($value) && method_exists($value, '__toString')) {
            $text = (string) $value;
        } else {
            return '';
        }

        return trim(strip_tags($text));
    }

    /**
     * Reads a fields option: an array or a comma-separated string of keys of self::FIELDS.
     *
     * @return string[] valid keys, in the given order; self::DEFAULT_FIELDS if none are given
     */
    public static function toFields($fields): array
    {
        if (is_string($fields)) {
            $fields = explode(',', $fields);
        }
        if (!is_array($fields)) {
            return self::DEFAULT_FIELDS;
        }
        $valid = [];
        foreach ($fields as $field) {
            $field = is_string($field) ? str_replace('-', '_', strtolower(trim($field))) : '';
            if (in_array($field, self::FIELDS, true) && !in_array($field, $valid, true)) {
                $valid[] = $field;
            }
        }

        return $valid ?: self::DEFAULT_FIELDS;
    }

    /** @return string|null 'firearm', 'ammo', or null for "guess from the title" */
    private static function validKind($kind): ?string
    {
        return $kind === 'firearm' || $kind === 'ammo' ? $kind : null;
    }

    private static function validFormat($format): string
    {
        return in_array($format, ['long', 'short', 'amount'], true) ? $format : 'long';
    }

    /** Escapes text for HTML. */
    public static function e(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
