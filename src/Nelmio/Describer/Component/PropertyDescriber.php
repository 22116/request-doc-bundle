<?php

declare(strict_types=1);

namespace LSBProject\RequestDocBundle\Nelmio\Describer\Component;

use Doctrine\Common\Annotations\Reader;
use LSBProject\RequestBundle\Util\ReflectionExtractor\DTO\Extraction;
use LSBProject\RequestDocBundle\Util\ReflectionExtractor\ApiPropertyExtraction;
use Nelmio\ApiDocBundle\Describer\ModelRegistryAwareInterface;
use Nelmio\ApiDocBundle\Describer\ModelRegistryAwareTrait;
use Nelmio\ApiDocBundle\ModelDescriber\Annotations\AnnotationsReader;
use Nelmio\ApiDocBundle\PropertyDescriber\PropertyDescriberInterface;
use OpenApi\Annotations as OA;
use Symfony\Component\PropertyInfo\Type;

use const OpenApi\UNDEFINED;

class PropertyDescriber implements ModelRegistryAwareInterface
{
    use ModelRegistryAwareTrait;

    private Reader $reader;

    /** @var PropertyDescriberInterface[] */
    private iterable $describers;

    /** @var array<string, OA\Property> */
    public static array $cachedProperties = [];

    /**
     * @param PropertyDescriberInterface[] $describers
     */
    public function __construct(Reader $reader, iterable $describers = [])
    {
        $this->reader = $reader;
        $this->describers = $describers;
    }

    public function describe(ApiPropertyExtraction $property): OA\Property
    {
        $propertySchema = new OA\Property([]);
        $key = $property->getReflector()->getDeclaringClass()->getName()
            . '::' . $property->getExtraction()->getName();

        if (array_key_exists($key, self::$cachedProperties)) {
            return self::$cachedProperties[$key];
        }

        if (null !== $property->getExtraction()->getDefault()) {
            $propertySchema->default = $property->getExtraction()->getDefault();
        }

        $annotationsReader = new AnnotationsReader($this->reader, $this->modelRegistry, []);
        $annotationsReader->updateProperty($property->getReflector(), $propertySchema);

        $type = $this->createTypeInfo($property->getExtraction());

        if (UNDEFINED !== $propertySchema->type && $type->getClassName()) {
            self::$cachedProperties[$key] = $propertySchema;

            return $propertySchema;
        }

        foreach ($this->describers as $describer) {
            if ($describer instanceof ModelRegistryAwareInterface) {
                $describer->setModelRegistry($this->modelRegistry);
            }

            if ($describer->supports([$type])) {
                $describer->describe([$type], $propertySchema);
                self::$cachedProperties[$key] = $propertySchema;

                break;
            }
        }

        return $propertySchema;
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
