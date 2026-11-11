# GunsNPrices Firearm Specs Plugin

The **GunsNPrices Firearm Specs** plugin for [Grav CMS](https://github.com/getgrav/grav) reads the specs of firearms
and ammunition from product titles. It adds Twig filters, functions and a test for themes and pages, and two
shortcodes for page content.

Give it the title "Glock 19 Gen 5 MOS 9mm 4.02in 15rd Pistol PA195S203MOS" and `{{ title|firearm_specs }}` shows:

| Spec          | Value              |
|---------------|--------------------|
| Model         | Glock 19 Gen 5 MOS |
| Caliber       | 9×19mm Parabellum  |
| Capacity      | 15 rounds          |
| Barrel length | 4.02 in            |

For ammunition it reads the round count and works out the cost per round:
`{{ 'Federal American Eagle 5.56x45mm 55gr FMJ 1000 Rounds'|cost_per_round(449.99) }}` prints "$0.45 per round".

The plugin uses the open source title parser of [GunsNPrices](https://gunsnprices.com), a price comparison site for
guns and ammunition that uses it to match the same product across 40+ US stores. The parser knows 293 calibers with
their retailer spellings ("9MM LUGER", "5.56 NATO", "12GA", "300 BLK") and the models of 50 brands. It runs on your
server: the plugin makes no HTTP requests and has no tracking.

## Features

- `firearm_specs` filter: a list of the specs found in a title (model, caliber, capacity, barrel length, rounds per
  box, cost per round, and more on request).
- `cost_per_round` filter: "$0.38 per round", "$0.38/rd" or "$0.38", from a title or a round count and a price.
- `caliber_name` filter: one name per cartridge. "9MM LUGER", "9x19" and "9mm Luger" all become "9×19mm Parabellum".
- `barrel_length`, `magazine_capacity` and `rounds_per_box` filters for single values.
- `firearm_specs()` function: the full parse result as an array, for your own markup.
- `find_calibers()` and `list_calibers()` functions, and an `ammo` test.
- `[firearm-specs]` and `[cost-per-round]` shortcodes, if the
  [Shortcode Core](https://github.com/getgrav/grav-plugin-shortcode-core) plugin is installed. It is optional.
- Barrel lengths in inches, centimeters or millimeters.
- Optional links from caliber names to their price comparison pages on GunsNPrices.com. Off by default.
- Works in Grav 2's sandbox for Twig in page content.

## Installation

Installing the plugin can be done in one of three ways: with the GPM (Grav Package Manager), with the Admin plugin,
or manually with a zip file.

### GPM installation (preferred)

In the root of your Grav site, run:

    bin/gpm install gunsnprices

This installs the plugin into `user/plugins/gunsnprices`.

### Admin plugin

If you use the Admin plugin, open **Plugins**, click **Add**, search for "GunsNPrices" and install it.

### Manual installation

Download the zip of the [latest release](https://github.com/gunsnprices/grav-plugin-gunsnprices/releases), unzip it
into `user/plugins`, and rename the folder to `gunsnprices`. You should then have the plugin files in

    your/site/user/plugins/gunsnprices

The release includes the parser library in `vendor/`, so Composer is not needed.

## Requirements

- Grav 1.7 or Grav 2
- PHP 7.4 or later (Grav 2 itself needs PHP 8.3 or later)
- Optional: Shortcode Core, for the shortcodes

## Configuration

Before you change the settings, copy `user/plugins/gunsnprices/gunsnprices.yaml` to
`user/config/plugins/gunsnprices.yaml` and edit the copy. If you use the Admin plugin, it saves the settings there
for you.

These are the defaults:

```yaml
enabled: true
barrel_length_unit: in        # in, cm or mm
cost_per_round_format: long   # long ("$0.38 per round"), short ("$0.38/rd") or amount ("$0.38")
currency_symbol: '$'
caliber_links: false          # link caliber names in spec lists to their GunsNPrices.com price pages
```

- `barrel_length_unit`: the unit of barrel lengths in spec lists and in the `barrel_length` filter. The parser reads
  lengths in inches; 16.5 in is shown as "41.9 cm" or "419 mm".
- `cost_per_round_format` and `currency_symbol`: how costs per round are shown. Each call can override them.
- `caliber_links`: when on, caliber names in spec lists (the `firearm_specs` filter and the `[firearm-specs]`
  shortcode) link to their price comparison pages on GunsNPrices.com, for example "9×19mm Parabellum" links to
  `https://gunsnprices.com/ammo/handgun-ammo/9-19mm-parabellum`. These are links to an external site, so they are off
  unless you turn them on. No other output links anywhere.

Pages that are already cached keep their old output: clear the cache (`bin/grav clearcache`) after a change.

## Usage

### Twig filters

| Filter | Example | Result |
|---|---|---|
| `firearm_specs(options)` | `{{ title\|firearm_specs }}` | HTML list of specs (see below) |
| `cost_per_round(price, options)` | `{{ title\|cost_per_round(18.99) }}` | `$0.38 per round`, or `''` |
| | `{{ 50\|cost_per_round(18.99) }}` | `$0.38 per round` (the input is the round count) |
| `caliber_name` | `{{ '308 WIN (7.62X51 NATO)'\|caliber_name }}` | `.308 Win` (unknown text is returned unchanged) |
| `barrel_length` | `{{ 'Ruger 10/22 Carbine .22 LR 16 1/2" 10rd'\|barrel_length }}` | `16.5 in`, or `''` |
| `magazine_capacity` | `{{ 'Springfield Hellcat Pro 9mm 15rd/17rd'\|magazine_capacity }}` | `17`, or nothing |
| `rounds_per_box` | `{{ 'Winchester 308 150gr PowerMax 20rds'\|rounds_per_box }}` | `20`, or nothing |

Options of `firearm_specs`:

```twig
{{ product.title|firearm_specs({
    fields: 'model,caliber,capacity,barrel_length',   {# or a list: ['model', 'caliber'] #}
    price: product.price,     {# adds the cost per round to ammo #}
    kind: 'ammo',             {# 'firearm' or 'ammo'; guessed from the title if not given #}
    brand: product.brand,     {# helps when the title does not name the brand #}
    mpn: product.mpn,         {# part number; decodes Glock part numbers #}
    class: 'product-specs'    {# extra CSS class #}
}) }}
```

The fields are `kind`, `brand`, `model`, `caliber`, `caliber_type`, `capacity`, `barrel_length`, `rounds` and
`cost_per_round`. The default is `model, caliber, capacity, barrel_length, rounds, cost_per_round`. Specs that the
title does not contain are left out, and a title with none of them gives an empty string.

Options of `cost_per_round`: `format` (`long`, `short`, `amount`), `currency`, `decimals` (2 to 4, default 2) and
`kind`. Only titles that read like ammunition (bullet weight, "ammo", "Box of 50", "20rds") get a cost per round, so
a pistol with "15rd" in its title gets none. For short titles such as "Magtech 9mm 50rd", pass `{kind: 'ammo'}`.
The price can be a number or a string such as "$1,049.99".

### Twig functions and test

```twig
{% set specs = firearm_specs(product.title, {price: product.price}) %}
{{ specs.kind }}             {# 'firearm' or 'ammo' #}
{{ specs.model.model }}      {# 'Glock 19 Gen 5 MOS' #}
{{ specs.caliber.name }}     {# '9×19mm Parabellum' #}
{{ specs.capacity }}         {# 15 #}
{{ specs.barrelLength }}     {# 4.02 (inches) #}
{{ specs.rounds }}           {# rounds per box, ammo only #}
{{ specs.costPerRound }}     {# 0.3798, ammo with a price only #}

{{ find_calibers('Henry Big Boy .44 Mag/.44 Spl 20in')|map(c => c.name)|join(', ') }}
{# .44 Magnum, .44 S&W Special #}

{% for caliber in list_calibers() %}{{ caliber.name }} {% endfor %}   {# all 293 #}

{% if product.title is ammo %}...{% endif %}
```

`firearm_specs()` returns the result of the parser unchanged, with the keys `title`, `kind`, `brand`, `model`
(`brand`, `family`, `model`, `generation`, `configs`, `features`, `source`), `caliber`, `calibers`, `capacity`,
`barrelLength`, `rounds` and `costPerRound`. Each caliber has an `id`, a `name`, a `type` (`handgun`, `rifle`,
`rimfire`, `shotgun`, `airgun`, `muzzleloader`) and the `url` of its GunsNPrices.com page. The plugin itself never
prints that URL unless `caliber_links` is on. See the
[library README](https://github.com/gunsnprices/gunsnprices-php#usage) for details.

### Twig in page content

The filters and functions also work in Markdown pages that process Twig.

- **Grav 2** runs Twig in page content inside a security sandbox (Twig in content is on by default since 2.0.19).
  The plugin adds its own filters and functions to the sandbox's allow-list, so nothing needs to be configured.
- **Grav 1.7** needs Twig processing turned on for the page.

For a spec list in page content, let Twig run before Markdown so the list is not wrapped in a paragraph:

```markdown
---
title: Glock 19 Gen 5 review
process:
  twig: true
twig_first: true
---

{{ 'Glock 19 Gen 5 MOS 9mm 4.02in 15rd Pistol'|firearm_specs }}

A box of 50 at $18.99 is {{ 50|cost_per_round(18.99) }}.
```

### Shortcodes

With the [Shortcode Core](https://github.com/getgrav/grav-plugin-shortcode-core) plugin installed, you can use these
shortcodes in page content. Without it the plugin works the same, only the shortcodes are not available.

```markdown
[firearm-specs]Glock 19 Gen 5 MOS 9mm 4.02" 15rd Pistol[/firearm-specs]

[firearm-specs title="Federal American Eagle 5.56x45mm 55gr FMJ 1000 Rounds" price="449.99" fields="caliber,rounds,cost_per_round" /]

That box is [cost-per-round price="21.99" rounds="50" /].

[cost-per-round price="$21.99" format="short"]Federal 9mm 115gr FMJ 50 Rounds[/cost-per-round]
```

- `[firearm-specs]` takes the title as its content or as `title`, and the options `fields`, `price`, `kind`, `brand`,
  `mpn` and `class`.
- `[cost-per-round]` takes `price`, and either `rounds` or a title (as content or `title`). Options: `format`,
  `currency`, `decimals`, `kind`. Its output is `<span class="gnp-cost-per-round">...</span>`.

Both are processed before Markdown, so a title is read exactly as typed (an inch mark `"` is fine in the content) and
the spec list is not wrapped in a paragraph.

### Styling

The spec list is a definition list with these classes:

```html
<dl class="gnp-specs gnp-specs--firearm">
  <dt class="gnp-specs__label gnp-specs__label--caliber">Caliber</dt>
  <dd class="gnp-specs__value gnp-specs__value--caliber">9×19mm Parabellum</dd>
  ...
</dl>
```

`gnp-specs--ammo` is used for ammunition, and caliber links have the class `gnp-caliber`. The plugin adds no CSS or
JavaScript of its own. A simple two-column layout:

```css
.gnp-specs { display: grid; grid-template-columns: max-content 1fr; gap: .25rem 1rem; }
.gnp-specs__label { font-weight: 600; }
.gnp-specs__value { margin: 0; }
```

The labels ("Caliber", "15 rounds", "per round") are in `languages.yaml` and can be translated there.

### From PHP

Other plugins can use the same classes, with the plugin settings:

```php
use Grav\Plugin\GunsNPrices\Formatter;
use GunsNPrices\Parser;

$formatter = new Formatter((array) $this->config->get('plugins.gunsnprices'));
$formatter->specsHtml('Sig Sauer P365 XL 9mm 3.7" 12+1');                     // the HTML spec list
$formatter->costPerRound('Federal 9mm 115gr FMJ 50 Rounds', 21.99);         // "$0.44 per round"
Parser::parseTitle('Sig Sauer P365 XL 9mm 3.7" 12+1')['model']['model'];  // "Sig Sauer P365 XL"
```

## Accuracy

Measured on 63,755 store listings, the parser finds the caliber in 81% of firearm titles and 91% of ammunition
titles, and is right 95-98% of the time when it finds one. What it cannot find is usually not in the title. Titles
in English from US retailers work best. Models are known for 50 brands (Glock, Sig Sauer, Smith & Wesson, Ruger,
Springfield, Mossberg, Remington and more); other brands still get the caliber, capacity and barrel length. The
figures for each spec are in the [library README](https://github.com/gunsnprices/gunsnprices-php#accuracy).

## Privacy

The parser and its data are part of the plugin. The plugin makes no requests to other sites, sends no data anywhere,
loads no assets and sets no cookies. The only links to GunsNPrices.com are the optional caliber links, which are off
by default.

## Development

The tests need no framework. Pass the path of a Grav install to test the Twig extension with that Grav's Twig:

```bash
composer install
php tests/run.php                       # parser and formatting
php tests/run.php /path/to/grav         # plus the Twig filters, functions and sandbox
```

`vendor/` is committed, as GPM installs the plugin from the release archive. After changing `composer.json`, run
`composer update --no-dev` and commit `vendor/`.

## Credits

- Title parser: [gunsnprices/gunsnprices](https://github.com/gunsnprices/gunsnprices-php) 0.1, bundled in `vendor/`.
  The code is under the MIT License. The model dictionaries and the caliber list (`data/*.json`) are
  © GunsNPrices ([gunsnprices.com](https://gunsnprices.com)) under
  [CC BY 4.0](https://creativecommons.org/licenses/by/4.0/).
- The same data is published as open datasets on [GitHub](https://github.com/gunsnprices/firearm-datasets) and
  [Hugging Face](https://huggingface.co/datasets/gunsnprices/firearm-calibers).

Questions, corrections and missing models: open an issue or write to app@gunsnprices.com.
Current gun and ammo prices: [gunsnprices.com](https://gunsnprices.com).

## License

MIT. See [LICENSE](LICENSE).
