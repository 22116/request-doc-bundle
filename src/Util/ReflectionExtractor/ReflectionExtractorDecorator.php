<?php

declare(strict_types=1);

namespace LSBProject\RequestDocBundle\Util\ReflectionExtractor;

use Doctrine\Common\Annotations\Reader;
use LSBProject\RequestBundle\Util\ReflectionExtractor\DTO\Extraction;
use LSBProject\RequestBundle\Util\ReflectionExtractor\ReflectionExtractor;
use OpenApi\Annotations\Schema;
use ReflectionClass;
use Symfony\Component\Validator\Constraint;

final class ReflectionExtractorDecorator
{
    private ReflectionExtractor $reflectionExtractor;
    private Reader $reader;

    public function __construct(ReflectionExtractor $reflectionExtractor, Reader $reader)
    {
        $this->reflectionExtractor = $reflectionExtractor;
        $this->reader = $reader;
    }

    /**
     * @param string[] $props
     *
     * @return ApiPropertyExtraction[]
     */
    public function extract(ReflectionClass $reflector, array $props = []): ApiExtraction
    {
        $extractions = $this->reflectionExtractor->extract($reflector, $props);
        $extractions = array_map(function (Extraction $extraction) use ($reflector) {
            /** @var Schema|null $schema */
            $schema = $this->reader->getPropertyAnnotation(
                $reflector->getProperty($extraction->getName()),
                Schema::class
            );

            $constraints = [];
            $annotations = $this->reader->getPropertyAnnotations($reflector->getProperty($extraction->getName()));

            foreach ($annotations as $annotation) {
                if (is_subclass_of($annotation, Constraint::class)) {
                    $constraints[] = $annotation;
                }
            }

            return new ApiPropertyExtraction($extraction, $schema, $constraints);
        }, $extractions);

        /** @var Schema|null $schema */
        $schema = $this->reader->getClassAnnotation($reflector, Schema::class);

        return new ApiExtraction($schema, $extractions);
    }
}
