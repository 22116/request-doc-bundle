<?php

declare(strict_types=1);

namespace LSBProject\RequestDocBundle\Util\ReflectionExtractor;

use OpenApi\Annotations\Schema;

final class ApiExtraction
{
    /**
     * @var ApiPropertyExtraction[]
     */
    private array $extractions;
    private ?Schema $schema;

    /**
     * @param ApiPropertyExtraction[] $extraction
     */
    public function __construct(?Schema $schema = null, array $extraction = [])
    {
        $this->extractions = $extraction;
        $this->schema = $schema;
    }

    /**
     * @return ApiPropertyExtraction[]
     */
    public function getExtractions(): array
    {
        return $this->extractions;
    }

    /**
     * @param ApiPropertyExtraction[] $extractions
     */
    public function setExtractions(array $extractions): void
    {
        $this->extractions = $extractions;
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
