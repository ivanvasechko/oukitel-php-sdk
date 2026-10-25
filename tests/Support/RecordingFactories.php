<?php

declare(strict_types=1);

namespace IvanVasechko\Oukitel\Tests\Support;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;

final class RecordingFactories implements RequestFactoryInterface, StreamFactoryInterface
{
    public int $requests = 0;

    public int $streams = 0;

    public function createRequest(string $method, $uri): RequestInterface
    {
        $this->requests++;

        return (new Psr17Factory)->createRequest($method, $uri);
    }

    public function createStream(string $content = ''): StreamInterface
    {
        $this->streams++;

        return (new Psr17Factory)->createStream($content);
    }

    public function createStreamFromFile(string $filename, string $mode = 'r'): StreamInterface
    {
        return (new Psr17Factory)->createStreamFromFile($filename, $mode);
    }

    public function createStreamFromResource($resource): StreamInterface
    {
        return (new Psr17Factory)->createStreamFromResource($resource);
    }
}
