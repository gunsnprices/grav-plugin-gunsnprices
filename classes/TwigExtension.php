<?php

namespace Grav\Plugin\GunsNPrices;

use GunsNPrices\Parser;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Twig\TwigTest;

/**
 * Twig filters, functions and a test for gun and ammo product titles.
 *
 *     {{ product.title|firearm_specs }}                   spec list (HTML)
 *     {{ product.title|cost_per_round(18.99) }}           "$0.38 per round"
 *     {{ '9MM LUGER'|caliber_name }}                      "9×19mm Parabellum"
 *     {% set specs = firearm_specs(product.title) %}      the parse result as an array
 *     {% if product.title is ammo %}...{% endif %}
 */
class TwigExtension extends AbstractExtension
{
    /** Filter names, for the Grav 2 page content sandbox. */
    public const FILTERS = ['firearm_specs', 'cost_per_round', 'caliber_name', 'barrel_length', 'magazine_capacity', 'rounds_per_box'];

    /** Function names, for the Grav 2 page content sandbox. */
    public const FUNCTIONS = ['firearm_specs', 'find_calibers', 'list_calibers'];

    /** @var Formatter */
    private $formatter;

    public function __construct(Formatter $formatter)
    {
        $this->formatter = $formatter;
    }

    public function getFilters(): array
    {
        $f = $this->formatter;

        return [
            new TwigFilter('firearm_specs', [$this, 'specsFilter'], ['is_safe' => ['html']]),
            new TwigFilter('cost_per_round', [$this, 'costPerRoundFilter']),
            new TwigFilter('caliber_name', [$f, 'caliberName']),
            new TwigFilter('barrel_length', [$f, 'barrelLength']),
            new TwigFilter('magazine_capacity', [$this, 'magazineCapacity']),
            new TwigFilter('rounds_per_box', [$this, 'roundsPerBox']),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('firearm_specs', [$this, 'specsFunction']),
            new TwigFunction('find_calibers', [$this, 'findCalibers']),
            new TwigFunction('list_calibers', [Parser::class, 'listCalibers']),
        ];
    }

    public function getTests(): array
    {
        return [
            new TwigTest('ammo', [$this, 'isAmmo']),
        ];
    }

    /**
     * {{ title|firearm_specs }}, {{ title|firearm_specs({fields: 'caliber,capacity', price: 18.99}) }}
     *
     * @param mixed $title
     * @param mixed $options
     */
    public function specsFilter($title, $options = []): string
    {
        return $this->formatter->specsHtml($title, is_array($options) ? $options : []);
    }

    /**
     * {{ title|cost_per_round(18.99) }}, {{ 50|cost_per_round(18.99, {format: 'short'}) }}
     *
     * @param mixed $subject the title, or the number of rounds
     * @param mixed $price
     * @param mixed $options
     */
    public function costPerRoundFilter($subject, $price = null, $options = []): string
    {
        return $this->formatter->costPerRound($subject, $price, is_array($options) ? $options : []);
    }

    /**
     * {% set specs = firearm_specs(title, {price: 18.99}) %}
     *
     * @param mixed $title
     * @param mixed $options brand, mpn, sku, price, kind
     */
    public function specsFunction($title, $options = []): array
    {
        return $this->formatter->parse($title, is_array($options) ? $options : []);
    }

    /** @param mixed $title */
    public function magazineCapacity($title): ?int
    {
        return Parser::magazineCapacity(Formatter::toText($title));
    }

    /** @param mixed $title */
    public function roundsPerBox($title): ?int
    {
        return Parser::roundsPerBox(Formatter::toText($title));
    }

    /**
     * @param mixed $text
     * @return array<int, array{id: string, name: string, type: string, url: string}>
     */
    public function findCalibers($text): array
    {
        return Parser::findCalibers(Formatter::toText($text));
    }

    /** @param mixed $title */
    public function isAmmo($title): bool
    {
        return Parser::looksLikeAmmo(Formatter::toText($title));
    }
}
