<?php

declare(strict_types=1);

namespace PhpStandard\Router;

use ArrayIterator;
use PhpStandard\Router\Traits\MiddlewareAwareTrait;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** @package PhpStandard\Router */
class Route
{
    use MiddlewareAwareTrait;

    /** @var array<string,mixed> Route parameters */
    private array $parameters = [];

    /**
     * @param string $method
     * @param string|array<string> $path
     * @param RequestHandlerInterface|string $handler
     * @param null|string $name
     * @param null|array $middlewares
     * @return void
     */
    public function __construct(
        private string $method,
        private string|array $path,
        private RequestHandlerInterface|string $handler,
        private ?string $name = null,
        ?array $middlewares = null
    ) {
        $parts = is_string($path) ? [$path] : $path;
        $this->path = $this->sanitizePath(...$parts);
        $this->handler = $handler;

        $this->setMiddlewares(...$middlewares ?? []);
    }

    /** @return string  */
    public function getMethod(): string
    {
        return $this->method;
    }

    /** @return string  */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @param string ...$parts
     * @return Route
     */
    public function withPath(string ...$parts): Route
    {
        $that = clone $this;
        $that->path = $that->sanitizePath(...$parts);
        return $that;
    }

    /** @return RequestHandlerInterface|string  */
    public function getHandler(): RequestHandlerInterface|string
    {
        return $this->handler;
    }

    /** @return null|string  */
    public function getName(): ?string
    {
        return $this->name;
    }

    /** @return ArrayIterator  */
    public function getParams(): ArrayIterator
    {
        return new ArrayIterator($this->parameters);
    }

    /**
     * @param Param ...$params
     * @return Route
     */
    public function withParam(Param ...$params): Route
    {
        $that = clone $this;

        foreach ($params as $param) {
            $that->parameters[$param->getKey()] = $param->getValue();
        }

        return $that;
    }

    /**
     * @param ContainerInterface $container
     * @return Route
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     */
    public function resolve(ContainerInterface $container): self
    {
        $this->resolveHandler($container);
        $this->resolveMiddlewares($container);
        return $this;
    }

    /**
     * @param string ...$parts
     * @return string
     */
    private function sanitizePath(string ...$parts): string
    {
        $path = implode('/', $parts);
        $path = preg_replace('/\/+/', '/', $path);
        $path = trim($path, '/');
        $path = '/' . $path;

        return $path;
    }

    /**
     * @param ContainerInterface $container
     * @return void
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     */
    private function resolveHandler(
        ContainerInterface $container
    ): void {
        if (is_string($this->handler)) {
            $handler = $container->get($this->handler);

            if (!($handler instanceof RequestHandlerInterface)) {
                // Throw exception
            }

            $this->handler = $handler;
        }
    }

    /**
     * @param ContainerInterface $container
     * @return void
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     */
    private function resolveMiddlewares(ContainerInterface $container): void
    {
        /** @var array<MiddlewareInterface> $resolved */
        $resolved = [];

        foreach ($this->middlewares as $middleware) {
            if (is_string($middleware)) {
                $middleware = $container->get($middleware);
            }

            if (!($middleware instanceof MiddlewareInterface)) {
                // Throw exception
            }

            $resolved[] = $middleware;
        }

        $this->middlewares = $resolved;
    }
}
