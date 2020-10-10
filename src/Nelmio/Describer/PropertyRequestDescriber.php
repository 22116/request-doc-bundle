<?php

declare(strict_types=1);

namespace LSBProject\RequestDocBundle\Nelmio\Describer;

use LSBProject\RequestBundle\Configuration\RequestStorage;
use LSBProject\RequestBundle\Request\AbstractRequest;
use LSBProject\RequestBundle\Request\Factory\RequestPropertyHelperTrait;
use LSBProject\RequestBundle\Util\NamingConversion\NamingConversionInterface;
use LSBProject\RequestBundle\Util\ReflectionExtractor\DTO\Extraction;
use LSBProject\RequestDocBundle\Util\ReflectionExtractor\ReflectionExtractorDecorator;
use Nelmio\ApiDocBundle\Describer\ModelRegistryAwareInterface;
use Nelmio\ApiDocBundle\Describer\ModelRegistryAwareTrait;
use Nelmio\ApiDocBundle\Model\Model;
use Nelmio\ApiDocBundle\ModelDescriber\ModelDescriberInterface;
use Nelmio\ApiDocBundle\OpenApiPhp\Util;
use Nelmio\ApiDocBundle\PropertyDescriber\PropertyDescriberInterface;
use OpenApi\Annotations\Schema;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use Symfony\Component\PropertyInfo\Type;

final class PropertyRequestDescriber implements ModelDescriberInterface, ModelRegistryAwareInterface
{
    use ModelRegistryAwareTrait;
    use RequestPropertyHelperTrait;

    private ReflectionExtractorDecorator $extractorDecorator;
    private ContainerInterface $container;
    private NamingConversionInterface $namingConversion;

    /** @var PropertyDescriberInterface[] */
    private iterable $describers;

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

    public function describe(Model $model, Schema $schema): void
    {
        /** @var class-string<AbstractRequest> $className */
        $className = $model->getType()->getClassName() ?: '';
        $reflector = new ReflectionClass($className);
        $extraction = $this->extractorDecorator->extract($reflector, $this->filterProps($reflector));
        $extractionSchema = $extraction->getSchema();

        if ($extractionSchema) {
            $this->importSchema($extractionSchema, $schema);
        }

        $properties = $extraction->getExtractions();

        foreach ($properties as $property) {
            $requestStorage = $property->getExtraction()->getRequestStorage();

            if (!$requestStorage) {
                $requestStorage = new RequestStorage([]);
            }

            $converter = $requestStorage->getConverter();

            /** @var NamingConversionInterface $converter */
            $converter = $this->container->has($converter ?: '')
                ? $this->container->get($converter ?: '')
                : $this->namingConversion;

            $propertySchema = Util::getProperty($schema, $converter->normalize($property->getExtraction()->getName()));

            if (!$property->getExtraction()->isDefault() &&
                !$property->getExtraction()->getConfiguration()->isOptional()
            ) {
                if (!is_array($schema->required)) {
                    $schema->required = [];
                }

                if (!in_array($propertySchema->property, $schema->required)) {
                    $schema->required[] = $propertySchema->property;
                }
            }

            if ($property->getExtraction()->isDefault()) {
                $propertySchema->default = $property->getExtraction()->getDefault();
            }

            foreach ($this->describers as $describer) {
                if ($describer instanceof ModelRegistryAwareInterface) {
                    $describer->setModelRegistry($this->modelRegistry);
                }

                $types = [$this->createTypeInfo($property->getExtraction())];

                if ($describer->supports($types)) {
                    $describer->describe($types, $propertySchema);

                    break;
                }
            }
        }
    }

    public function supports(Model $model): bool
    {
        return is_a($model->getType()->getClassName() ?: '', AbstractRequest::class, true);
    }

    private function importSchema(Schema $source, Schema $target, bool $ignoreEmpty = false): void
    {
        foreach (get_object_vars($source) as $key => $value) {
            if (!$ignoreEmpty || $value) {
                $target->$key = $value;
            }
        }
    }

    private function createTypeInfo(Extraction $extraction): Type {
        $configuration = $extraction->getConfiguration();
        $baseType = new Type(
            $configuration->isBuiltInType() ? ($configuration->getType() ?: '') : Type::BUILTIN_TYPE_OBJECT,
            $configuration->isOptional(),
            !$configuration->isBuiltInType() ? $configuration->getType() : null
        );

        if ($configuration->isCollection()) {
            return new Type(Type::BUILTIN_TYPE_ARRAY, true, null, true, $baseType);
        }

        return $baseType;
    }
}
