<?php

declare(strict_types=1);

namespace LSBProject\RequestDocBundle\Nelmio\Describer;

use InvalidArgumentException;
use LSBProject\RequestBundle\Configuration\RequestStorage;
use LSBProject\RequestBundle\Request\AbstractRequest;
use LSBProject\RequestBundle\Request\Factory\RequestPropertyHelperTrait;
use LSBProject\RequestBundle\Util\NamingConversion\NamingConversionInterface;
use LSBProject\RequestBundle\Util\ReflectionExtractor\DTO\Extraction;
use LSBProject\RequestDocBundle\Util\ReflectionExtractor\ApiPropertyExtraction;
use LSBProject\RequestDocBundle\Util\ReflectionExtractor\ReflectionExtractorDecorator;
use Nelmio\ApiDocBundle\Describer\ModelRegistryAwareInterface;
use Nelmio\ApiDocBundle\Describer\ModelRegistryAwareTrait;
use Nelmio\ApiDocBundle\OpenApiPhp\Util;
use Nelmio\ApiDocBundle\PropertyDescriber\PropertyDescriberInterface;
use Nelmio\ApiDocBundle\RouteDescriber\RouteDescriberInterface;
use Nelmio\ApiDocBundle\RouteDescriber\RouteDescriberTrait;
use OpenApi\Annotations as OA;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PropertyInfo\Type;
use Symfony\Component\Routing\Route;

final class RouteRequestDescriber implements RouteDescriberInterface, ModelRegistryAwareInterface
{
    use RouteDescriberTrait;
    use ModelRegistryAwareTrait;
    use RequestPropertyHelperTrait;

    private ReflectionExtractorDecorator $extractorDecorator;
    private ContainerInterface $container;
    private NamingConversionInterface $namingConversion;

    /** @var PropertyDescriberInterface[] */
    private iterable $describers;

    /** @var array<string, OA\Schema> */
    private array $cachedProperties = [];

    /**
     * @param PropertyDescriberInterface[] $describers
     */
    public function __construct(
        ReflectionExtractorDecorator $extractorDecorator,
        ContainerInterface $container,
        NamingConversionInterface $namingConversion,
        iterable $describers = []
    ) {
        $this->extractorDecorator = $extractorDecorator;
        $this->container = $container;
        $this->namingConversion = $namingConversion;
        $this->describers = $describers;
    }

    public function describe(OA\OpenApi $api, Route $route, ReflectionMethod $reflectionMethod): void
    {
        $requests = $this->findRequests($reflectionMethod);

        if (!$requests) {
            return;
        }

        foreach ($this->getOperations($api, $route) as $operation) {
            $operation->operationId = $operation->method . ucfirst($reflectionMethod->getName());

            foreach ($requests as $request) {
                /** @var ReflectionClass<AbstractRequest> $classReflector */
                $classReflector = $request->getClass();
                $properties = $this->extractorDecorator->extract($classReflector, $this->filterProps($classReflector));
                $methodsHavingBody = [Request::METHOD_POST, Request::METHOD_PUT, Request::METHOD_PATCH];
                $bodyProperties = [];
                $requiredProperties = [];

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
                        in_array(strtoupper($operation->method), $methodsHavingBody) &&
                        in_array(RequestStorage::BODY, $sources)
                    ) {
                        $propertyName = $this->getNamingConversion($requestStorage->getConverter())->normalize(
                            $property->getExtraction()->getName(),
                        );
                        $bodyProperties[$propertyName] = $this->createPropertySchema($property);

                        if (
                            null === $property->getExtraction()->getDefault() &&
                            !$property->getExtraction()->getConfiguration()->isOptional() &&
                            !in_array($propertyName, $requiredProperties)
                        ) {
                            $requiredProperties[] = $propertyName;
                        }
                    }
                }

                if (!in_array(strtoupper($operation->method), $methodsHavingBody)) {
                    continue;
                }

                /** @var OA\RequestBody $requestBody */
                $requestBody = Util::getChild($operation, OA\RequestBody::class);

                foreach (['json'] as $mediaType) {
                    $schema = $this->getContentSchemaForType($requestBody, $mediaType);
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
            }
        }
    }

    private function getContentSchemaForType(OA\RequestBody $requestBody, string $type): OA\Schema
    {
        /** @phpstan-ignore-next-line */
        $requestBody->content = OA\UNDEFINED !== $requestBody->content ? $requestBody->content : [];

        switch ($type) {
            case 'json':
                $contentType = 'application/json';

                break;
            case 'xml':
                $contentType = 'application/xml';

                break;
            default:
                throw new InvalidArgumentException('Unsupported media type');
        }

        if (!isset($requestBody->content[$contentType])) {
            $requestBody->content[$contentType] = new OA\MediaType(
                [
                    'mediaType' => $contentType,
                ],
            );
            /** @var OA\Schema $schema */
            $schema = Util::getChild(
                $requestBody->content[$contentType],
                OA\Schema::class,
            );
            $schema->type = 'object';
        }

        /** @var OA\Schema $schema */
        $schema = Util::getChild(
            $requestBody->content[$contentType],
            OA\Schema::class,
        );

        return $schema;
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

        $parameter = Util::getOperationParameter(
            $operation,
            $this->getNamingConversion(
                $requestStorage->getConverter(),
            )->normalize($property->getExtraction()->getName()),
            $inType,
        );

        /** @var OA\Schema $schema */
        $schema = Util::getChild($parameter, OA\Schema::class);

        Util::merge($schema, $this->createPropertySchema($property));

        if (RequestStorage::PATH !== $inType) {
            $parameter->required = !$property->getExtraction()->getConfiguration()->isOptional();
        } else {
            $parameter->required = true;
        }
    }

    private function createPropertySchema(ApiPropertyExtraction $property): OA\Schema
    {
        $propertySchema = new OA\Schema([]);
        $key = $propertySchema->schema . $property->getExtraction()->getName();

        if (in_array($key, array_keys($this->cachedProperties))) {
            return $this->cachedProperties[$key];
        }

        if (null !== $property->getExtraction()->getDefault()) {
            $propertySchema->default = $property->getExtraction()->getDefault();
        }

        foreach ($this->describers as $describer) {
            if ($describer instanceof ModelRegistryAwareInterface) {
                $describer->setModelRegistry($this->modelRegistry);
            }

            $types = [$this->createTypeInfo($property->getExtraction())];

            if ($describer->supports($types)) {
                $describer->describe($types, $propertySchema);
                $this->cachedProperties[$key] = $propertySchema;

                break;
            }
        }

        return $propertySchema;
    }

    private function getNamingConversion(?string $converter = null): NamingConversionInterface
    {
        return $this->container->has($converter ?: '')
            ? $this->container->get($converter ?: '')
            : $this->namingConversion;
    }

    /**
     * @return ReflectionParameter[]
     */
    private function findRequests(ReflectionMethod $reflectionMethod): array
    {
        $requests = [];

        foreach ($reflectionMethod->getParameters() as $parameter) {
            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType && is_a($type->getName(), AbstractRequest::class, true)) {
                $requests[] = $parameter;
            }
        }

        return $requests;
    }

    private function createTypeInfo(Extraction $extraction): Type
    {
        $configuration = $extraction->getConfiguration();
        $baseType = new Type(
            $configuration->isBuiltInType() ? ($configuration->getType() ?: '') : Type::BUILTIN_TYPE_OBJECT,
            $configuration->isOptional(),
            !$configuration->isBuiltInType() ? $configuration->getType() : null,
        );

        if ($configuration->isCollection()) {
            return new Type(Type::BUILTIN_TYPE_ARRAY, true, null, true, $baseType);
        }

        return $baseType;
    }
}
