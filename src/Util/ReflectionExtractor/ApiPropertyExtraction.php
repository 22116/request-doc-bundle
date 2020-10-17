<?php

declare(strict_types=1);

namespace LSBProject\RequestDocBundle\Util\ReflectionExtractor;

use LSBProject\RequestBundle\Util\ReflectionExtractor\DTO\Extraction;
use OpenApi\Annotations\Schema;
use ReflectionProperty;

final class ApiPropertyExtraction
{
    private ReflectionProperty $reflector;
    private Extraction $extraction;
    private ?Schema $schema;

    public function __construct(Extraction $extraction, ReflectionProperty $reflector, ?Schema $schema = null)
    {
        $this->extraction = $extraction;
        $this->reflector = $reflector;
        $this->schema = $schema;
    }

    public function getExtraction(): Extraction
    {
        return $this->extraction;
    }

    public function setExtraction(Extraction $extraction): void
    {
        $this->extraction = $extraction;
    }

    public function getSchema(): ?Schema
    {
        return $this->schema;
    }

    public function setSchema(?Schema $schema): void
    {
        $this->schema = $schema;
    }

    public function getReflector(): ReflectionProperty
    {
        return $this->reflector;
    }
}
