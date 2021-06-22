# Composer global installer

A plugin to install stable, [remote](https://packagist.org/) [Composer](https://getcomposer.org) packages in a global 
vendor directory. The path to a global installed package is 
`[global-vendor-dir]/[pretty-package-name]/[pretty-package-version]`; for example, `symfony/console:v5.3.1` might be 
found under `/usr/lib/composer/vendor/symfony/console/v5.3.1`. It creates symlinks in the local vendor directory 
(`/path/to/project/vendor`) to assist [IDEs](https://nl.wikipedia.org/wiki/Integrated_development_environment). The 
autoloader will point to the resolved global vendor/package directory. You should make sure the global vendor directory
is writable.

## Installation

To install this package, run `composer require iwink/composer-global-installer`. It's also possible to install the 
plugin global: `composer global require iwink/composer-global-installer`.

## Options

You can configure the plugin using the `extra.global-installer` key in `composer.json` (and in the global 
`$COMPOSER_HOME/composer.json`):

- `path`: Path to global vendor directory.
- `exclude`: Array of excluded package names (including vendor prefix), these packages will be installed local.

Example:

```json
{
    ...
	"extra": {
        "global-installer": {
            "path": "path/to/global/vendor",
            "exclude": [
                "vendor/package",
                "vendor/package-two"
            ]
        }
    }
}

```

To disable the entire plugin, you can pass `false` to the `extra.global-installer` key:

```json
{
    ...
	"extra": {
        "global-installer": false
    }
}

```
