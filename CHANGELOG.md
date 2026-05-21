# Changelog

All notable changes to this package will be documented in this file.

This project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

## [1.0.0] - 2025-05-19
### Added
- Initial release
- `DecodoClient` with `scrape()`, `scrapeMany()`, `scrapeWithJs()`, `scrapeFromGeo()`
- `ScrapeResult` DTO with `isSuccessful()` and `toArray()`
- `DecodoException` with descriptive static constructors
- `DecodoServiceProvider` with auto-discovery support
- `Decodo` facade
- Publishable `config/decodo.php`
- Pest feature and unit test suite
