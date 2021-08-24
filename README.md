# Composer global installer

[![License](https://poser.pugx.org/iwink/composer-global-installer/license.png)](https://packagist.org/packages/iwink/composer-global-installer)
[![Tag](https://img.shields.io/github/v/tag/iwink/composer-global-installer)](https://github.com/iwink/composer-global-installer/releases)

A plugin to install stable, [remote](https://packagist.org/) [Composer](https://getcomposer.org) packages in a global 
vendor directory. The path to a global installed package is 
`[global-vendor-dir]/[pretty-package-name]/[pretty-package-version]`; for example, `symfony/console:v5.3.1` might be 
found under `/usr/local/lib/composer/vendor/symfony/console/v5.3.1`. It creates symlinks in the local vendor directory 
(`/path/to/project/vendor`) to assist [IDEs](https://nl.wikipedia.org/wiki/Integrated_development_environment). The 
autoloader will point to the resolved global vendor/package directory. You should make sure the global vendor directory
is writable.

## Installation

To install this package, run `composer require iwink/composer-global-installer`. It's also possible to install the 
plugin global: `composer global require iwink/composer-global-installer`.

## Options

You can configure the plugin using the `extra.global-installer` key in `composer.json` (and in the global 
`$COMPOSER_HOME/composer.json`):

- `path`: Path to global vendor directory. (default: `/usr/local/lib/composer/vendor/`)
- `exclude`: Array of excluded package names (including vendor prefix), these packages will be installed locally.

Example:

```json
{
	"extra": {
		"global-installer": {
			"path": "path/to/global/vendor/",
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
	"extra": {
		"global-installer": false
	}
}

```

## Caveats
### Unsupported libraries
Some libraries, like 
[`laminas/laminas-zendframework-bridge`](https://packagist.org/packages/laminas/laminas-zendframework-bridge) and 
[`phpunit/phpunit`](https://packagist.org/packages/phpunit/phpunit), expect the package to be installed locally. If one
or more of your projects depend on such libraries, you can exclude them in the global composer configuration:

`composer global config --json extra.global-installer.exclude '["laminas/laminas-zendframework-bridge", "phpunit/phpunit"]'`

### Packages with patches
If you use a seperate plugin (for example [`cweagans/composer-patches`](https://github.com/cweagans/composer-patches))
to apply patches to packages, it might not be a good idea to install those packages globally.
Other projects which use the same package will receive the changes from the applied patch as well.
To prevent this, we recommend excluding packages which you have patched.
