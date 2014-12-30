# Installation

Add this line to your `composer.json` :

```json
{
    "require": {
        "web/statsd-bundle": "@stable"
    }
}
```

Update your vendors :

```
composer update web/statsd-bundle
```

## Registering

```php
class AppKernel extends \Symfony\Component\HttpKernel\Kernel
{
    public function registerBundles()
    {
        $bundles = array(
            new Web\Bundle\StatsdBundle\WebStatsdBundle(),
        );
    }
}
```

For the configuration read the [usage part](usage.md) of the documentation.

[TOC](../README.md)