<?php

/**
 * Tests for the Formatter and the Twig extension. No test framework is needed.
 *
 *     php tests/run.php                       Formatter tests only
 *     php tests/run.php /path/to/grav         also the Twig extension, with the Twig of that Grav install
 *
 * GRAV_ROOT=/path/to/grav works too. Exit code 0 when all tests pass.
 */

use Grav\Plugin\GunsNPrices\Formatter;
use Grav\Plugin\GunsNPrices\TwigExtension;

error_reporting(E_ALL);
ini_set('display_errors', 'stderr');

require dirname(__DIR__) . '/vendor/autoload.php';

$failures = 0;
$count = 0;

// Any notice, warning or deprecation raised in the plugin's own files is a failure.
// (Grav 1.7's Twig 1 raises deprecations on PHP 8.1+; those are not counted.)
set_error_handler(function (int $level, string $message, string $file, int $line): bool {
    global $failures;
    $root = dirname(__DIR__) . '/';
    if (strpos($file, $root) === 0 && strpos($file, $root . 'vendor/') !== 0) {
        $failures++;
        fwrite(STDERR, "FAIL: PHP error in plugin code: $message ($file:$line)\n");
        return true;
    }
    if ($level === E_DEPRECATED || $level === E_USER_DEPRECATED) {
        return true;
    }
    return false;
});

function check(string $name, $expected, $actual): void
{
    global $failures, $count;
    $count++;
    if ($expected === $actual) {
        return;
    }
    $failures++;
    fwrite(STDERR, "FAIL: $name\n  expected: " . var_export($expected, true) . "\n  actual:   " . var_export($actual, true) . "\n");
}

function contains(string $name, string $needle, string $haystack, bool $expected = true): void
{
    check($name, $expected, strpos($haystack, $needle) !== false);
}

$glock = 'Glock 19 Gen 5 MOS 9mm 4.02in 15rd Pistol PA195S203MOS';
$federal = 'Federal American Eagle 5.56x45mm 55gr FMJ 1000 Rounds';

// Parsing.
$f = new Formatter();
$parsed = $f->parse($glock);
check('parse kind', 'firearm', $parsed['kind']);
check('parse model', 'Glock 19 Gen 5 MOS', $parsed['model']['model']);
check('parse caliber', '9×19mm Parabellum', $parsed['caliber']['name']);
check('parse capacity', 15, $parsed['capacity']);
check('parse barrel length', 4.02, $parsed['barrelLength']);
check('parse is cached', $parsed, $f->parse($glock));
check('parse strips tags', 'Glock 19 Gen 5 MOS', $f->parse('<b>' . $glock . '</b>')['model']['model']);
check('parse ammo', 1000, $f->parse($federal)['rounds']);
check('parse price option', 0.45, $f->parse($federal, ['price' => '449.99'])['costPerRound']);
check('parse forced kind', 50, $f->parse('Magtech 9mm 50rd', ['kind' => 'ammo'])['rounds']);
check('parse bad kind is ignored', 'firearm', $f->parse('Magtech 9mm 50rd', ['kind' => 'shoe'])['kind']);
check('parse array title', '', $f->parse(['x'])['title']);
check('parse object title', 'Glock 19 Gen 5 MOS', $f->parse(new class {
    public function __toString(): string
    {
        return 'Glock 19 Gen 5 MOS 9mm 15rd';
    }
})['model']['model']);

// Spec list.
$expected = '<dl class="gnp-specs gnp-specs--firearm">'
    . '<dt class="gnp-specs__label gnp-specs__label--model">Model</dt><dd class="gnp-specs__value gnp-specs__value--model">Glock 19 Gen 5 MOS</dd>'
    . '<dt class="gnp-specs__label gnp-specs__label--caliber">Caliber</dt><dd class="gnp-specs__value gnp-specs__value--caliber">9×19mm Parabellum</dd>'
    . '<dt class="gnp-specs__label gnp-specs__label--capacity">Capacity</dt><dd class="gnp-specs__value gnp-specs__value--capacity">15 rounds</dd>'
    . '<dt class="gnp-specs__label gnp-specs__label--barrel-length">Barrel length</dt><dd class="gnp-specs__value gnp-specs__value--barrel-length">4.02 in</dd>'
    . '</dl>';
$html = $f->specsHtml($glock);
check('spec list html', $expected, $html);
contains('no links by default', 'href', $html, false);
contains('no gunsnprices.com by default', 'gunsnprices.com', $html, false);
check('empty title gives no list', '', $f->specsHtml(''));
check('title without specs gives no list', '', $f->specsHtml('Gift card'));
$fields = $f->specsHtml($glock, ['fields' => 'capacity, caliber']);
check('fields order', 2, substr_count($fields, '<dt'));
check('fields order: capacity first', true, strpos($fields, 'capacity') < strpos($fields, 'caliber'));
check('unknown fields give the defaults', $html, $f->specsHtml($glock, ['fields' => ['nope']]));
contains('hyphenated field names', 'Ammunition', $f->specsHtml($federal, ['fields' => ['kind', 'cost-per-round'], 'price' => 449.99]));
$ammo = $f->specsHtml($federal, ['price' => 449.99]);
contains('ammo list kind class', 'gnp-specs--ammo', $ammo);
contains('ammo list rounds', '<dd class="gnp-specs__value gnp-specs__value--rounds">1000 rounds</dd>', $ammo);
contains('ammo list cost per round', '<dd class="gnp-specs__value gnp-specs__value--cost-per-round">$0.45 per round</dd>', $ammo);
contains('caliber type', '>Handgun<', $f->specsHtml($glock, ['fields' => 'caliber_type']));
contains('several calibers', '.44 Magnum, .44 S&amp;W Special', $f->specsHtml('Henry Big Boy .44 Mag/.44 Spl 20in', ['fields' => 'caliber']));
contains('one round', '>1 round<', $f->specsHtml('Test 9mm 1rd pistol', ['fields' => 'capacity']));
contains('extra class', 'class="gnp-specs gnp-specs--firearm my-specs"', $f->specsHtml($glock, ['class' => 'my-specs']));
contains('unsafe class is dropped', 'script', $f->specsHtml($glock, ['class' => '"><script>alert(1)</script>']), false);
$xss = $f->specsHtml('Acme <script>alert(1)</script> 9mm 15rd', ['brand' => '<img src=x onerror=alert(1)>', 'fields' => 'brand,caliber']);
contains('title tags are removed', '<script', $xss, false);
contains('brand is escaped', '&lt;img src=x onerror=alert(1)&gt;', $xss);

// Caliber links (opt-in).
$linked = new Formatter(['caliber_links' => true]);
contains('caliber link', '<a class="gnp-caliber" href="https://gunsnprices.com/ammo/handgun-ammo/9-19mm-parabellum">9×19mm Parabellum</a>', $linked->specsHtml($glock));
check('caliber link only to gunsnprices.com', 'X', $linked->caliberHtml(['name' => 'X', 'url' => 'https://example.com/x']));
check('caliber link not for javascript urls', 'X', $linked->caliberHtml(['name' => 'X', 'url' => 'javascript:alert(1)']));
check('caliber without page', '.17 Test', $linked->caliberHtml(['name' => '.17 Test', 'url' => '']));

// Barrel length units.
check('barrel length in', '4.02 in', $f->barrelLength($glock));
check('barrel length cm', '10.2 cm', (new Formatter(['barrel_length_unit' => 'cm']))->barrelLength($glock));
check('barrel length mm', '102 mm', (new Formatter(['barrel_length_unit' => 'mm']))->barrelLength($glock));
check('barrel length bad unit', '4.02 in', (new Formatter(['barrel_length_unit' => 'ft']))->barrelLength($glock));
check('barrel length whole inches', '20 in', $f->barrelLength('Mossberg 590 Persuader 12/20 BL/SY 3"'));
check('barrel length none', '', $f->barrelLength('Federal 9mm 115gr FMJ 50 Rounds'));
check('barrel length fraction', '16.5 in', $f->barrelLength('Ruger 10/22 Carbine .22 LR 16 1/2" 10rd'));

// Cost per round.
check('cost from title', '$0.45 per round', $f->costPerRound($federal, 449.99));
check('cost from round count', '$0.38 per round', $f->costPerRound(50, 18.99));
check('cost from numeric string', '$0.38 per round', $f->costPerRound('50', '18.99'));
check('cost short', '$0.38/rd', $f->costPerRound(50, 18.99, ['format' => 'short']));
check('cost amount', '$0.38', $f->costPerRound(50, 18.99, ['format' => 'amount']));
check('cost bad format', '$0.38 per round', $f->costPerRound(50, 18.99, ['format' => 'huge']));
check('cost 3 decimals, trailing zero dropped', '$0.38 per round', $f->costPerRound(50, 18.99, ['decimals' => 3]));
check('cost 4 decimals', '$0.3798 per round', $f->costPerRound(50, 18.99, ['decimals' => 4]));
check('cost 9 decimals is capped at 4', '$0.3798 per round', $f->costPerRound(50, 18.99, ['decimals' => 9]));
check('cost currency option', '€0.38 per round', $f->costPerRound(50, 18.99, ['currency' => '€']));
check('cost rounds half up', '$0.05 per round', $f->costPerRound(500, 22.5));
check('cost two decimals kept', '$0.40 per round', $f->costPerRound(50, 20));
check('cost price string', '$1.05 per round', $f->costPerRound('Federal 9mm 115gr FMJ 1000 Rounds', '$1,049.99'));
check('cost short title reads as a firearm', '', $f->costPerRound('Magtech 9mm 50rd', 18.99));
check('cost short title with kind ammo', '$0.38 per round', $f->costPerRound('Magtech 9mm 50rd', 18.99, ['kind' => 'ammo']));
check('cost not for firearms', '', $f->costPerRound('Glock 19 Gen 5 9mm 15rd', 549));
check('cost without price', '', $f->costPerRound(50, null));
check('cost with price 0', '', $f->costPerRound(50, 0));
check('cost with bad price', '', $f->costPerRound(50, 'call'));
check('cost with 0 rounds', '', $f->costPerRound(0, 18.99));
check('cost with title without count', '', $f->costPerRound('Federal 9mm 115gr FMJ', 18.99));
$configured = new Formatter(['cost_per_round_format' => 'short', 'currency_symbol' => 'US$']);
check('cost configured format and currency', 'US$0.38/rd', $configured->costPerRound(50, 18.99));
check('cost option beats configured format', 'US$0.38 per round', $configured->costPerRound(50, 18.99, ['format' => 'long']));

// Caliber names.
check('caliber name 9MM LUGER', '9×19mm Parabellum', $f->caliberName('9MM LUGER'));
check('caliber name 308', '.308 Win', $f->caliberName('308 WIN (7.62X51 NATO)'));
check('caliber name 12GA', '12 Gauge', $f->caliberName('12GA'));
check('caliber name unknown stays', 'Not a caliber', $f->caliberName('Not a caliber'));
check('caliber name null', '', $f->caliberName(null));

// Helpers.
check('price float', 18.99, Formatter::toPrice(18.99));
check('price string', 1049.99, Formatter::toPrice('$1,049.99'));
check('price leading dot', 0.5, Formatter::toPrice('.50'));
check('price text', null, Formatter::toPrice('call for price'));
check('price negative', null, Formatter::toPrice('-5'));
check('price negative number', null, Formatter::toPrice(-5));
check('price array', null, Formatter::toPrice([1]));
check('number 16.0', '16', Formatter::formatNumber(16.0, 2));
check('number 16.50', '16.5', Formatter::formatNumber(16.50, 2));
check('number min decimals', '0.40', Formatter::formatNumber(0.4, 4, 2));
check('fields string', ['caliber', 'capacity'], Formatter::toFields('caliber, capacity, caliber'));
check('fields empty', Formatter::DEFAULT_FIELDS, Formatter::toFields(''));

// Translated labels.
$de = new Formatter([], ['caliber' => 'Kaliber', 'n_rounds' => '{count} Schuss', 'unit_in' => '{length} Zoll', 'nope' => 'x', 'model' => '']);
$deHtml = $de->specsHtml($glock);
contains('label override', '>Kaliber<', $deHtml);
contains('plural override', '>15 Schuss<', $deHtml);
contains('unit override', '>4.02 Zoll<', $deHtml);
contains('empty label keeps the default', '>Model<', $deHtml);

// Twig extension, with the Twig of a Grav install.
$grav = $argv[1] ?? getenv('GRAV_ROOT') ?: '';
if ($grav !== '') {
    require rtrim($grav, '/') . '/vendor/autoload.php';
    $twigVersion = \Twig\Environment::VERSION;

    $render = function (string $template, array $vars = [], ?Formatter $formatter = null, ?\Twig\Extension\SandboxExtension $sandbox = null): string {
        $env = new \Twig\Environment(new \Twig\Loader\ArrayLoader(['t' => $template]), ['autoescape' => 'html', 'strict_variables' => true]);
        $env->addExtension(new TwigExtension($formatter ?? new Formatter()));
        if ($sandbox) {
            $env->addExtension($sandbox);
        }

        return $env->render('t', $vars);
    };

    check('twig firearm_specs filter', $expected, $render('{{ title|firearm_specs }}', ['title' => $glock]));
    check('twig firearm_specs filter options', 2, substr_count($render("{{ title|firearm_specs({fields: 'caliber,capacity'}) }}", ['title' => $glock]), '<dt'));
    check('twig cost_per_round filter', '$0.45 per round', $render('{{ title|cost_per_round(449.99) }}', ['title' => $federal]));
    check('twig cost_per_round round count', '$0.38/rd', $render("{{ 50|cost_per_round(18.99, {format: 'short'}) }}"));
    check('twig cost_per_round is escaped', '&lt;b&gt;0.38 per round', $render("{{ 50|cost_per_round(18.99, {currency: '<b>'}) }}"));
    check('twig cost_per_round kind', '$0.38 per round', $render("{{ 'Magtech 9mm 50rd'|cost_per_round(18.99, {kind: 'ammo'}) }}"));
    check('twig caliber_name', '9×19mm Parabellum', $render("{{ '9mm'|caliber_name }}"));
    check('twig caliber_name strips tags and is escaped', 'x &amp; y', $render('{{ v|caliber_name }}', ['v' => '<i>x</i> & y']));
    check('twig barrel_length', '4.02 in', $render('{{ title|barrel_length }}', ['title' => $glock]));
    check('twig magazine_capacity', '15', $render('{{ title|magazine_capacity }}', ['title' => $glock]));
    check('twig rounds_per_box', '1000', $render('{{ title|rounds_per_box }}', ['title' => $federal]));
    check('twig firearm_specs function', 'Glock 19 Gen 5 MOS|15|4.02', $render('{% set s = firearm_specs(title) %}{{ s.model.model }}|{{ s.capacity }}|{{ s.barrelLength }}', ['title' => $glock]));
    check('twig firearm_specs function price', '0.45', $render('{{ firearm_specs(title, {price: 449.99}).costPerRound }}', ['title' => $federal]));
    check('twig find_calibers', '.44 Magnum, .44 S&amp;W Special', $render("{{ find_calibers('Henry Big Boy .44 Mag/.44 Spl 20in')|map(c => c.name)|join(', ') }}"));
    check('twig list_calibers', '293', $render('{{ list_calibers()|length }}'));
    check('twig ammo test', 'ammo|firearm', $render("{{ 'Hornady 9mm 115gr FTX Critical Defense 25rd' is ammo ? 'ammo' : 'firearm' }}|{{ title is ammo ? 'ammo' : 'firearm' }}", ['title' => $glock]));
    contains('twig caliber links setting', 'href="https://gunsnprices.com/ammo/handgun-ammo/9-19mm-parabellum"', $render('{{ title|firearm_specs }}', ['title' => $glock], $linked));

    // Grav 2 runs Twig in page content in a sandbox; the plugin allows its own names there.
    $policy = new \Twig\Sandbox\SecurityPolicy(['if', 'set'], array_merge(TwigExtension::FILTERS, ['escape', 'raw']), [], [], TwigExtension::FUNCTIONS);
    $sandbox = new \Twig\Extension\SandboxExtension($policy, true);
    check('sandbox allows the plugin', $expected . '|$0.38 per round|9×19mm Parabellum|Glock 19 Gen 5 MOS', $render("{{ title|firearm_specs }}|{{ 50|cost_per_round(18.99) }}|{{ '9mm'|caliber_name }}|{{ firearm_specs(title).model.model }}", ['title' => $glock], null, $sandbox));
    $blocked = '';
    try {
        $render("{{ title|upper }}", ['title' => $glock], null, $sandbox);
    } catch (\Twig\Sandbox\SecurityError $e) {
        $blocked = 'blocked';
    }
    check('sandbox blocks other filters', 'blocked', $blocked);

    echo "Twig $twigVersion from $grav\n";
} else {
    echo "Twig tests skipped (pass the path of a Grav install to run them)\n";
}

echo "PHP " . PHP_VERSION . ": $count checks, $failures failed\n";
exit($failures ? 1 : 0);
