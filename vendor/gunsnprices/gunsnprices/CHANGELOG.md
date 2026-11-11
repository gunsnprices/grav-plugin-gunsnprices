# Changelog

## 0.1.0 (2026-11-10)

First release. PHP port of the [gunsnprices](https://www.npmjs.com/package/gunsnprices) JavaScript package 0.1.2
(itself a port of the [Python package](https://pypi.org/project/gunsnprices/)), with identical results on 63,755
store titles and on the 1,150 recorded test titles shared by all ports.

- `Parser::parseTitle()`: firearm or ammo, brand, model, calibers, magazine capacity, barrel length, rounds per box,
  cost per round.
- `Parser::identifyModel()` / `listBrands()`: 50 brands, the same model dictionaries that GunsNPrices uses on its site;
  decodes Glock part numbers.
- `Parser::findCalibers()` / `findCaliber()` / `normalizeCaliber()` / `listCalibers()`: 293 calibers with retailer
  spellings.
- `Parser::magazineCapacity()`, `barrelLength()`, `roundsPerBox()`, `costPerRound()`, `looksLikeAmmo()`.
- PHP 7.4 to 8.5, no dependencies beyond the bundled `json` and `pcre` extensions.
- Data in `data/*.json` (CC BY 4.0), generated from the JavaScript package by `tools/build-data.mjs`.
