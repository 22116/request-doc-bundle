<?php

declare(strict_types=1);

namespace LSBProject\RequestDocBundle\Util\ReflectionExtractor;

use Doctrine\Common\Annotations\Reader;
use LSBProject\RequestBundle\Request\AbstractRequest;
use LSBProject\RequestBundle\Util\ReflectionExtractor\DTO\Extraction;
use LSBProject\RequestBundle\Util\ReflectionExtractor\ReflectionExtractorInterface;
use OpenApi\Annotations\Schema;
use ReflectionClass;

final class ReflectionExtractorDecorator
{
    private ReflectionExtractorInterface $reflectionExtractor;
    private Reader $reader;

    public function __construct(ReflectionExtractorInterface $reflectionExtractor, Reader $reader)
    {
        $this->reflectionExtractor = $reflectionExtractor;
        $this->reader = $reader;
    }

    /**
     * @param ReflectionClass<AbstractRequest> $reflector
     * @param string[] $props
     */
    public function extract(ReflectionClass $reflector, array $props = []): ApiExtraction
    {
        $extractions = array_map(function (Extraction $extraction) use ($reflector) {
            /** @var Schema|null $schema */
            $schema = $this->reader->getPropertyAnnotation(
                $reflector->getProperty($extraction->getName()),
                Schema::class,
            );

            return new ApiPropertyExtraction($extraction, $reflector->getProperty($extraction->getName()), $schema);
        }, $this->reflectionExtractor->extract($reflector, $props));

        /** @var Schema|null $schema */
        $schema = $this->reader->getClassAnnotation($reflector, Schema::class);

        return new ApiExtraction($schema, $extractions);
    }
}
