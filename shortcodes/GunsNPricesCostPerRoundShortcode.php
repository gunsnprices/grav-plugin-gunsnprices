<?php

namespace Grav\Plugin\Shortcodes;

use Grav\Plugin\GunsNPrices\Formatter;
use Thunder\Shortcode\Shortcode\ShortcodeInterface;

/**
 * [cost-per-round price="18.99" rounds="50" /]
 * [cost-per-round price="18.99"]Federal 9mm 115gr FMJ 50 Rounds[/cost-per-round]
 *
 * Loaded only when the Shortcode Core plugin is installed.
 */
class GunsNPricesCostPerRoundShortcode extends Shortcode
{
    public function init()
    {
        // Raw handlers run before Markdown, so the title is read as typed and the HTML
        // is not wrapped in a paragraph. Older Shortcode Core versions only have getHandlers().
        $handlers = method_exists($this->shortcode, 'getRawHandlers') ? $this->shortcode->getRawHandlers() : $this->shortcode->getHandlers();
        $handlers->add('cost-per-round', function (ShortcodeInterface $sc) {
            $options = [];
            foreach (['kind', 'format', 'currency', 'decimals', 'brand'] as $name) {
                $value = $sc->getParameter($name);
                if ($value !== null && $value !== '') {
                    $options[$name] = $value;
                }
            }
            $rounds = $sc->getParameter('rounds');
            $subject = $rounds !== null && $rounds !== '' ? $rounds : $sc->getParameter('title', $sc->getContent());
            $text = $this->grav['gunsnprices.formatter']->costPerRound($subject, $sc->getParameter('price'), $options);

            return $text !== '' ? '<span class="gnp-cost-per-round">' . Formatter::e($text) . '</span>' : '';
        });
    }
}
