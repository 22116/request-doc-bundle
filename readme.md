# LSBProjectRequestDocBundle
OpenApi 3.0 auto documentation for request-bundle. Adds `AbstractRequest` describer for
[nelmio/api-doc-bundle](https://github.com/nelmio/NelmioApiDocBundle)

## Installation

### Step 1: Download the Bundle

Open a command console, enter your project directory and execute the following command to download the latest stable version of this bundle:

```
$ composer require lsbproject/request-doc-bundle
```

This command requires you to have Composer installed globally, as explained in the installation chapter of the Composer documentation.

### Step 2: Enable the Bundle (If composer flex is not installed)
Then, enable the bundle by adding it to the list of registered bundles in the config/bundles.php file of your project:

```php
// config/bundles.php

return [
    // ...
    LSBProject\RequestDocBundle\LSBProjectRequestDocBundle::class => ['all' => true],
];
```
