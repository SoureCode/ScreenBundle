
# sourecode/screen-bundle

[![Packagist Version](https://img.shields.io/packagist/v/sourecode/screen-bundle.svg)](https://packagist.org/packages/sourecode/screen-bundle)
[![Downloads](https://img.shields.io/packagist/dt/sourecode/screen-bundle.svg)](https://packagist.org/packages/sourecode/screen-bundle)
[![CI](https://github.com/SoureCode/ScreenBundle/actions/workflows/ci.yml/badge.svg?branch=master)](https://github.com/SoureCode/ScreenBundle/actions/workflows/ci.yml)
[![codecov](https://codecov.io/gh/SoureCode/ScreenBundle/branch/master/graph/badge.svg?token=LVVINTVXAQ)](https://codecov.io/gh/SoureCode/ScreenBundle)
[![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2FSoureCode%2FScreenBundle%2Fmaster)](https://dashboard.stryker-mutator.io/reports/github.com/SoureCode/ScreenBundle/master)

This bundle provides to manage GNU Screen sessions in Symfony applications.

- [License](./LICENSE)

## Installation

Make sure Composer is installed globally, as explained in the
[installation chapter](https://getcomposer.org/doc/00-intro.md)
of the Composer documentation.

### Applications that use Symfony Flex

Open a command console, enter your project directory and execute:

```console
composer require sourecode/screen-bundle
```

### Applications that don't use Symfony Flex

#### Step 1: Download the Bundle

Open a command console, enter your project directory and execute the
following command to download the latest stable version of this bundle:

```console
composer require sourecode/screen-bundle
```

#### Step 2: Enable the Bundle

Then, enable the bundle by adding it to the list of registered bundles
in the `config/bundles.php` file of your project:

```php
// config/bundles.php

return [
    // ...
    \SoureCode\Bundle\Screen\SoureCodeScreenBundle::class => ['all' => true],
];
```

## Config

```yaml
# config/packages/soure_code_screen.yaml
soure_code_screen:
  screens:
    worker0:
      command: [ "php", "bin/console", "messenger:consume", "async", "--limit", "10", "-vv" ]
    worker1:
      command: [ "php", "bin/console", "messenger:consume", "async", "--limit", "10", "-vv" ]
```

## Development

**Note:** To run infection threaded the tests are written to be random, this causes to generate a lot of log files in the `tests/app/var/log` directory.
