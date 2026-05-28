# Changelog

All notable changes to this package will be documented in this file.

This project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

## [1.1.1] - 2026-05-28
### Added
- Support for Laravel 13, Pest 4, and PHPUnit 12.
- Support for `cookies` and `passthrough` in `PayloadBuilder`.
- Support for v3 batch format (passing an array of URLs to the `url` parameter).
- Improved `DecodoFake` with better assertion capabilities for scrapes, tasks, and batches.

### Improved
- Refactored DTOs to use promoted properties with `public readonly` access for better DX.
- Improved API v3 compatibility across the entire module.
- Enhanced webhook handler with `passthrough` validation and improved status mapping.
- Normalized code style across the package.
- Improved proxy pool domain auto-detection in `PayloadBuilder`.

## [1.1.0] - 2026-05-27
### Added
- `AsyncDecodoClient` and `DecodoAsync` facade for v3 Asynchronous API support.
- `PayloadBuilder` for fluent and expressive request configuration.
- Database tracking for async tasks and batches with `DecodoTask` and `DecodoBatch` models.
- Database migrations for task and batch persistence.
- Result caching via `DecodoResultCache`.
- Webhook support with automatic route registration and callback URL injection.
- New events: `DecodoTaskCompleted`, `DecodoTaskFaulted`, `DecodoTaskExpired`, and `DecodoBatchCompleted`.
- Artisan commands: `decodo:status`, `decodo:retry`, and `decodo:prune`.
- Automatic pruning of old records via scheduled task.
- New `DecodoClient` methods: `screenshot()`, `scrapeAsMarkdown()`, and `scrapeWithParser()`.
- `DecodoFake` testing helper for mocking API responses.
- `TaskResponse` and `BatchTaskResponse` DTOs.
- Support for custom headers, cookies, and HTTP methods in requests.
- Rate-limit protection for batch task submissions.

### Improved
- `DecodoClient` now uses `PayloadBuilder` internally for better consistency.
- Enhanced exception handling with more descriptive error messages.

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
