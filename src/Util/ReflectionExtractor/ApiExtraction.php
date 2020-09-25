<?php

declare(strict_types=1);

namespace LSBProject\RequestDocBundle\Util\ReflectionExtractor;

use LSBProject\RequestBundle\Util\ReflectionExtractor\DTO\Extraction;
use OpenApi\Annotations\Schema;
use Symfony\Component\Validator\Constraint;

class ApiExtraction
{
    /**
     * @var ApiExtraction[]
     */
    private array $extractions = [];
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
     * @return ApiExtraction[]
     */
    public function getExtractions(): array
    {
        return $this->extractions;
    }

    /**
     * @param ApiExtraction[] $extractions
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
