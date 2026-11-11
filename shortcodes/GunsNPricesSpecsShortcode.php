<?php

namespace Grav\Plugin\Shortcodes;

use Thunder\Shortcode\Shortcode\ShortcodeInterface;

/**
 * [firearm-specs]Glock 19 Gen 5 9mm 4.02" 15rd Pistol[/firearm-specs]
 * [firearm-specs title="Federal 9mm 115gr FMJ 50 Rounds" price="18.99" fields="caliber,rounds,cost_per_round" /]
 *
 * Loaded only when the Shortcode Core plugin is installed.
 */
class GunsNPricesSpecsShortcode extends Shortcode
{
    public function init()
    {
        // Raw handlers run before Markdown, so the title is read as typed and the HTML
        // is not wrapped in a paragraph. Older Shortcode Core versions only have getHandlers().
        $handlers = method_exists($this->shortcode, 'getRawHandlers') ? $this->shortcode->getRawHandlers() : $this->shortcode->getHandlers();
        $handlers->add('firearm-specs', function (ShortcodeInterface $sc) {
            $title = $sc->getParameter('title');
            $options = [];
            foreach (['fields', 'kind', 'price', 'brand', 'mpn', 'sku', 'class'] as $name) {
                $value = $sc->getParameter($name);
                if ($value !== null && $value !== '') {
                    $options[$name] = $value;
                }
            }

            return $this->grav['gunsnprices.formatter']->specsHtml($title !== null ? $title : $sc->getContent(), $options);
        });
    }
}
