<?php

declare(strict_types=1);

namespace LSBProject\RequestDocBundle\Nelmio\Describer\Component;

use Doctrine\Common\Annotations\Reader;
use LSBProject\RequestBundle\Configuration\RequestStorage;
use LSBProject\RequestBundle\Request\AbstractRequest;
use LSBProject\RequestBundle\Request\Factory\RequestPropertyHelperTrait;
use LSBProject\RequestBundle\Util\NamingConversion\NamingConversionInterface;
use LSBProject\RequestDocBundle\Util\ReflectionExtractor\ApiPropertyExtraction;
use LSBProject\RequestDocBundle\Util\ReflectionExtractor\ReflectionExtractorDecorator;
use Nelmio\ApiDocBundle\Describer\ModelRegistryAwareInterface;
use Nelmio\ApiDocBundle\Describer\ModelRegistryAwareTrait;
use Nelmio\ApiDocBundle\OpenApiPhp\Util;
use OpenApi\Annotations as OA;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use Symfony\Component\HttpFoundation\Request;

use const OpenApi\UNDEFINED;

class OperationDescriber implements ModelRegistryAwareInterface
{
    use RequestPropertyHelperTrait;
    use ModelRegistryAwareTrait;

    private const BODY_REQUESTS = [Request::METHOD_POST, Request::METHOD_PUT, Request::METHOD_PATCH];

    private PropertyDescriber $propertyDescriber;
    private ReflectionExtractorDecorator $extractorDecorator;
    private ContainerInterface $container;
    private NamingConversionInterface $namingConversion;
    private Reader $reader;

    public function __construct(
        PropertyDescriber $propertyDescriber,
        ReflectionExtractorDecorator $extractorDecorator,
        ContainerInterface $container,
        NamingConversionInterface $namingConversion,
        Reader $reader
    ) {
        $this->propertyDescriber = $propertyDescriber;
        $this->extractorDecorator = $extractorDecorator;
        $this->container = $container;
        $this->namingConversion = $namingConversion;
        $this->reader = $reader;
    }

    /**
     * @param ReflectionClass<AbstractRequest> $classReflector
     */
    public function describeRequest(OA\Operation $operation, ReflectionClass $classReflector): void
    {
        $properties = $this->extractorDecorator->extract($classReflector, $this->filterProps($classReflector));
        $requiredProperties = [];
        $bodyProperties = [];

        foreach ($properties->getExtractions() as $property) {
            $requestStorage = $property->getExtraction()->getRequestStorage();

            if (!$requestStorage) {
                $requestStorage = new RequestStorage([]);
            }

            $sources = $requestStorage->getSources();

            if (in_array(RequestStorage::QUERY, $sources)) {
                $this->describeOperationParameter($operation, $property, RequestStorage::QUERY);
            }

            if (in_array(RequestStorage::PATH, $sources)) {
                $this->describeOperationParameter($operation, $property, RequestStorage::PATH);
            }

            if (in_array(RequestStorage::HEAD, $sources)) {
                $this->describeOperationParameter($operation, $property, RequestStorage::HEAD);
            }

            if (
                in_array(strtoupper($operation->method), self::BODY_REQUESTS) &&
                in_array(RequestStorage::BODY, $sources)
            ) {
                $propertyName = $this->getNamingConversion($requestStorage->getConverter())->normalize(
                    $property->getExtraction()->getName(),
                );
                $bodyProperties[$propertyName] = $this->propertyDescriber->describe($property);

                if (
                    null === $property->getExtraction()->getDefault() &&
                    !$property->getExtraction()->getConfiguration()->isOptional() &&
                    !in_array($propertyName, $requiredProperties)
                ) {
                    $requiredProperties[] = $propertyName;
                }
            }
        }

        if (!in_array(strtoupper($operation->method), self::BODY_REQUESTS)) {
            return;
        }

        /** @var OA\AbstractAnnotation $requestBodyAnnotation */
        $requestBodyAnnotation = $this->reader->getClassAnnotation($classReflector, OA\RequestBody::class);

        /** @var OA\RequestBody $requestBody */
        $requestBody = Util::getChild($operation, OA\RequestBody::class);

        Util::merge($requestBody, $requestBodyAnnotation);

        /** @phpstan-ignore-next-line */
        $requestBody->content = UNDEFINED === $requestBody->content ? [] : $requestBody->content;
        $schema = $this->getContentSchemaForType($requestBody);
        $schema->type = 'object';
        $schema->properties = array_map(
            function (OA\Schema $propertySchema, string $k) use ($schema) {
                $property = Util::getProperty($schema, $k);

                Util::merge($property, $propertySchema);

                return $property;
            },
            $bodyProperties,
            array_keys($bodyProperties),
        );

        if ($requiredProperties) {
            $schema->required = $requiredProperties;
        }
    }

    private function describeOperationParameter(
        OA\Operation $operation,
        ApiPropertyExtraction $property,
        string $inType
    ): void {
        $requestStorage = $property->getExtraction()->getRequestStorage();

        if (!$requestStorage) {
            $requestStorage = new RequestStorage([]);
        }

        $propertyName = $this->getNamingConversion(
            $requestStorage->getConverter(),
        )->normalize($property->getExtraction()->getName());
        $parameter = Util::getOperationParameter($operation, $propertyName, $inType);

        /** @var OA\Schema $schema */
        $schema = Util::getChild($parameter, OA\Schema::class);

        $this->propertyDescriber->setModelRegistry($this->modelRegistry);

        Util::merge($schema, $this->propertyDescriber->describe($property));

        if (RequestStorage::PATH !== $inType) {
            $parameter->required = !$property->getExtraction()->getConfiguration()->isOptional();
        } else {
            $parameter->required = true;
        }
    }

    private function getNamingConversion(?string $converter = null): NamingConversionInterface
    {
        return $this->container->has($converter ?: '')
            ? $this->container->get($converter ?: '')
            : $this->namingConversion;
    }

    private function getContentSchemaForType(OA\RequestBody $requestBody): OA\Schema
    {
        /** @phpstan-ignore-next-line */
        $requestBody->content = OA\UNDEFINED !== $requestBody->content ? $requestBody->content : [];

        $contentType = 'application/json';
        $index = 0;

        foreach ($requestBody->content as $index => $content) {
            if (UNDEFINED !== $content->mediaType) {
                $contentType = $content->mediaType;
            }
        }

        if (!isset($requestBody->content[$index])) {
            $requestBody->content[$index] = new OA\MediaType(['mediaType' => $contentType]);

            /** @var OA\Schema $schema */
            $schema = Util::getChild($requestBody->content[$index], OA\Schema::class);
            $schema->type = 'object';
        }

        /** @var OA\Schema $schema */
        $schema = Util::getChild($requestBody->content[$index], OA\Schema::class);

        return $schema;
    }
}
