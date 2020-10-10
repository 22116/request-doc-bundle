<?php

declare(strict_types=1);

namespace LSBProject\RequestDocBundle\Util\ReflectionExtractor;

use LSBProject\RequestBundle\Util\ReflectionExtractor\DTO\Extraction;
use OpenApi\Annotations\Schema;

final class ApiPropertyExtraction
{
    private Extraction $extraction;
    private ?Schema $schema;

    public function __construct(Extraction $extraction, ?Schema $schema = null)
    {
        $this->extraction = $extraction;
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
}
