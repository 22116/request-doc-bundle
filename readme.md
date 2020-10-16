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

## Examples

```php
<?php declare(strict_types=1);

namespace App\DTO;

use App\Entity\TestEntity;
use App\Service\TestService;
use LSBProject\RequestBundle\Configuration as LSB;
use LSBProject\RequestBundle\Request\AbstractRequest;
use OpenApi\Annotations as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @OA\RequestBody(@OA\MediaType(mediaType="application/json"))
 */
class TestRequest extends AbstractRequest
{
    /**
     * @Assert\NotBlank()
     * @LSB\PropConverter(name="foo_bar")
     * @LSB\RequestStorage({LSB\RequestStorage::QUERY})
     * @OA\Schema(description="Some awesome property")
     */
    public string $foo;

    /**
     * @LSB\PropConverter("App\Service\TestService")
     * @LSB\RequestStorage({LSB\RequestStorage::BODY})
     */
    public TestService $service;

    /**
     * @LSB\RequestStorage({LSB\RequestStorage::QUERY})
     */
    public int $testId;

    /**
     * @Assert\NotBlank()
     * @LSB\RequestStorage({LSB\RequestStorage::BODY})
     */
    private bool $barBaz;

    /**
     * @LSB\Entity(options={"id": "test_id"})
     * @LSB\RequestStorage({LSB\RequestStorage::BODY})
     */
    public TestEntity $entity;

    /**
     * @LSB\Entity(expr="repository.find(id)", mapping={"id": "test_id"})
     * @LSB\RequestStorage({LSB\RequestStorage::BODY})
     */
    public TestEntity $entityB;

    /**
     * @LSB\Entity(options={"mapping": {"bar_baz": "text"}})
     * @LSB\RequestStorage({LSB\RequestStorage::BODY})
     */
    public TestEntity $entityC;

    /**
     * @LSB\PropConverter(isDto=true)
     * @LSB\RequestStorage({LSB\RequestStorage::QUERY})
     */
    public SubRequest $params;

    public function setBarBaz(bool $flag): void
    {
        $this->barBaz = $flag;
    }

    public function getBarBaz(): bool
    {
        return $this->barBaz;
    }
}   
```
