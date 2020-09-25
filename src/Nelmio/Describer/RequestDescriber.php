<?php

declare(strict_types=1);

namespace LSBProject\RequestDocBundle\Nelmio\Describer;

use LSBProject\RequestBundle\Request\AbstractRequest;
use LSBProject\RequestBundle\Request\Factory\Param\CompositeFactory;
use LSBProject\RequestBundle\Request\Manager\RequestManagerInterface;
use LSBProject\RequestBundle\Util\ReflectionExtractor\DTO\Extraction;
use LSBProject\RequestBundle\Util\ReflectionExtractor\ReflectionExtractorInterface;
use Nelmio\ApiDocBundle\Describer\ModelRegistryAwareInterface;
use Nelmio\ApiDocBundle\Describer\ModelRegistryAwareTrait;
use Nelmio\ApiDocBundle\Model\Model;
use Nelmio\ApiDocBundle\ModelDescriber\ModelDescriberInterface;
use OpenApi\Annotations\Schema;
use ReflectionClass;
use ReflectionProperty;
use Symfony\Component\HttpFoundation\Request;

final class RequestDescriber implements ModelDescriberInterface, ModelRegistryAwareInterface
{
    use ModelRegistryAwareTrait;

    private RequestManagerInterface $requestManager;
    private ReflectionExtractorInterface $reflectionExtractor;

    public function __construct(
        RequestManagerInterface $requestManager,
        ReflectionExtractorInterface $reflectionExtractor
    ) {
        $this->requestManager = $requestManager;
        $this->reflectionExtractor = $reflectionExtractor;
    }

    public function describe(Model $model, Schema $schema): void
    {
        $meta = new ReflectionClass($model->getType()->getClassName() ?: '');
        $compositeFactory = new CompositeFactory($this->requestManager, $this);
        $props = $this->reflectionExtractor->extract($meta, $this->filterProps($meta));

        /** @var Extraction $prop */
        foreach ($props as $prop) {
        }
    }

    public function supports(Model $model): bool
    {
        return is_a($model->getType()->getClassName() ?: '', AbstractRequest::class, true);
    }

    /**
     * @param ReflectionClass<AbstractRequest> $meta
     *
     * @return string[]
     */
    private function filterProps(ReflectionClass $meta): array
    {
        $props = array_filter(
            $meta->getProperties(),
            function (ReflectionProperty $prop) use ($meta) {
                $method = 'set' . ucfirst($prop->getName());

                return Request::class !== $prop->getDeclaringClass()->getName() &&
                    ($prop->isPublic() || ($meta->hasMethod($method) && $meta->getMethod($method)->isPublic()));
            },
        );

        return array_map(function (ReflectionProperty $property) {
            return $property->getName();
        }, $props);
    }
}
