<?php

declare(strict_types=1);

namespace LSBProject\RequestDocBundle\Nelmio\Describer;

use LSBProject\RequestBundle\Util\ReflectionExtractor\DTO\Extraction;
use Symfony\Component\PropertyInfo\Type;

trait DescriberTrait
{
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
