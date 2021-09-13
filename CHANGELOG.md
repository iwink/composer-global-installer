# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.2] - 2021-09-13
### Fixed
- Throws explicit exception on an invalid path to prevent confusion.

## [1.0.1] - 2021-08-26
### Changed
- Adhere to PSR-12 coding standards.

## [1.0.0] - 2021-07-01
### Added
- Initial stable release.

## [0.2.2] - 2021-07-01
### Fixed
- Use default transport option for path downloader.

## [0.2.1] - 2021-06-29
### Changed
- Default path is now `/usr/local/lib/composer/vendor/` due to OSX-restrictions.

## [0.2.0] - 2021-06-24
### Changed
- Unzip global installed packages to the system's temp directory for faster installation.

## [0.1.3] - 2021-06-23
### Fixed
- Better documentation about packages that depend on being installed locally.

## [0.1.2] - 2021-06-23
### Fixed
- Correctly merge the global and local plugin options.

## [0.1.1] - 2021-06-22
### Fixed
- Valid JSON examples.

## [0.1.0] - 2021-06-22
### Added
- Support for a global installer directory.

[Unreleased]: https://github.com/iwink/composer-global-installer/compare/v1.0.2...main
[1.0.2]: https://github.com/iwink/composer-global-installer/compare/v1.0.1...v1.0.2
[1.0.1]: https://github.com/iwink/composer-global-installer/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/iwink/composer-global-installer/compare/v0.2.2...v1.0.0
[0.2.2]: https://github.com/iwink/composer-global-installer/compare/v0.2.1...v0.2.2
[0.2.1]: https://github.com/iwink/composer-global-installer/compare/v0.2.0...v0.2.1
[0.2.0]: https://github.com/iwink/composer-global-installer/compare/v0.1.3...v0.2.0
[0.1.3]: https://github.com/iwink/composer-global-installer/compare/v0.1.2...v0.1.3
[0.1.2]: https://github.com/iwink/composer-global-installer/compare/v0.1.1...v0.1.2
[0.1.1]: https://github.com/iwink/composer-global-installer/compare/v0.1.0...v0.1.1
[0.1.0]: https://github.com/iwink/composer-global-installer/releases/tag/v0.1.0
