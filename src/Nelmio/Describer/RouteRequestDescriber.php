<?php

declare(strict_types=1);

namespace LSBProject\RequestDocBundle\Nelmio\Describer;

use LSBProject\RequestBundle\Request\AbstractRequest;
use LSBProject\RequestDocBundle\Nelmio\Describer\Component\OperationDescriber;
use Nelmio\ApiDocBundle\Describer\ModelRegistryAwareInterface;
use Nelmio\ApiDocBundle\Describer\ModelRegistryAwareTrait;
use Nelmio\ApiDocBundle\RouteDescriber\RouteDescriberInterface;
use Nelmio\ApiDocBundle\RouteDescriber\RouteDescriberTrait;
use OpenApi\Annotations as OA;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use Symfony\Component\Routing\Route;

final class RouteRequestDescriber implements RouteDescriberInterface, ModelRegistryAwareInterface
{
    use RouteDescriberTrait;
    use ModelRegistryAwareTrait;

    private OperationDescriber $describer;

    public function __construct(OperationDescriber $describer)
    {
        $this->describer = $describer;
    }

    public function describe(OA\OpenApi $api, Route $route, ReflectionMethod $reflectionMethod): void
    {
        $requests = $this->findRequests($reflectionMethod);

        if (!$requests) {
            return;
        }

        foreach ($this->getOperations($api, $route) as $operation) {
            $operation->operationId = $operation->method . ucfirst($reflectionMethod->getName());

            foreach ($requests as $request) {
                /** @var ReflectionClass<AbstractRequest> $requestReflector */
                $requestReflector = $request->getClass();

                $this->describer->setModelRegistry($this->modelRegistry);
                $this->describer->describeRequest($operation, $requestReflector);
            }
        }
    }

    /**
     * @return ReflectionParameter[]
     */
    private function findRequests(ReflectionMethod $reflectionMethod): array
    {
        $requests = [];

        foreach ($reflectionMethod->getParameters() as $parameter) {
            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType && is_a($type->getName(), AbstractRequest::class, true)) {
                $requests[] = $parameter;
            }
        }

        return $requests;
    }
}
