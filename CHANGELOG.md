# v1.0.0
## 2026-11-11

1. [](#new)
    * First release
    * `firearm_specs` filter: an HTML list of the specs read from a gun or ammo product title (type, brand, model, caliber, caliber type, magazine capacity, barrel length, rounds per box, cost per round)
    * `cost_per_round` filter: the cost of one round from a title or a round count and a price, as "$0.38 per round", "$0.38/rd" or "$0.38"
    * `caliber_name`, `barrel_length`, `magazine_capacity` and `rounds_per_box` filters
    * `firearm_specs()`, `find_calibers()` and `list_calibers()` functions, and an `ammo` test
    * `[firearm-specs]` and `[cost-per-round]` shortcodes when the Shortcode Core plugin is installed (optional)
    * Filters and functions are allowed in the Grav 2 sandbox for Twig in page content
    * Settings: barrel length unit (in, cm, mm), cost per round format, currency symbol, and optional links from caliber names to their GunsNPrices.com price pages (off by default)
    * Bundles the gunsnprices/gunsnprices 0.1.0 parser library (MIT code, CC BY 4.0 data) in `vendor/`
    * Grav 1.7 and 2, PHP 7.4 and later
