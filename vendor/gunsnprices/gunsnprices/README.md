# gunsnprices

[![Packagist](https://img.shields.io/packagist/v/gunsnprices/gunsnprices)](https://packagist.org/packages/gunsnprices/gunsnprices)
[![Tests](https://github.com/gunsnprices/gunsnprices-php/actions/workflows/ci.yml/badge.svg)](https://github.com/gunsnprices/gunsnprices-php/actions/workflows/ci.yml)
[![PHP](https://img.shields.io/badge/php-7.4%20to%208.5-777bb4)](composer.json)
[![License](https://img.shields.io/badge/license-MIT%20%2B%20CC%20BY%204.0%20data-blue)](#license)

Parse firearm and ammunition product titles into structured data: brand, model, caliber, magazine capacity, barrel length,
rounds per box and cost per round.

```php
use GunsNPrices\Parser;

$info = Parser::parseTitle('Glock 19 Gen 5 MOS 9mm 4.02in 15rd Pistol PA195S203MOS');
$info['model']['model'];   // 'Glock 19 Gen 5 MOS'
$info['caliber']['name'];  // '9×19mm Parabellum'
$info['capacity'];         // 15
$info['barrelLength'];     // 4.02
```

This is the title parser behind [GunsNPrices](https://gunsnprices.com), a price comparison site that tracks 40+ US gun
and ammo stores. Store titles are messy (`G19 GEN5 9MM 15RD FS`, `O/U 12/28 BL/WD 3"`, `Federal 308 150gr ... 20rds`),
and matching the same product across stores starts with reading them reliably. The model dictionaries and the caliber
list in this package are the ones the site uses to group listings, so you get the same model names you see on
gunsnprices.com.

PHP 7.4 to 8.5, no dependencies (only the bundled `json` and `pcre` extensions; `mbstring` is not needed), about
0.3 ms per title. It is a port of the [JavaScript package](https://www.npmjs.com/package/gunsnprices)
([source](https://github.com/gunsnprices/gunsnprices-js)) and the [Python package](https://pypi.org/project/gunsnprices/)
([source](https://github.com/gunsnprices/gunsnprices-python)) and gives identical results: all three are checked
against the same 1,150 recorded titles, and the PHP port agrees with the JavaScript one on all 63,755 titles of the
GunsNPrices catalog.

## Install

```bash
composer require gunsnprices/gunsnprices
```

## What it recognizes

| Field | Example in a title | Result |
|---|---|---|
| Firearm or ammo | `... 150gr ... 20rds` | `'kind' => 'ammo'` |
| Brand and model (50 brands) | `G19 Gen5 MOS`, part number `PA195S203` | `Glock 19 Gen 5 MOS` |
| Calibers (293) | `.357 Mag / .38 Spl`, `6.5 CM`, `12ga`, `300 BLK`, `7MM RM` | `.357 Mag`, `.38 S&W Special`, ... |
| Magazine capacity | `15+1`, `17RD`, `13rd/15rd`, `10-shot` | `15`, `17`, `15`, `10` |
| Barrel length, inches | `4.02in`, `16 1/2"`, `22 BBL`, `O/U 12/28` | `4.02`, `16.5`, `22.0`, `28.0` |
| Rounds per box | `50/Box`, `Box of 20`, `1,000 Rounds`, `20/25` | `50`, `20`, `1000`, `20` |
| Cost per round | price `449.99` + `1000 Rounds` | `0.45` |

Brands with model dictionaries: Barrett, Benelli, Beretta, Bergara, Browning, Cadex, Canik, Charles Daly, Chiappa,
Christensen Arms, Cimarron, CMMG, Colt, CVA, CZ, Daniel Defense, Desert Eagle, Diamondback, Fierce, FN, Glock,
Great Lakes, Henry, Heritage, HK, Howa, Kel-Tec, Kimber, Kriss, Marlin, Mossberg, Remington, Retay, Rossi, Ruger, Savage,
SCCY, Seekins, Shadow Systems, Sig Sauer, Smith & Wesson, Springfield, Stevens, Stoeger, Taurus, Tikka, Tristar, Walther,
Weatherby, Winchester.

## Usage

Everything is a static method of `GunsNPrices\Parser` and returns plain arrays, so the results go straight into
`json_encode()`, a database row or a template.

### Everything at once

```php
use GunsNPrices\Parser;

$info = Parser::parseTitle('Federal American Eagle 5.56x45mm 55gr FMJ 1000 Rounds', ['price' => 449.99]);
$info['kind'];             // 'ammo'
$info['caliber']['name'];  // '5.56×45mm NATO'
$info['rounds'];           // 1000
$info['costPerRound'];     // 0.45
```

`Parser::parseTitle($title, ['brand' => ..., 'mpn' => ..., 'sku' => ..., 'price' => ..., 'kind' => ...])` returns an
array with these keys (the same names as the JavaScript package):

| Key | Type | Notes |
|---|---|---|
| `title` | `string` | the title you passed |
| `kind` | `'firearm'` or `'ammo'` | pass `kind` to skip the guess |
| `brand` | `string` | brand of the identified model, else the `brand` you passed, else `''` |
| `model` | `array` or `null` | firearms only, see below |
| `caliber` | `array` or `null` | first caliber in the title |
| `calibers` | `array[]` | all calibers, in order |
| `capacity` | `int` or `null` | magazine capacity (for `15+1` it is 15), firearms only |
| `barrelLength` | `float` or `null` | inches, firearms only |
| `rounds` | `int` or `null` | rounds per box, ammo only |
| `costPerRound` | `float` or `null` | ammo with `price` only, rounded to 4 decimals |

Passing `brand` (the manufacturer field from a feed) helps with titles that don't name the brand. Passing `mpn` lets it
decode Glock part numbers when the title says nothing useful:

```php
Parser::identifyModel('GLOCK 9MM PISTOL 15RD', ['brand' => 'Glock', 'mpn' => 'UA4350201']);
// ['brand' => 'Glock', 'family' => 'Glock 43', 'model' => 'Glock 43', 'generation' => '',
//  'configs' => [], 'features' => [], 'source' => 'mpn']
```

### Models

```php
$m = Parser::identifyModel('Sig Sauer P365 XL 9mm 3.7" 12+1 Optic Ready');
$m['family'];  // 'Sig Sauer P365'
$m['model'];   // 'Sig Sauer P365 XL'

Parser::listBrands();  // ['Barrett', 'Benelli', ...] (50)
```

A model has `brand`, `family`, `model` (the most specific name), `generation`, `configs` (such as `MOS`, `Compact`),
`features` (such as `Threaded Barrel`, `FDE`) and `source` (`'title'` or `'mpn'`). Accessories, parts, airguns and
airsoft are rejected, so it returns `null` for "Glock 19 holster" and "Umarex Glock 19 CO2 BB pistol".

### Calibers

```php
array_column(Parser::findCalibers('Henry Big Boy .44 Mag/.44 Spl 20in'), 'name');
// ['.44 Magnum', '.44 S&W Special']

Parser::normalizeCaliber('308 WIN (7.62X51 NATO)');
// ['id' => '308win', 'name' => '.308 Win', 'type' => 'rifle', 'url' => 'https://gunsnprices.com/ammo/rifle-ammo/308-win']
```

Each caliber has a stable `id`, a display `name`, a `type` (`handgun`, `rifle`, `rimfire`, `shotgun`, `airgun`,
`muzzleloader`) and the `url` of its GunsNPrices price comparison page (`''` if there is none). `normalizeCaliber()` is
meant for caliber fields in product feeds; `findCalibers()` and `findCaliber()` search free text; `listCalibers()`
returns all 293, sorted by name.

### Numbers

```php
Parser::magazineCapacity('Springfield Hellcat Pro OSP 9mm 3.7in 15rd/17rd');  // 17
Parser::barrelLength('Mossberg 590 Persuader 12/20 BL/SY 3"');                // 20.0 (3" is the chamber)
Parser::roundsPerBox('Winchester 308 150gr PowerMax Bonded 20rds');           // 20
Parser::costPerRound(31.99, 20);                                              // 1.5995
Parser::looksLikeAmmo('Hornady 9mm 115gr FTX Critical Defense 25rd');         // true
```

`Parser::VERSION` holds the package version.

## Input

Titles are UTF-8 strings as stores publish them: HTML entities (`26&quot;`, `10&#x2B;1`) are decoded, `null` reads as an
empty title, and invalid UTF-8 bytes are replaced with U+FFFD instead of breaking the regular expressions.

## Accuracy

Measured on 50,202 firearm and 13,555 ammunition listings from 40+ stores on gunsnprices.com (October 2026),
against the attributes stored on the site (the PHP port gives the same answers, so the figures are the same):

| What | Found in the title | Right when found |
|---|---|---|
| Model (32,317 listings with a known model) | 99.97% | 99.8% same name as the site* |
| Caliber, firearms | 81% | 97.8% |
| Caliber, ammo | 91% | 94.9% |
| Magazine capacity | 46% | 95.6% |
| Barrel length | 58% | 94.4% |
| Rounds per box | 80% | 96.6% |

"Found" is limited by the titles themselves: many stores don't put the caliber or barrel length in the title at all.
Most of the "wrong" cases we checked by hand are errors in the store data, not in the parser: a 6mm Creedmoor rifle
labeled 6.5 Creedmoor, a "1000 Rounds" case stored as a box of 20.

\* The other 0.2% are the same model with a cleaner name: the site calls 52 listings "S&W M&P Shield Plus Plus Shield",
the library says "S&W M&P Shield Plus".

## Performance

About 0.3 ms per `parseTitle()` call (PHP 8.5, Apple Silicon, PCRE JIT on), measured over the 63,755 catalog titles.
The data is read and the patterns are compiled on first use, which takes about 30 ms once per process; in PHP-FPM that
is once per request, so parse titles in batches (on import, in cron) rather than one per page view where you can.

## Data

The model dictionaries and caliber list ship inside the package as JSON (`data/`, about 440 KB). They are generated from
the JavaScript package with `node tools/build-data.mjs`, so all ports use the same data. The same data, with listing
counts and price levels, is published as open datasets:

- MCP server for AI assistants (Claude, Cursor, VS Code), built on this parser: [gunsnprices-mcp](https://github.com/gunsnprices/gunsnprices-mcp)
- Hugging Face: [gunsnprices/firearm-calibers](https://huggingface.co/datasets/gunsnprices/firearm-calibers),
  [gunsnprices/firearm-models](https://huggingface.co/datasets/gunsnprices/firearm-models)
- GitHub: [gunsnprices/firearm-datasets](https://github.com/gunsnprices/firearm-datasets)
- Kaggle: [gunsnprices](https://www.kaggle.com/gunsnprices)

## Development

```bash
composer install
vendor/bin/phpunit                    # 1,150 recorded titles + API tests

# compare with the JavaScript package on your own titles (TSV with a "name" column)
node tools/crosscheck-js.mjs path/to/gunsnprices-js titles.tsv > js.jsonl
php tools/crosscheck.php js.jsonl     # add --exact to force the fully translated patterns
```

The rules are written as JavaScript regular expressions. `src/Internal/Rx.php` translates them to PCRE with the same
meaning (ASCII-only `\d`, `\w` and `\b`, JavaScript's `\s`, case-insensitive matching that does not fold `ſ` into `s`),
which is what keeps the three ports identical. On ASCII-only text PCRE's own `\d`, `\w`, `\s` and `\b` already mean the
same thing and run faster, so each pattern also has that simpler form for ASCII titles.

## Limitations

- Titles from US retailers, in English.
- Models are covered for the 50 brands above. Other brands still get calibers and numbers.
- Built for firearms and ammunition. Magazines, parts and accessories get calibers and numbers but no model.

## License

The code is under the [MIT License](LICENSE). The data files `data/models.json` and `data/calibers.json` are under
[CC BY 4.0](LICENSE-DATA): you can use them for anything, including commercial use, if you credit
**GunsNPrices (gunsnprices.com)** with a link.

Questions, corrections and missing models: open an issue or write to app@gunsnprices.com.
Current gun and ammo prices: [gunsnprices.com](https://gunsnprices.com).
