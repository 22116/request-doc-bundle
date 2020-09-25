<?php

declare(strict_types=1);

namespace LSBProject\RequestDocBundle\Util\ReflectionExtractor;

use LSBProject\RequestBundle\Util\ReflectionExtractor\DTO\Extraction;
use OpenApi\Annotations\Schema;
use Symfony\Component\Validator\Constraint;

class ApiPropertyExtraction
{
    /**
     * @var Constraint[]
     */
    private array $validatorConstraints = [];
    private Extraction $extraction;
    private ?Schema $schema;

    /**
     * @param Constraint[] $validatorConstraints
     */
    public function __construct(Extraction $extraction, ?Schema $schema = null, array $validatorConstraints = [])
    {
        $this->extraction = $extraction;
        $this->schema = $schema;
        $this->validatorConstraints = $validatorConstraints;
    }

    /**
     * @return Constraint[]
     */
    public function getValidatorConstraints(): array
    {
        return $this->validatorConstraints;
    }

    /**
     * @param Constraint[] $validatorConstraints
     */
    public function setValidatorConstraints(array $validatorConstraints): void
    {
        $this->validatorConstraints = $validatorConstraints;
    }

    public function addValidatorConstraints(Constraint $constraint): void
    {
        $this->validatorConstraints[] = $constraint;
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
