<?php

namespace Grav\Plugin;

use Composer\Autoload\ClassLoader;
use Grav\Common\Plugin;
use Grav\Plugin\GunsNPrices\Formatter;
use Grav\Plugin\GunsNPrices\TwigExtension;
use RocketTheme\Toolbox\Event\Event;

/**
 * GunsNPrices Firearm Specs: Twig filters and shortcodes for gun and ammo product titles.
 *
 * The parser runs locally (gunsnprices/gunsnprices library in vendor/). The plugin makes no
 * HTTP requests. Caliber links to gunsnprices.com are off unless caliber_links is on.
 */
class GunsnpricesPlugin extends Plugin
{
    /** @var Formatter|null */
    private $formatter;

    public static function getSubscribedEvents(): array
    {
        return [
            'onTwigExtensions' => ['onTwigExtensions', 0],
            // Fired by Grav 2 when it builds the sandbox for Twig in page content.
            'onBuildTwigSandboxPolicy' => ['onBuildTwigSandboxPolicy', 0],
            // Fired only when the Shortcode Core plugin is installed and enabled.
            'onShortcodeHandlers' => ['onShortcodeHandlers', 0],
        ];
    }

    /**
     * Composer autoload (loads the gunsnprices/gunsnprices library).
     */
    public function autoload(): ClassLoader
    {
        return require __DIR__ . '/vendor/autoload.php';
    }

    /**
     * The formatter with the plugin settings, shared by the Twig extension and the shortcodes.
     */
    public function formatter(): Formatter
    {
        if ($this->formatter === null) {
            $this->formatter = new Formatter((array) $this->config->get('plugins.gunsnprices', []), $this->labels());
        }

        return $this->formatter;
    }

    public function onTwigExtensions(): void
    {
        $this->grav['twig']->twig()->addExtension(new TwigExtension($this->formatter()));
    }

    /**
     * Allows the filters and functions in sandboxed page content (Grav 2). They only read the
     * text they are given and return text or arrays.
     */
    public function onBuildTwigSandboxPolicy(Event $event): void
    {
        $filters = (array) $event['filters'];
        $functions = (array) $event['functions'];
        foreach (TwigExtension::FILTERS as $name) {
            $filters[] = $name;
        }
        foreach (TwigExtension::FUNCTIONS as $name) {
            $functions[] = $name;
        }
        $event['filters'] = $filters;
        $event['functions'] = $functions;
    }

    public function onShortcodeHandlers(): void
    {
        if (!isset($this->grav['shortcode'])) {
            return;
        }
        if (!isset($this->grav['gunsnprices.formatter'])) {
            $this->grav['gunsnprices.formatter'] = $this->formatter();
        }
        $this->grav['shortcode']->registerAllShortcodes(__DIR__ . '/shortcodes');
    }

    /**
     * Output labels from languages.yaml, in the current language.
     *
     * @return array<string, string>
     */
    private function labels(): array
    {
        $labels = [];
        if (!isset($this->grav['language'])) {
            return $labels;
        }
        $language = $this->grav['language'];
        foreach (array_keys(Formatter::LABELS) as $key) {
            $code = 'PLUGIN_GUNSNPRICES.OUT_' . strtoupper($key);
            $text = $language->translate([$code]);
            if (is_string($text) && $text !== '' && $text !== $code) {
                $labels[$key] = $text;
            }
        }

        return $labels;
    }
}
